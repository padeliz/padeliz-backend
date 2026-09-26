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
use Carbon\Carbon;
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
            [(int) $field->id_player_custom_field],
            $this->cutoffFor($edition)
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
            [(int) $field->id_player_custom_field],
            $this->cutoffFor($edition)
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
            [(int) $field->id_player_custom_field],
            $this->cutoffFor($edition)
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
            [(int) $field->id_player_custom_field],
            $this->cutoffFor($edition)
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
            [(int) $fieldA->id_player_custom_field, (int) $fieldB->id_player_custom_field],
            $this->cutoffFor($edition)
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
            $fieldIds,
            $this->cutoffFor($edition)
        );

        $this->assertCount(3, $result[(int) $player->id_player]);
        $this->assertSame(
            ['Field 1', 'Field 2', 'Field 3'],
            array_column($result[(int) $player->id_player], 'label')
        );
    }

    public function test_recent_answers_include_this_edition_and_cap_at_two_by_inserted_at(): void
    {
        $edition = Edition::factory()->create(['date' => '2026-06-15', 'name' => 'Current Cup']);
        $pastEdition = Edition::factory()->create(['date' => '2026-01-01', 'name' => 'Past Cup']);
        $olderEdition = Edition::factory()->create(['date' => '2025-01-01', 'name' => 'Older Cup']);
        $player = Player::factory()->create();
        $field = PlayerCustomField::factory()->create([
            'label' => 'Shirt Size',
            'type' => PlayerCustomFieldType::Text,
        ]);

        PlayerCustomFieldValue::factory()->create([
            'player_id' => $player->id_player,
            'player_custom_field_id' => $field->id_player_custom_field,
            'edition_id' => $olderEdition->id_edition,
            'value_text' => 'S',
            'inserted_at' => 1000,
        ]);
        PlayerCustomFieldValue::factory()->create([
            'player_id' => $player->id_player,
            'player_custom_field_id' => $field->id_player_custom_field,
            'edition_id' => $pastEdition->id_edition,
            'value_text' => 'M',
            'inserted_at' => 2000,
        ]);
        PlayerCustomFieldValue::factory()->create([
            'player_id' => $player->id_player,
            'player_custom_field_id' => $field->id_player_custom_field,
            'edition_id' => $edition->id_edition,
            'value_text' => 'L',
            'inserted_at' => 3000,
        ]);

        $result = $this->resolver->resolveForPlayers(
            (int) $edition->id_edition,
            [(int) $player->id_player],
            [(int) $field->id_player_custom_field],
            $this->cutoffFor($edition)
        );

        $recent = $result[(int) $player->id_player][0]['recent_answers'];
        $this->assertCount(2, $recent);
        $this->assertSame('M', $recent[0]['value']);
        $this->assertSame('Past Cup', $recent[0]['edition_name']);
        $this->assertSame('L', $recent[1]['value']);
        $this->assertSame('Current Cup', $recent[1]['edition_name']);
    }

    public function test_recent_answers_exclude_future_edition_and_future_globals(): void
    {
        $edition = Edition::factory()->create(['date' => '2026-06-15', 'name' => 'Current Cup']);
        $futureEdition = Edition::factory()->create(['date' => '2026-07-01', 'name' => 'Future Cup']);
        $player = Player::factory()->create();
        $field = PlayerCustomField::factory()->create(['type' => PlayerCustomFieldType::Text]);

        PlayerCustomFieldValue::factory()->create([
            'player_id' => $player->id_player,
            'player_custom_field_id' => $field->id_player_custom_field,
            'edition_id' => $edition->id_edition,
            'value_text' => 'Now',
            'inserted_at' => 1000,
        ]);
        PlayerCustomFieldValue::factory()->create([
            'player_id' => $player->id_player,
            'player_custom_field_id' => $field->id_player_custom_field,
            'edition_id' => $futureEdition->id_edition,
            'value_text' => 'Future',
            'inserted_at' => 2000,
        ]);
        PlayerCustomFieldValue::factory()->create([
            'player_id' => $player->id_player,
            'player_custom_field_id' => $field->id_player_custom_field,
            'edition_id' => null,
            'value_text' => 'Future Global',
            'inserted_at' => Carbon::parse('2026-06-16', config('app.timezone'))->timestamp,
        ]);

        $result = $this->resolver->resolveForPlayers(
            (int) $edition->id_edition,
            [(int) $player->id_player],
            [(int) $field->id_player_custom_field],
            $this->cutoffFor($edition)
        );

        $recent = $result[(int) $player->id_player][0]['recent_answers'];
        $this->assertCount(1, $recent);
        $this->assertSame('Now', $recent[0]['value']);
    }

    public function test_recent_answers_exclude_invalidated_and_format_global(): void
    {
        $edition = Edition::factory()->create(['date' => '2026-06-15']);
        $player = Player::factory()->create();
        $field = PlayerCustomField::factory()->create(['type' => PlayerCustomFieldType::Text]);
        $globalInserted = Carbon::parse('2026-01-10', config('app.timezone'))->timestamp;

        PlayerCustomFieldValue::factory()->create([
            'player_id' => $player->id_player,
            'player_custom_field_id' => $field->id_player_custom_field,
            'edition_id' => null,
            'value_text' => 'Global',
            'inserted_at' => $globalInserted,
        ]);
        PlayerCustomFieldValue::factory()->create([
            'player_id' => $player->id_player,
            'player_custom_field_id' => $field->id_player_custom_field,
            'edition_id' => $edition->id_edition,
            'value_text' => 'Stale',
            'inserted_at' => $globalInserted + 100,
            'invalidated_at' => time(),
        ]);

        $result = $this->resolver->resolveForPlayers(
            (int) $edition->id_edition,
            [(int) $player->id_player],
            [(int) $field->id_player_custom_field],
            $this->cutoffFor($edition)
        );

        $recent = $result[(int) $player->id_player][0]['recent_answers'];
        $this->assertCount(1, $recent);
        $this->assertNull($recent[0]['edition_name']);
        $this->assertSame('2026-01-10', $recent[0]['inserted_date']);
        $this->assertSame('Global', $recent[0]['value']);
    }

    private function cutoffFor(Edition $edition): string
    {
        if ($edition->date instanceof \DateTimeInterface) {
            return $edition->date->format('Y-m-d');
        }

        return (string) $edition->formatted_date;
    }
}
