<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\PadelEvent;
use App\Models\Player;
use App\Models\PlayerCustomField;
use App\Models\PlayerCustomFieldValue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PlayerCustomFieldSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_seeds_minitour_fields(): void
    {
        $codes = PlayerCustomField::query()->orderBy('sort_order')->pluck('code')->all();

        $this->assertSame([
            'padel_matches_played',
            'tennis_experience',
            'racket_lessons',
            'big_tournament_division',
            'age',
            'playtomic_url',
        ], $codes);

        foreach (PlayerCustomField::query()->get() as $field) {
            $this->assertSame('text', $field->type->value);
            $this->assertSame(2000, $field->rules_json['max_length'] ?? null);
        }
    }

    public function test_unique_allows_multiple_null_edition_rows(): void
    {
        $player = Player::factory()->create();
        $field = PlayerCustomField::factory()->create();

        PlayerCustomFieldValue::factory()->create([
            'player_id' => $player->id_player,
            'player_custom_field_id' => $field->id_player_custom_field,
            'event_id' => null,
            'value_text' => 'one',
        ]);
        PlayerCustomFieldValue::factory()->create([
            'player_id' => $player->id_player,
            'player_custom_field_id' => $field->id_player_custom_field,
            'event_id' => null,
            'value_text' => 'two',
        ]);

        $this->assertSame(
            2,
            PlayerCustomFieldValue::query()
                ->where('player_id', $player->id_player)
                ->where('player_custom_field_id', $field->id_player_custom_field)
                ->whereNull('event_id')
                ->count()
        );
    }

    public function test_unique_rejects_duplicate_player_field_edition(): void
    {
        $player = Player::factory()->create();
        $field = PlayerCustomField::factory()->create();
        $event = PadelEvent::factory()->create();

        PlayerCustomFieldValue::factory()->create([
            'player_id' => $player->id_player,
            'player_custom_field_id' => $field->id_player_custom_field,
            'event_id' => $event->id_event,
            'value_text' => 'one',
        ]);

        $this->expectException(QueryException::class);

        PlayerCustomFieldValue::factory()->create([
            'player_id' => $player->id_player,
            'player_custom_field_id' => $field->id_player_custom_field,
            'event_id' => $event->id_event,
            'value_text' => 'two',
        ]);
    }
}
