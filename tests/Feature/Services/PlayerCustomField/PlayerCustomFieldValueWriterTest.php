<?php

declare(strict_types=1);

namespace Tests\Feature\Services\PlayerCustomField;

use App\Models\User;
use App\Models\PlayerCustomField;
use App\Models\PlayerCustomFieldSubmission;
use App\Models\PlayerCustomFieldValue;
use App\Services\PlayerCustomField\Formats\GoogleFormMiniTourWelcomeCsvFormat;
use App\Services\PlayerCustomField\PlayerCustomFieldValueWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class PlayerCustomFieldValueWriterTest extends TestCase
{
    use RefreshDatabase;

    private PlayerCustomFieldValueWriter $writer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->writer = app(PlayerCustomFieldValueWriter::class);
    }

    public function test_returns_new_for_the_first_write_in_a_submission(): void
    {
        $player = User::factory()->create();
        $submission = $this->submissionFor($player);
        $field = PlayerCustomField::factory()->create();

        $outcome = $this->writer->write(
            (int) $player->id_user,
            $field,
            (int) $submission->id_player_custom_field_submission,
            ['value' => 'Answer'],
            PlayerCustomFieldValue::SUBMITTED_VIA_MANAGER,
        );

        $this->assertSame('new', $outcome);
    }

    public function test_returns_replaced_when_rewriting_inside_the_same_submission(): void
    {
        $player = User::factory()->create();
        $submission = $this->submissionFor($player);
        $field = PlayerCustomField::factory()->create();

        $this->writer->write(
            (int) $player->id_user,
            $field,
            (int) $submission->id_player_custom_field_submission,
            ['value' => 'First'],
            PlayerCustomFieldValue::SUBMITTED_VIA_MANAGER,
        );

        $outcome = $this->writer->write(
            (int) $player->id_user,
            $field,
            (int) $submission->id_player_custom_field_submission,
            ['value' => 'Second'],
            PlayerCustomFieldValue::SUBMITTED_VIA_MANAGER,
        );

        $this->assertSame('replaced', $outcome);
        $this->assertSame(1, PlayerCustomFieldValue::query()->count());
        $this->assertSame('Second', PlayerCustomFieldValue::query()->first()->value_text);
    }

    public function test_a_second_submission_keeps_the_earlier_value_row(): void
    {
        $player = User::factory()->create();
        $field = PlayerCustomField::factory()->create();

        foreach (['First', 'Second'] as $answer) {
            $this->writer->write(
                (int) $player->id_user,
                $field,
                (int) $this->submissionFor($player)->id_player_custom_field_submission,
                ['value' => $answer],
                PlayerCustomFieldValue::SUBMITTED_VIA_MANAGER,
            );
        }

        $this->assertSame(2, PlayerCustomFieldValue::query()->count());
    }

    public function test_json_field_stores_racket_experience(): void
    {
        $player = User::factory()->create();
        $submission = $this->submissionFor($player);
        $field = PlayerCustomField::query()
            ->where('code', PlayerCustomFieldValueWriter::RACKET_EXPERIENCE_CODE)
            ->firstOrFail();

        $this->writer->write(
            (int) $player->id_user,
            $field,
            (int) $submission->id_player_custom_field_submission,
            ['value' => [['sport' => 'padel', 'level' => 'amateur', 'matches_played' => 12]]],
            PlayerCustomFieldValue::SUBMITTED_VIA_MANAGER,
        );

        $value = PlayerCustomFieldValue::query()->firstOrFail();

        // MySQL normalizes JSON object key order, so compare without caring about it.
        $this->assertEquals(
            [['sport' => 'padel', 'level' => 'amateur', 'matches_played' => 12]],
            $value->value_json
        );
        $this->assertNull($value->value_text);
    }

    public function test_minitour_format_supports_exact_headers(): void
    {
        $format = new GoogleFormMiniTourWelcomeCsvFormat();
        $contents = (string) file_get_contents(base_path('tests/Fixtures/player-custom-field-value-import/google_form_minitour_welcome_sample.csv'));
        $firstLine = strtok($contents, "\n");
        $headers = str_getcsv($firstLine === false ? '' : $firstLine);

        $this->assertTrue($format->supports($headers));
        $this->assertSame('google_form_minitour_welcome', $format->formatKey());
    }

    public function test_archived_field_is_rejected(): void
    {
        $player = User::factory()->create();
        $submission = $this->submissionFor($player);
        $field = PlayerCustomField::factory()->archived()->create();

        $this->expectException(ValidationException::class);

        $this->writer->write(
            (int) $player->id_user,
            $field,
            (int) $submission->id_player_custom_field_submission,
            ['value' => 'Nope'],
            PlayerCustomFieldValue::SUBMITTED_VIA_MANAGER,
        );
    }

    private function submissionFor(User $player): PlayerCustomFieldSubmission
    {
        return PlayerCustomFieldSubmission::factory()->create([
            'player_user_id' => $player->id_user,
        ]);
    }
}
