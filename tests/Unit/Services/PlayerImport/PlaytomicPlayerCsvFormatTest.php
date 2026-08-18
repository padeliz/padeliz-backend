<?php

declare(strict_types=1);

namespace Tests\Unit\Services\PlayerImport;

use App\Services\PlayerImport\Formats\PlaytomicPlayerCsvFormat;
use PHPUnit\Framework\TestCase;

final class PlaytomicPlayerCsvFormatTest extends TestCase
{
    public function test_supports_required_headers_trim_case_insensitive_with_extras(): void
    {
        $format = new PlaytomicPlayerCsvFormat();

        $this->assertTrue($format->supports([' Name ', 'EMAIL', 'Phone Number', 'Level']));
        $this->assertFalse($format->supports(['Name', 'Email']));
        $this->assertFalse($format->supports(['Full Name', 'Email', 'Phone Number']));
    }

    public function test_parse_row_reads_name_email_phone_and_ignores_extras(): void
    {
        $format = new PlaytomicPlayerCsvFormat();
        $parsed = $format->parseRow([
            'Name' => '  Ada Popescu ',
            'Email' => ' ADA@Example.test ',
            'Phone Number' => ' +40 712 ',
            'Level' => '4.5',
        ]);

        $this->assertSame([
            'name' => 'Ada Popescu',
            'email' => 'ADA@Example.test',
            'phone' => '+40 712',
        ], $parsed);
    }
}
