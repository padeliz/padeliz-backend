<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Enums\PlayerCustomFieldType;
use App\Models\Player;
use App\Models\PlayerCustomField;
use App\Models\PlayerCustomFieldValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class PlayerCustomFieldControllerTest extends TestCase
{
    use RefreshDatabase;

    private Player $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = Player::factory()->manager()->create();
    }

    public function test_index_requires_manager_auth(): void
    {
        $this->getJson('/api/v1/manager/player-custom-fields')->assertUnauthorized();
    }

    public function test_player_only_is_forbidden(): void
    {
        Sanctum::actingAs(Player::factory()->playerOnly()->create());

        $this->getJson('/api/v1/manager/player-custom-fields')->assertForbidden();
    }

    public function test_crud_search_filter_and_lock_code_type(): void
    {
        Sanctum::actingAs($this->manager);

        $field = PlayerCustomField::factory()->create([
            'code' => 'notes_field',
            'label' => 'Notes Field',
            'type' => PlayerCustomFieldType::Text,
        ]);

        $this->getJson('/api/v1/manager/player-custom-fields?q=notes')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'notes_field');

        $this->getJson('/api/v1/manager/player-custom-fields?filters[type]=text')
            ->assertOk()
            ->assertJsonFragment(['code' => 'notes_field']);

        $this->getJson('/api/v1/manager/player-custom-fields/' . $field->id_player_custom_field)
            ->assertOk()
            ->assertJsonPath('data.code', 'notes_field');

        $this->postJson('/api/v1/manager/player-custom-fields', [
            'code' => 'new_field',
            'label' => 'New Field',
            'type' => 'text',
            'is_required' => true,
        ])->assertCreated()
            ->assertJsonPath('data.is_required', true);

        $this->postJson('/api/v1/manager/player-custom-fields', [
            'code' => 'bad_type',
            'label' => 'Bad',
            'type' => 'unknown',
        ])->assertStatus(422);

        $this->patchJson('/api/v1/manager/player-custom-fields/' . $field->id_player_custom_field, [
            'code' => 'changed',
            'type' => 'integer',
        ])->assertStatus(422);

        $this->patchJson('/api/v1/manager/player-custom-fields/' . $field->id_player_custom_field, [
            'label' => 'Updated Notes',
        ])->assertOk()
            ->assertJsonPath('data.label', 'Updated Notes');
    }

    public function test_destroy_blocked_when_values_exist(): void
    {
        Sanctum::actingAs($this->manager);

        $field = PlayerCustomField::factory()->create();
        PlayerCustomFieldValue::factory()->create([
            'player_custom_field_id' => $field->id_player_custom_field,
        ]);

        $this->deleteJson('/api/v1/manager/player-custom-fields/' . $field->id_player_custom_field)
            ->assertStatus(422);

        $empty = PlayerCustomField::factory()->create();
        $this->deleteJson('/api/v1/manager/player-custom-fields/' . $empty->id_player_custom_field)
            ->assertOk();
    }

    public function test_bulk_destroy_fails_for_fields_with_values(): void
    {
        Sanctum::actingAs($this->manager);

        $withValues = PlayerCustomField::factory()->create();
        $empty = PlayerCustomField::factory()->create();
        PlayerCustomFieldValue::factory()->create([
            'player_custom_field_id' => $withValues->id_player_custom_field,
        ]);

        $response = $this->deleteJson('/api/v1/manager/player-custom-fields/bulk', [
            'player_custom_field_ids' => [$withValues->id_player_custom_field, $empty->id_player_custom_field],
        ]);

        $response->assertStatus(207);
        $this->assertSame(1, $response->json('errors.total_successful'));
        $this->assertSame(1, $response->json('errors.total_failed'));
    }

    public function test_archive_and_unarchive(): void
    {
        Sanctum::actingAs($this->manager);

        $field = PlayerCustomField::factory()->create();

        $this->postJson('/api/v1/manager/player-custom-fields/' . $field->id_player_custom_field . '/archive')
            ->assertOk()
            ->assertJsonPath('data.archived_at', fn ($value) => $value !== null);

        $this->postJson('/api/v1/manager/player-custom-fields/' . $field->id_player_custom_field . '/unarchive')
            ->assertOk()
            ->assertJsonPath('data.archived_at', null);
    }
}
