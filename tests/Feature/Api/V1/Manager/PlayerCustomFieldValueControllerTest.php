<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Enums\PlayerCustomFieldType;
use App\Enums\PlayerCustomFieldValueReviewStatus;
use App\Models\Player;
use App\Models\PlayerCustomField;
use App\Models\PlayerCustomFieldOption;
use App\Models\PlayerCustomFieldSubmission;
use App\Models\PlayerCustomFieldValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class PlayerCustomFieldValueControllerTest extends TestCase
{
    use RefreshDatabase;

    private Player $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = Player::factory()->manager()->create();
        Sanctum::actingAs($this->manager);
    }

    public function test_submit_list_show_and_ownership(): void
    {
        $player = Player::factory()->create();
        $other = Player::factory()->create();
        $field = PlayerCustomField::factory()->create([
            'type' => PlayerCustomFieldType::Text,
            'rules_json' => null,
        ]);

        $this->postJson('/api/v1/manager/players/' . $player->id_player . '/custom-field-values', [
            'player_custom_field_id' => $field->id_player_custom_field,
            'value' => 'Hello',
        ])->assertCreated()
            ->assertJsonPath('data.submitted_via', 'manager')
            ->assertJsonPath('data.value', 'Hello')
            ->assertJsonPath('data.created_via_import_id', null);

        $valueId = PlayerCustomFieldValue::query()->firstOrFail()->id_player_custom_field_value;

        $this->getJson('/api/v1/manager/players/' . $player->id_player . '/custom-field-values')
            ->assertOk()
            ->assertJsonPath('data.0.id_player_custom_field_value', $valueId);

        $this->getJson('/api/v1/manager/players/' . $player->id_player . '/custom-field-values/' . $valueId)
            ->assertOk();

        $this->getJson('/api/v1/manager/players/' . $other->id_player . '/custom-field-values/' . $valueId)
            ->assertNotFound();
    }

    public function test_empty_manual_value_is_rejected(): void
    {
        $player = Player::factory()->create();
        $field = PlayerCustomField::factory()->create();

        $this->postJson('/api/v1/manager/players/' . $player->id_player . '/custom-field-values', [
            'player_custom_field_id' => $field->id_player_custom_field,
            'value' => '',
        ])->assertStatus(422);

        $this->assertSame(0, PlayerCustomFieldValue::query()->count());
    }

    public function test_archived_field_rejected_until_unarchived(): void
    {
        $player = Player::factory()->create();
        $field = PlayerCustomField::factory()->archived()->create();

        $this->postJson('/api/v1/manager/players/' . $player->id_player . '/custom-field-values', [
            'player_custom_field_id' => $field->id_player_custom_field,
            'value' => 'Nope',
        ])->assertStatus(422);

        $field->update(['archived_at' => null]);

        $this->postJson('/api/v1/manager/players/' . $player->id_player . '/custom-field-values', [
            'player_custom_field_id' => $field->id_player_custom_field,
            'value' => 'Yes',
        ])->assertCreated();
    }

    public function test_each_write_without_a_submission_starts_a_new_one(): void
    {
        $player = Player::factory()->create();
        $field = PlayerCustomField::factory()->create();

        $this->postJson('/api/v1/manager/players/' . $player->id_player . '/custom-field-values', [
            'player_custom_field_id' => $field->id_player_custom_field,
            'value' => 'One',
        ])->assertCreated();

        $this->postJson('/api/v1/manager/players/' . $player->id_player . '/custom-field-values', [
            'player_custom_field_id' => $field->id_player_custom_field,
            'value' => 'Two',
        ])->assertCreated();

        $this->assertSame(2, PlayerCustomFieldValue::query()->count());
        $this->assertSame(2, PlayerCustomFieldSubmission::query()->count());
    }

    public function test_rewriting_inside_one_submission_updates_the_row_in_place(): void
    {
        $player = Player::factory()->create();
        $field = PlayerCustomField::factory()->create();
        $submission = PlayerCustomFieldSubmission::factory()->create([
            'player_id' => $player->id_player,
        ]);

        $this->postJson('/api/v1/manager/players/' . $player->id_player . '/custom-field-values', [
            'player_custom_field_id' => $field->id_player_custom_field,
            'submission_id' => $submission->id_player_custom_field_submission,
            'value' => 'First',
        ])->assertCreated();

        $first = PlayerCustomFieldValue::query()->firstOrFail();

        $this->postJson('/api/v1/manager/players/' . $player->id_player . '/custom-field-values', [
            'player_custom_field_id' => $field->id_player_custom_field,
            'submission_id' => $submission->id_player_custom_field_submission,
            'value' => 'Second',
        ])->assertCreated()
            ->assertJsonPath('data.value', 'Second');

        $this->assertSame(1, PlayerCustomFieldValue::query()->count());
        $second = PlayerCustomFieldValue::query()->firstOrFail();
        $this->assertSame($first->id_player_custom_field_value, $second->id_player_custom_field_value);
        $this->assertSame((int) $first->inserted_at, (int) $second->inserted_at);
        $this->assertSame('Second', $second->value_text);
        $this->assertSame($first->review_status, $second->review_status);
        $this->assertSame($first->reviewed_at, $second->reviewed_at);
    }

    public function test_a_submission_from_another_player_is_rejected(): void
    {
        $player = Player::factory()->create();
        $field = PlayerCustomField::factory()->create();
        $foreignSubmission = PlayerCustomFieldSubmission::factory()->create();

        $this->postJson('/api/v1/manager/players/' . $player->id_player . '/custom-field-values', [
            'player_custom_field_id' => $field->id_player_custom_field,
            'submission_id' => $foreignSubmission->id_player_custom_field_submission,
            'value' => 'Nope',
        ])->assertStatus(422);
    }

    public function test_invalidate_route_is_gone(): void
    {
        $player = Player::factory()->create();
        $value = PlayerCustomFieldValue::factory()->create([
            'player_id' => $player->id_player,
        ]);

        $this->postJson(
            '/api/v1/manager/players/' . $player->id_player . '/custom-field-values/' . $value->id_player_custom_field_value . '/invalidate',
            ['invalidation_reason' => 'Typo']
        )->assertStatus(404);

        $this->assertSame(
            PlayerCustomFieldValueReviewStatus::Validated,
            $value->fresh()->review_status
        );
    }

    public function test_manager_write_is_validated_with_the_acting_manager(): void
    {
        $player = Player::factory()->create();
        $field = PlayerCustomField::factory()->create();

        $this->postJson('/api/v1/manager/players/' . $player->id_player . '/custom-field-values', [
            'player_custom_field_id' => $field->id_player_custom_field,
            'value' => 'Hello',
        ])->assertCreated()
            ->assertJsonPath('data.review_status', 'validated')
            ->assertJsonPath('data.reviewed_by_manager_id', $this->manager->id_player)
            ->assertJsonMissingPath('data.invalidated_at');

        $value = PlayerCustomFieldValue::query()->firstOrFail();
        $this->assertSame(PlayerCustomFieldValueReviewStatus::Validated, $value->review_status);
        $this->assertNotNull($value->reviewed_at);
        $this->assertSame((int) $this->manager->id_player, (int) $value->reviewed_by_manager_id);
    }

    public function test_select_value_with_option_snapshot(): void
    {
        $player = Player::factory()->create();
        $field = PlayerCustomField::factory()->select()->create();
        $option = PlayerCustomFieldOption::factory()->create([
            'player_custom_field_id' => $field->id_player_custom_field,
            'label' => 'Yes',
        ]);

        $this->postJson('/api/v1/manager/players/' . $player->id_player . '/custom-field-values', [
            'player_custom_field_id' => $field->id_player_custom_field,
            'option_id' => $option->id_player_custom_field_option,
        ])->assertCreated()
            ->assertJsonPath('data.value', 'Yes');
    }

    public function test_rejects_event_division_id_in_body(): void
    {
        $player = Player::factory()->create();
        $field = PlayerCustomField::factory()->create();

        $this->postJson('/api/v1/manager/players/' . $player->id_player . '/custom-field-values', [
            'player_custom_field_id' => $field->id_player_custom_field,
            'value' => 'Hello',
            'event_division_id' => 1,
        ])->assertStatus(422);
    }
}
