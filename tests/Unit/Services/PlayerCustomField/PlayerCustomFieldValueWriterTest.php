<?php

declare(strict_types=1);

namespace Tests\Unit\Services\PlayerCustomField;

use App\Models\Edition;
use App\Models\Player;
use App\Models\PlayerCustomField;
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

    public function test_returns_new_for_first_edition_scoped_write(): void
    {
        $player = Player::factory()->create();
        $edition = Edition::factory()->create();
        $field = PlayerCustomField::factory()->create();

        $outcome = $this->writer->write(
            (int) $player->id_player,
            $field,
            (int) $edition->id_edition,
            ['value' => 'Answer'],
            PlayerCustomFieldValue::SUBMITTED_VIA_MANAGER,
        );

        $this->assertSame('new', $outcome);
    }

    public function test_returns_replaced_for_second_edition_scoped_write(): void
    {
        $player = Player::factory()->create();
        $edition = Edition::factory()->create();
        $field = PlayerCustomField::factory()->create();

        $this->writer->write(
            (int) $player->id_player,
            $field,
            (int) $edition->id_edition,
            ['value' => 'First'],
            PlayerCustomFieldValue::SUBMITTED_VIA_MANAGER,
        );

        $outcome = $this->writer->write(
            (int) $player->id_player,
            $field,
            (int) $edition->id_edition,
            ['value' => 'Second'],
            PlayerCustomFieldValue::SUBMITTED_VIA_MANAGER,
        );

        $this->assertSame('replaced', $outcome);
        $this->assertSame(1, PlayerCustomFieldValue::query()->count());
        $this->assertSame('Second', PlayerCustomFieldValue::query()->first()->value_text);
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
        $player = Player::factory()->create();
        $field = PlayerCustomField::factory()->archived()->create();

        $this->expectException(ValidationException::class);

        $this->writer->write(
            (int) $player->id_player,
            $field,
            null,
            ['value' => 'Nope'],
            PlayerCustomFieldValue::SUBMITTED_VIA_MANAGER,
        );
    }
}
