<?php

declare(strict_types=1);

namespace Tests\Feature\Services\PlayerCustomField;

use App\Enums\PlayerCustomFieldType;
use App\Enums\PlayerCustomFieldValueReviewStatus;
use App\Models\User;
use App\Models\PlayerCustomField;
use App\Models\PlayerCustomFieldOption;
use App\Models\PlayerCustomFieldSubmission;
use App\Models\PlayerCustomFieldValue;
use App\Services\PlayerCustomField\PlayerCustomFieldSubmissionWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class PlayerCustomFieldReviewStatusWriteRulesTest extends TestCase
{
    use RefreshDatabase;

    private PlayerCustomFieldSubmissionWriter $writer;

    private User $player;

    protected function setUp(): void
    {
        parent::setUp();

        $this->writer = app(PlayerCustomFieldSubmissionWriter::class);
        $this->player = User::factory()->create();
    }

    /**
     * @return array<string, array{PlayerCustomFieldType, array<string, mixed>}>
     */
    public static function emptyAnswers(): array
    {
        return [
            'text empty string' => [PlayerCustomFieldType::Text, ['value' => '']],
            'text whitespace' => [PlayerCustomFieldType::Text, ['value' => '   ']],
            'text null' => [PlayerCustomFieldType::Text, ['value' => null]],
            'text missing' => [PlayerCustomFieldType::Text, []],
            'integer empty string' => [PlayerCustomFieldType::Integer, ['value' => '']],
            'boolean null' => [PlayerCustomFieldType::Boolean, ['value' => null]],
            'date empty string' => [PlayerCustomFieldType::Date, ['value' => '']],
            'single select null' => [PlayerCustomFieldType::SingleSelect, ['option_id' => null]],
            'multi select empty list' => [PlayerCustomFieldType::MultiSelect, ['option_ids' => []]],
            'json empty list' => [PlayerCustomFieldType::Json, ['value' => []]],
            'labeled foreign id empty' => [PlayerCustomFieldType::LabeledForeignId, ['value' => '']],
            'labeled foreign id missing' => [PlayerCustomFieldType::LabeledForeignId, []],
        ];
    }

    /**
     * @param  array<string, mixed>  $answer
     */
    #[DataProvider('emptyAnswers')]
    public function test_empty_answer_writes_nothing(PlayerCustomFieldType $type, array $answer): void
    {
        $field = PlayerCustomField::factory()->create(['type' => $type, 'code' => 'empty_' . $type->value]);

        foreach ([PlayerCustomFieldSubmission::SUBMITTED_VIA_APP, PlayerCustomFieldSubmission::SUBMITTED_VIA_CSV] as $via) {
            $submission = PlayerCustomFieldSubmission::factory()->create([
                'player_user_id' => $this->player->id_user,
                'submitted_via' => $via,
            ]);

            $this->assertNull($this->writer->write($submission, $field, $answer, (int) $this->player->id_user));
        }

        $this->assertSame(0, PlayerCustomFieldValue::query()->count());
    }

    public function test_false_and_zero_are_filled_answers(): void
    {
        $boolean = PlayerCustomField::factory()->create(['type' => PlayerCustomFieldType::Boolean]);
        $integer = PlayerCustomField::factory()->create(['type' => PlayerCustomFieldType::Integer]);
        $submission = $this->appSubmission();

        $this->assertNotNull($this->writer->write($submission, $boolean, ['value' => false]));
        $this->assertNotNull($this->writer->write($submission, $integer, ['value' => 0]));

        $this->assertSame(2, PlayerCustomFieldValue::query()->count());
    }

    public function test_empty_answer_never_clears_or_deletes_an_existing_row(): void
    {
        $field = PlayerCustomField::factory()->create();
        $submission = $this->appSubmission();

        $first = $this->writer->write($submission, $field, ['value' => 'Keep me']);
        $this->assertNull($this->writer->write($submission, $field, ['value' => '']));

        $this->assertSame(1, PlayerCustomFieldValue::query()->count());
        $this->assertSame('Keep me', $first->fresh()->value_text);
    }

    public function test_same_submission_first_write_inserts_and_next_edit_updates_in_place(): void
    {
        $field = PlayerCustomField::factory()->create();
        $submission = $this->appSubmission();

        $first = $this->writer->write($submission, $field, ['value' => 'One'], (int) $this->player->id_user);
        PlayerCustomFieldValue::query()->whereKey($first->getKey())->update(['inserted_at' => 1_600_000_000]);

        $second = $this->writer->write($submission, $field, ['value' => 'Two'], (int) $this->player->id_user);

        $this->assertSame(1, PlayerCustomFieldValue::query()->count());
        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertSame(1_600_000_000, (int) $second->fresh()->inserted_at);
        $this->assertSame('Two', $second->fresh()->value_text);
    }

    public function test_in_place_edit_leaves_review_fields_untouched(): void
    {
        $field = PlayerCustomField::factory()->create();
        $manager = User::factory()->manager()->create();
        $submission = $this->appSubmission();

        $value = PlayerCustomFieldValue::factory()->create([
            'player_user_id' => $this->player->id_user,
            'player_custom_field_id' => $field->id_player_custom_field,
            'submission_id' => $submission->id_player_custom_field_submission,
            'submitted_via' => PlayerCustomFieldValue::SUBMITTED_VIA_APP,
            'review_status' => PlayerCustomFieldValueReviewStatus::Rejected,
            'reviewed_at' => 1_650_000_000,
            'reviewed_by_manager_user_id' => $manager->id_user,
        ]);

        $this->writer->write($submission, $field, ['value' => 'Edited']);

        $fresh = $value->fresh();
        $this->assertSame('Edited', $fresh->value_text);
        $this->assertSame(PlayerCustomFieldValueReviewStatus::Rejected, $fresh->review_status);
        $this->assertSame(1_650_000_000, (int) $fresh->reviewed_at);
        $this->assertSame((int) $manager->id_user, (int) $fresh->reviewed_by_manager_user_id);
        $this->assertSame(1, PlayerCustomFieldValue::query()->count());
    }

    public function test_pending_row_stays_pending_when_edited_in_place(): void
    {
        $field = PlayerCustomField::factory()->create();
        $submission = $this->appSubmission();

        $this->writer->write($submission, $field, ['value' => 'One']);
        $value = $this->writer->write($submission, $field, ['value' => 'Two']);

        $this->assertSame(PlayerCustomFieldValueReviewStatus::Pending, $value->fresh()->review_status);
        $this->assertNull($value->fresh()->reviewed_at);
    }

    public function test_same_submission_new_field_inserts_a_row_without_touching_the_others(): void
    {
        $fieldA = PlayerCustomField::factory()->create();
        $fieldB = PlayerCustomField::factory()->create();
        $submission = $this->appSubmission();

        $a = $this->writer->write($submission, $fieldA, ['value' => 'A']);
        $b = $this->writer->write($submission, $fieldB, ['value' => 'B']);

        $this->assertNotSame($a->getKey(), $b->getKey());
        $this->assertSame(2, PlayerCustomFieldValue::query()->where('submission_id', $submission->getKey())->count());
        $this->assertSame('A', $a->fresh()->value_text);
    }

    public function test_multi_select_in_place_edit_replaces_the_options(): void
    {
        $field = PlayerCustomField::factory()->multiSelect()->create();
        $one = PlayerCustomFieldOption::factory()->create(['player_custom_field_id' => $field->id_player_custom_field, 'label' => 'One']);
        $two = PlayerCustomFieldOption::factory()->create(['player_custom_field_id' => $field->id_player_custom_field, 'label' => 'Two']);
        $submission = $this->appSubmission();

        $first = $this->writer->write($submission, $field, ['option_ids' => [$one->id_player_custom_field_option]]);
        $second = $this->writer->write($submission, $field, ['option_ids' => [$two->id_player_custom_field_option]]);

        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertSame(
            ['Two'],
            $second->selectedOptions()->pluck('option_label_snapshot')->all()
        );
    }

    public function test_new_app_submission_with_the_same_answer_inserts_a_new_pending_row(): void
    {
        $field = PlayerCustomField::factory()->create();

        $validated = PlayerCustomFieldValue::factory()->create([
            'player_user_id' => $this->player->id_user,
            'player_custom_field_id' => $field->id_player_custom_field,
            'value_text' => 'Same',
        ]);

        $written = $this->writer->write($this->appSubmission(), $field, ['value' => 'Same'], (int) $this->player->id_user);

        $this->assertSame(2, PlayerCustomFieldValue::query()->count());
        $this->assertNotSame($validated->getKey(), $written->getKey());
        $this->assertSame('Same', $written->value_text);
        $this->assertSame(PlayerCustomFieldValueReviewStatus::Pending, $written->fresh()->review_status);
        $this->assertNull($written->fresh()->reviewed_at);
        $this->assertNull($written->fresh()->reviewed_by_manager_user_id);
        $this->assertSame(PlayerCustomFieldValueReviewStatus::Validated, $validated->fresh()->review_status);
    }

    public function test_new_app_submission_with_an_empty_answer_inserts_no_row(): void
    {
        $field = PlayerCustomField::factory()->create();
        PlayerCustomFieldValue::factory()->create([
            'player_user_id' => $this->player->id_user,
            'player_custom_field_id' => $field->id_player_custom_field,
        ]);

        $this->assertNull($this->writer->write($this->appSubmission(), $field, ['value' => '']));

        $this->assertSame(1, PlayerCustomFieldValue::query()->count());
    }

    public function test_new_app_submission_never_changes_the_previous_value(): void
    {
        $field = PlayerCustomField::factory()->create();
        $previous = PlayerCustomFieldValue::factory()->create([
            'player_user_id' => $this->player->id_user,
            'player_custom_field_id' => $field->id_player_custom_field,
            'value_text' => 'Before',
        ]);

        $this->writer->write($this->appSubmission(), $field, ['value' => 'After']);

        $this->assertSame(PlayerCustomFieldValueReviewStatus::Validated, $previous->fresh()->review_status);
        $this->assertSame('Before', $previous->fresh()->value_text);
        $this->assertSame(
            $previous->getKey(),
            $this->writer->currentValue((int) $this->player->id_user, (int) $field->id_player_custom_field)?->getKey()
        );
    }

    public function test_manager_write_is_validated_with_the_manager(): void
    {
        $field = PlayerCustomField::factory()->create();
        $manager = User::factory()->manager()->create();
        $submission = PlayerCustomFieldSubmission::factory()->viaManager((int) $manager->id_user)->create([
            'player_user_id' => $this->player->id_user,
        ]);

        $value = $this->writer->write($submission, $field, ['value' => 'Mgr'], (int) $manager->id_user)->fresh();

        $this->assertSame(PlayerCustomFieldValueReviewStatus::Validated, $value->review_status);
        $this->assertNotNull($value->reviewed_at);
        $this->assertSame((int) $manager->id_user, (int) $value->reviewed_by_manager_user_id);
    }

    public function test_csv_write_is_validated_with_a_null_manager(): void
    {
        $field = PlayerCustomField::factory()->create();
        $manager = User::factory()->manager()->create();
        $submission = PlayerCustomFieldSubmission::factory()->viaCsv()->create([
            'player_user_id' => $this->player->id_user,
            'submitted_by_player_user_id' => $manager->id_user,
        ]);

        $value = $this->writer->write($submission, $field, ['value' => 'Csv'], (int) $manager->id_user)->fresh();

        $this->assertSame(PlayerCustomFieldValueReviewStatus::Validated, $value->review_status);
        $this->assertNotNull($value->reviewed_at);
        $this->assertNull($value->reviewed_by_manager_user_id);
    }

    public function test_current_value_is_the_latest_validated_only(): void
    {
        $field = PlayerCustomField::factory()->create();
        $make = fn (string $text, PlayerCustomFieldValueReviewStatus $status, ?int $reviewedAt) => PlayerCustomFieldValue::factory()->create([
            'player_user_id' => $this->player->id_user,
            'player_custom_field_id' => $field->id_player_custom_field,
            'value_text' => $text,
            'review_status' => $status,
            'reviewed_at' => $reviewedAt,
        ]);

        $this->assertNull($this->writer->currentValue((int) $this->player->id_user, (int) $field->id_player_custom_field));

        $make('Old validated', PlayerCustomFieldValueReviewStatus::Validated, 1_600_000_000);
        $make('Newer pending', PlayerCustomFieldValueReviewStatus::Pending, null);
        $make('Newer rejected', PlayerCustomFieldValueReviewStatus::Rejected, 1_700_000_000);
        $make('Newer discarded', PlayerCustomFieldValueReviewStatus::Discarded, 1_700_000_001);

        $this->assertSame(
            'Old validated',
            $this->writer->currentValue((int) $this->player->id_user, (int) $field->id_player_custom_field)->value_text
        );

        $make('Latest validated', PlayerCustomFieldValueReviewStatus::Validated, 1_650_000_000);

        $this->assertSame(
            'Latest validated',
            $this->writer->currentValue((int) $this->player->id_user, (int) $field->id_player_custom_field)->value_text
        );
    }

    public function test_equal_reviewed_at_is_broken_by_the_higher_id(): void
    {
        $field = PlayerCustomField::factory()->create();
        foreach (['First', 'Second'] as $text) {
            PlayerCustomFieldValue::factory()->create([
                'player_user_id' => $this->player->id_user,
                'player_custom_field_id' => $field->id_player_custom_field,
                'value_text' => $text,
                'reviewed_at' => 1_600_000_000,
            ]);
        }

        $this->assertSame(
            'Second',
            $this->writer->currentValue((int) $this->player->id_user, (int) $field->id_player_custom_field)->value_text
        );
    }

    private function appSubmission(): PlayerCustomFieldSubmission
    {
        return PlayerCustomFieldSubmission::factory()->create([
            'player_user_id' => $this->player->id_user,
            'submitted_via' => PlayerCustomFieldSubmission::SUBMITTED_VIA_APP,
        ]);
    }
}
