<?php

declare(strict_types=1);

namespace Tests\Unit\Services\UserImport;

use App\Services\UserImport\Formats\PlaytomicUserCsvFormat;
use PHPUnit\Framework\TestCase;

final class PlaytomicUserCsvFormatTest extends TestCase
{
    public function test_supports_required_headers_trim_case_insensitive_with_extras(): void
    {
        $format = new PlaytomicUserCsvFormat();

        $this->assertTrue($format->supports([' Name ', 'EMAIL', 'Phone Number', 'Level']));
        $this->assertFalse($format->supports(['Name', 'Email']));
        $this->assertFalse($format->supports(['Full Name', 'Email', 'Phone Number']));
    }

    public function test_parse_row_reads_name_email_phone_and_ignores_extras(): void
    {
        $format = new PlaytomicUserCsvFormat();
        $parsed = $format->parseRow([
            'Name' => '  Mara Popescu ',
            'Email' => ' MARA@Example.test ',
            'Phone Number' => ' +40 712 ',
            'Level' => '4.5',
        ]);

        $this->assertSame([
            'name' => 'Mara Popescu',
            'email' => 'MARA@Example.test',
            'phone' => '+40 712',
        ], $parsed);
    }
}
