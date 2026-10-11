<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\User;
use App\Models\PlayerCustomField;
use App\Models\PlayerCustomFieldSubmission;
use App\Models\PlayerCustomFieldValue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class PlayerCustomFieldSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_seeds_level_assessment_fields(): void
    {
        $codes = PlayerCustomField::query()->orderBy('sort_order')->pluck('code')->all();

        $this->assertSame([
            'racket_experience',
            'big_tournament_division',
            'year_of_birth',
            'playtomic_id',
            'rankedin_id',
            'gender',
            'instagram_username',
            'facebook_username',
        ], $codes);
    }

    public function test_racket_experience_is_a_json_field_with_a_sport_and_level_catalog(): void
    {
        $field = PlayerCustomField::query()->where('code', 'racket_experience')->firstOrFail();

        $this->assertSame('json', $field->type->value);
        $this->assertSame(
            ['padel', 'tennis', 'badminton', 'squash', 'ping-pong', 'pickleball'],
            $field->rules_json['sports']
        );
        $this->assertSame(
            ['amateur', 'intermediate', 'advanced', 'professional'],
            $field->rules_json['levels']
        );
    }

    public function test_big_tournament_division_is_labeled_foreign_id_for_levels_catalog(): void
    {
        $field = PlayerCustomField::query()->where('code', 'big_tournament_division')->firstOrFail();

        $this->assertSame('labeled_foreign_id', $field->type->value);
        $this->assertSame('levels', $field->rules_json['catalog'] ?? null);
    }

    public function test_a_player_may_answer_the_same_field_in_several_submissions(): void
    {
        $player = User::factory()->create();
        $field = PlayerCustomField::factory()->create();

        foreach (['one', 'two'] as $answer) {
            PlayerCustomFieldValue::factory()->create([
                'player_user_id' => $player->id_user,
                'player_custom_field_id' => $field->id_player_custom_field,
                'submission_id' => PlayerCustomFieldSubmission::factory()->create([
                    'player_user_id' => $player->id_user,
                ])->id_player_custom_field_submission,
                'value_text' => $answer,
            ]);
        }

        $this->assertSame(
            2,
            PlayerCustomFieldValue::query()
                ->where('player_user_id', $player->id_user)
                ->where('player_custom_field_id', $field->id_player_custom_field)
                ->count()
        );
    }

    public function test_a_value_cannot_exist_without_a_submission(): void
    {
        $player = User::factory()->create();
        $field = PlayerCustomField::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('player_custom_field_values')->insert([
            'player_user_id' => $player->id_user,
            'player_custom_field_id' => $field->id_player_custom_field,
            'submission_id' => null,
            'value_text' => 'orphan',
            'submitted_via' => PlayerCustomFieldValue::SUBMITTED_VIA_MANAGER,
            'inserted_at' => time(),
        ]);
    }
}
