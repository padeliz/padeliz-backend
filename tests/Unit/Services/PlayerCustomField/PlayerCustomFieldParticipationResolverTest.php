<?php

declare(strict_types=1);

namespace Tests\Unit\Services\PlayerCustomField;

use App\Enums\PlayerCustomFieldType;
use App\Models\Edition;
use App\Models\Player;
use App\Models\PlayerCustomField;
use App\Models\PlayerCustomFieldOption;
use App\Models\PlayerCustomFieldValue;
use App\Models\PlayerCustomFieldValueOption;
use App\Services\PlayerCustomField\PlayerCustomFieldParticipationResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PlayerCustomFieldParticipationResolverTest extends TestCase
{
    use RefreshDatabase;

    private PlayerCustomFieldParticipationResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new PlayerCustomFieldParticipationResolver();
    }

    public function test_edition_scoped_value_overrides_global(): void
    {
        $edition = Edition::factory()->create();
        $player = Player::factory()->create();
        $field = PlayerCustomField::factory()->create([
            'label' => 'Shirt Size',
            'type' => PlayerCustomFieldType::Text,
            'sort_order' => 1,
        ]);

        PlayerCustomFieldValue::factory()->create([
            'player_id' => $player->id_player,
            'player_custom_field_id' => $field->id_player_custom_field,
            'edition_id' => null,
            'value_text' => 'Global M',
            'inserted_at' => time() - 100,
        ]);
        PlayerCustomFieldValue::factory()->create([
            'player_id' => $player->id_player,
            'player_custom_field_id' => $field->id_player_custom_field,
            'edition_id' => $edition->id_edition,
            'value_text' => 'Edition L',
            'inserted_at' => time(),
        ]);

        $result = $this->resolver->resolveForPlayers(
            (int) $edition->id_edition,
            [(int) $player->id_player],
            [(int) $field->id_player_custom_field]
        );

        $this->assertSame('Edition L', $result[(int) $player->id_player][0]['value']);
    }

    public function test_invalidated_values_are_ignored(): void
    {
        $edition = Edition::factory()->create();
        $player = Player::factory()->create();
        $field = PlayerCustomField::factory()->create(['type' => PlayerCustomFieldType::Text]);

        PlayerCustomFieldValue::factory()->create([
            'player_id' => $player->id_player,
            'player_custom_field_id' => $field->id_player_custom_field,
            'edition_id' => $edition->id_edition,
            'value_text' => 'Stale',
            'invalidated_at' => time(),
        ]);
        PlayerCustomFieldValue::factory()->create([
            'player_id' => $player->id_player,
            'player_custom_field_id' => $field->id_player_custom_field,
            'edition_id' => null,
            'value_text' => 'Active Global',
        ]);

        $result = $this->resolver->resolveForPlayers(
            (int) $edition->id_edition,
            [(int) $player->id_player],
            [(int) $field->id_player_custom_field]
        );

        $this->assertSame('Active Global', $result[(int) $player->id_player][0]['value']);
    }

    public function test_latest_inserted_at_wins_within_same_scope(): void
    {
        $edition = Edition::factory()->create();
        $player = Player::factory()->create();
        $field = PlayerCustomField::factory()->create(['type' => PlayerCustomFieldType::Text]);

        PlayerCustomFieldValue::factory()->create([
            'player_id' => $player->id_player,
            'player_custom_field_id' => $field->id_player_custom_field,
            'edition_id' => null,
            'value_text' => 'Older',
            'inserted_at' => time() - 500,
        ]);
        PlayerCustomFieldValue::factory()->create([
            'player_id' => $player->id_player,
            'player_custom_field_id' => $field->id_player_custom_field,
            'edition_id' => null,
            'value_text' => 'Newer',
            'inserted_at' => time(),
        ]);

        $result = $this->resolver->resolveForPlayers(
            (int) $edition->id_edition,
            [(int) $player->id_player],
            [(int) $field->id_player_custom_field]
        );

        $this->assertSame('Newer', $result[(int) $player->id_player][0]['value']);
    }

    public function test_multi_select_values_join_as_comma_separated_string(): void
    {
        $edition = Edition::factory()->create();
        $player = Player::factory()->create();
        $field = PlayerCustomField::factory()->multiSelect()->create(['label' => 'Tags']);
        $optionA = PlayerCustomFieldOption::factory()->create([
            'player_custom_field_id' => $field->id_player_custom_field,
            'label' => 'Alpha',
        ]);
        $optionB = PlayerCustomFieldOption::factory()->create([
            'player_custom_field_id' => $field->id_player_custom_field,
            'label' => 'Beta',
        ]);

        $value = PlayerCustomFieldValue::factory()->create([
            'player_id' => $player->id_player,
            'player_custom_field_id' => $field->id_player_custom_field,
            'edition_id' => null,
            'value_text' => null,
        ]);
        PlayerCustomFieldValueOption::query()->create([
            'player_custom_field_value_id' => $value->id_player_custom_field_value,
            'option_id' => $optionA->id_player_custom_field_option,
            'option_label_snapshot' => 'Alpha',
            'inserted_at' => time(),
        ]);
        PlayerCustomFieldValueOption::query()->create([
            'player_custom_field_value_id' => $value->id_player_custom_field_value,
            'option_id' => $optionB->id_player_custom_field_option,
            'option_label_snapshot' => 'Beta',
            'inserted_at' => time(),
        ]);

        $result = $this->resolver->resolveForPlayers(
            (int) $edition->id_edition,
            [(int) $player->id_player],
            [(int) $field->id_player_custom_field]
        );

        $this->assertSame('Alpha, Beta', $result[(int) $player->id_player][0]['value']);
    }

    public function test_returns_one_value_per_field(): void
    {
        $edition = Edition::factory()->create();
        $player = Player::factory()->create();
        $fieldA = PlayerCustomField::factory()->create([
            'label' => 'Field A',
            'type' => PlayerCustomFieldType::Text,
            'sort_order' => 1,
        ]);
        $fieldB = PlayerCustomField::factory()->create([
            'label' => 'Field B',
            'type' => PlayerCustomFieldType::Text,
            'sort_order' => 2,
        ]);

        PlayerCustomFieldValue::factory()->create([
            'player_id' => $player->id_player,
            'player_custom_field_id' => $fieldA->id_player_custom_field,
            'value_text' => 'A value',
        ]);
        PlayerCustomFieldValue::factory()->create([
            'player_id' => $player->id_player,
            'player_custom_field_id' => $fieldA->id_player_custom_field,
            'value_text' => 'A duplicate',
            'inserted_at' => time() - 100,
        ]);
        PlayerCustomFieldValue::factory()->create([
            'player_id' => $player->id_player,
            'player_custom_field_id' => $fieldB->id_player_custom_field,
            'value_text' => 'B value',
        ]);

        $result = $this->resolver->resolveForPlayers(
            (int) $edition->id_edition,
            [(int) $player->id_player],
            [(int) $fieldA->id_player_custom_field, (int) $fieldB->id_player_custom_field]
        );

        $entries = $result[(int) $player->id_player];
        $this->assertCount(2, $entries);
        $this->assertSame('A value', $entries[0]['value']);
        $this->assertSame('B value', $entries[1]['value']);
    }

    public function test_no_requested_field_is_omitted_when_value_exists(): void
    {
        $edition = Edition::factory()->create();
        $player = Player::factory()->create();
        $fields = collect(range(1, 3))->map(function (int $order): PlayerCustomField {
            $field = PlayerCustomField::factory()->create([
                'label' => 'Field ' . $order,
                'type' => PlayerCustomFieldType::Text,
                'sort_order' => $order,
            ]);
            PlayerCustomFieldValue::factory()->create([
                'player_id' => Player::factory()->create()->id_player,
                'player_custom_field_id' => $field->id_player_custom_field,
                'value_text' => 'Other player',
            ]);

            return $field;
        });

        foreach ($fields as $field) {
            PlayerCustomFieldValue::factory()->create([
                'player_id' => $player->id_player,
                'player_custom_field_id' => $field->id_player_custom_field,
                'value_text' => 'Value for ' . $field->label,
            ]);
        }

        $fieldIds = $fields->pluck('id_player_custom_field')->map(static fn ($id): int => (int) $id)->all();
        $result = $this->resolver->resolveForPlayers(
            (int) $edition->id_edition,
            [(int) $player->id_player],
            $fieldIds
        );

        $this->assertCount(3, $result[(int) $player->id_player]);
        $this->assertSame(
            ['Field 1', 'Field 2', 'Field 3'],
            array_column($result[(int) $player->id_player], 'label')
        );
    }
}
