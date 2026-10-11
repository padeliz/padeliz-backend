<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\User;
use App\Models\PlayerCustomField;
use App\Models\PlayerCustomFieldOption;
use App\Models\PlayerCustomFieldValue;
use App\Models\PlayerCustomFieldValueOption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class PlayerCustomFieldOptionControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->manager()->create();
        Sanctum::actingAs($this->manager);
    }

    public function test_rejects_options_for_non_select_field(): void
    {
        $field = PlayerCustomField::factory()->create();

        $this->getJson('/api/v1/manager/player-custom-fields/' . $field->id_player_custom_field . '/options')
            ->assertStatus(422);
    }

    public function test_store_generates_code_from_label_and_update_locks_code(): void
    {
        $field = PlayerCustomField::factory()->select()->create();

        $response = $this->postJson('/api/v1/manager/player-custom-fields/' . $field->id_player_custom_field . '/options', [
            'label' => 'Beginner Level',
        ])->assertCreated();

        $optionId = $response->json('data.id_player_custom_field_option');
        $this->assertSame('beginner-level', $response->json('data.code'));

        $this->patchJson(
            '/api/v1/manager/player-custom-fields/' . $field->id_player_custom_field . '/options/' . $optionId,
            ['code' => 'changed', 'label' => 'Beginner Updated']
        )->assertStatus(422);

        $this->patchJson(
            '/api/v1/manager/player-custom-fields/' . $field->id_player_custom_field . '/options/' . $optionId,
            ['label' => 'Beginner Updated', 'is_active' => false]
        )->assertOk()
            ->assertJsonPath('data.label', 'Beginner Updated')
            ->assertJsonPath('data.is_active', false);
    }

    public function test_destroy_blocked_when_referenced_by_value_option(): void
    {
        $field = PlayerCustomField::factory()->select()->create();
        $option = PlayerCustomFieldOption::factory()->create([
            'player_custom_field_id' => $field->id_player_custom_field,
        ]);
        $value = PlayerCustomFieldValue::factory()->create([
            'player_custom_field_id' => $field->id_player_custom_field,
            'value_text' => null,
        ]);
        PlayerCustomFieldValueOption::create([
            'player_custom_field_value_id' => $value->id_player_custom_field_value,
            'option_id' => $option->id_player_custom_field_option,
            'option_label_snapshot' => $option->label,
        ]);

        $this->deleteJson(
            '/api/v1/manager/player-custom-fields/' . $field->id_player_custom_field . '/options/' . $option->id_player_custom_field_option
        )->assertStatus(422);

        $unused = PlayerCustomFieldOption::factory()->create([
            'player_custom_field_id' => $field->id_player_custom_field,
        ]);
        $this->deleteJson(
            '/api/v1/manager/player-custom-fields/' . $field->id_player_custom_field . '/options/' . $unused->id_player_custom_field_option
        )->assertOk();
    }
}
