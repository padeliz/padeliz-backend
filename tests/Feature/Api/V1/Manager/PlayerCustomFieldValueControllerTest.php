<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Enums\PlayerCustomFieldType;
use App\Models\PadelEvent;
use App\Models\Player;
use App\Models\PlayerCustomField;
use App\Models\PlayerCustomFieldOption;
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

    public function test_null_edition_allows_multiple_values(): void
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
    }

    public function test_edition_scoped_write_replaces_existing_row(): void
    {
        $player = Player::factory()->create();
        $event = PadelEvent::factory()->create();
        $field = PlayerCustomField::factory()->create();

        $this->postJson('/api/v1/manager/players/' . $player->id_player . '/custom-field-values', [
            'player_custom_field_id' => $field->id_player_custom_field,
            'event_id' => $event->id_event,
            'value' => 'First',
        ])->assertCreated();

        $firstId = PlayerCustomFieldValue::query()->firstOrFail()->id_player_custom_field_value;

        $this->postJson('/api/v1/manager/players/' . $player->id_player . '/custom-field-values', [
            'player_custom_field_id' => $field->id_player_custom_field,
            'event_id' => $event->id_event,
            'value' => 'Second',
        ])->assertCreated()
            ->assertJsonPath('data.value', 'Second');

        $this->assertSame(1, PlayerCustomFieldValue::query()->count());
        $this->assertNull(PlayerCustomFieldValue::query()->find($firstId));
    }

    public function test_invalidate_once_and_reject_second_invalidate(): void
    {
        $player = Player::factory()->create();
        $field = PlayerCustomField::factory()->create();
        $value = PlayerCustomFieldValue::factory()->create([
            'player_id' => $player->id_player,
            'player_custom_field_id' => $field->id_player_custom_field,
        ]);

        $this->postJson(
            '/api/v1/manager/players/' . $player->id_player . '/custom-field-values/' . $value->id_player_custom_field_value . '/invalidate',
            ['invalidation_reason' => 'Typo']
        )->assertOk()
            ->assertJsonPath('data.invalidation_reason', 'Typo');

        $this->postJson(
            '/api/v1/manager/players/' . $player->id_player . '/custom-field-values/' . $value->id_player_custom_field_value . '/invalidate'
        )->assertStatus(422);
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
