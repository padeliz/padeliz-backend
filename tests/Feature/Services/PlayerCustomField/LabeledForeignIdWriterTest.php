<?php

declare(strict_types=1);

namespace Tests\Feature\Services\PlayerCustomField;

use App\Enums\PlayerCustomFieldType;
use App\Models\Level;
use App\Models\User;
use App\Models\PlayerCustomField;
use App\Models\PlayerCustomFieldSubmission;
use App\Models\PlayerCustomFieldValue;
use App\Services\PlayerCustomField\PlayerCustomFieldValuePresenter;
use App\Services\PlayerCustomField\PlayerCustomFieldValueWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class LabeledForeignIdWriterTest extends TestCase
{
    use RefreshDatabase;

    private PlayerCustomFieldValueWriter $writer;

    private User $player;

    private PlayerCustomFieldSubmission $submission;

    private PlayerCustomField $field;

    private Level $levelA;

    private Level $levelB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->writer = app(PlayerCustomFieldValueWriter::class);
        $this->player = User::factory()->create();
        $this->submission = PlayerCustomFieldSubmission::factory()->create([
            'player_user_id' => $this->player->id_user,
        ]);
        $this->field = PlayerCustomField::factory()->create([
            'type' => PlayerCustomFieldType::LabeledForeignId,
            'code' => 'labeled_fixture',
            'rules_json' => ['catalog' => 'levels'],
        ]);
        $this->levelA = Level::query()->orderBy('id_level')->firstOrFail();
        $this->levelB = Level::query()->orderBy('id_level')->skip(1)->firstOrFail();
    }

    public function test_id_only_fills_both_columns(): void
    {
        $this->writer->write(
            (int) $this->player->id_user,
            $this->field,
            (int) $this->submission->id_player_custom_field_submission,
            ['value' => (int) $this->levelA->id_level],
            PlayerCustomFieldValue::SUBMITTED_VIA_MANAGER,
        );

        $value = PlayerCustomFieldValue::query()->firstOrFail();
        $this->assertSame((int) $this->levelA->id_level, (int) $value->value_integer);
        $this->assertSame((string) $this->levelA->name, $value->value_text);
        $this->assertSame(
            (string) $this->levelA->name,
            PlayerCustomFieldValuePresenter::resolve($value->load('field'))
        );
    }

    public function test_name_only_fills_both_columns(): void
    {
        $this->writer->write(
            (int) $this->player->id_user,
            $this->field,
            (int) $this->submission->id_player_custom_field_submission,
            ['value' => (string) $this->levelA->name],
            PlayerCustomFieldValue::SUBMITTED_VIA_MANAGER,
        );

        $value = PlayerCustomFieldValue::query()->firstOrFail();
        $this->assertSame((int) $this->levelA->id_level, (int) $value->value_integer);
        $this->assertSame((string) $this->levelA->name, $value->value_text);
    }

    public function test_matching_id_and_label_succeed(): void
    {
        $this->writer->write(
            (int) $this->player->id_user,
            $this->field,
            (int) $this->submission->id_player_custom_field_submission,
            [
                'value' => (int) $this->levelA->id_level,
                'label' => (string) $this->levelA->name,
            ],
            PlayerCustomFieldValue::SUBMITTED_VIA_MANAGER,
        );

        $this->assertSame(1, PlayerCustomFieldValue::query()->count());
    }

    public function test_conflicting_id_and_label_return_422(): void
    {
        $this->expectException(ValidationException::class);

        $this->writer->write(
            (int) $this->player->id_user,
            $this->field,
            (int) $this->submission->id_player_custom_field_submission,
            [
                'value' => (int) $this->levelA->id_level,
                'label' => (string) $this->levelB->name,
            ],
            PlayerCustomFieldValue::SUBMITTED_VIA_MANAGER,
        );
    }

    public function test_unknown_id_and_unknown_name_return_422(): void
    {
        try {
            $this->writer->write(
                (int) $this->player->id_user,
                $this->field,
                (int) $this->submission->id_player_custom_field_submission,
                ['value' => 999999],
                PlayerCustomFieldValue::SUBMITTED_VIA_MANAGER,
            );
            $this->fail('Expected ValidationException for bad id');
        } catch (ValidationException) {
            // expected
        }

        try {
            $this->writer->write(
                (int) $this->player->id_user,
                $this->field,
                (int) $this->submission->id_player_custom_field_submission,
                ['value' => 'Not A Real Level'],
                PlayerCustomFieldValue::SUBMITTED_VIA_MANAGER,
            );
            $this->fail('Expected ValidationException for bad name');
        } catch (ValidationException) {
            // expected
        }
    }

    public function test_empty_answer_is_skipped(): void
    {
        $outcome = $this->writer->write(
            (int) $this->player->id_user,
            $this->field,
            (int) $this->submission->id_player_custom_field_submission,
            ['value' => ''],
            PlayerCustomFieldValue::SUBMITTED_VIA_MANAGER,
        );

        $this->assertSame(PlayerCustomFieldValueWriter::OUTCOME_SKIPPED, $outcome);
        $this->assertSame(0, PlayerCustomFieldValue::query()->count());
    }
}
