<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\EventDescriptionSanitizer;
use PHPUnit\Framework\TestCase;

final class EventDescriptionSanitizerTest extends TestCase
{
    public function test_sanitizer_keeps_allowlisted_tags_and_strips_the_rest(): void
    {
        $sanitizer = new EventDescriptionSanitizer();

        $clean = $sanitizer->sanitize(
            '<p>Hi <strong>there</strong></p><ul><li><em>one</em></li></ul>'
            . '<a href="https://padeliz.ro" onclick="alert(1)">site</a>'
            . '<script>alert(1)</script><img src="x">'
        );

        $this->assertStringContainsString('<p>', $clean);
        $this->assertStringContainsString('<strong>there</strong>', $clean);
        $this->assertStringContainsString('<ul>', $clean);
        $this->assertStringContainsString('<li>', $clean);
        $this->assertStringContainsString('<em>one</em>', $clean);
        $this->assertStringContainsString('href="https://padeliz.ro"', $clean);
        $this->assertStringNotContainsString('<script>', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('<img', $clean);
    }

    public function test_is_empty_after_strip_tags(): void
    {
        $sanitizer = new EventDescriptionSanitizer();

        $this->assertTrue($sanitizer->isEmpty('<p></p><br>'));
        $this->assertTrue($sanitizer->isEmpty($sanitizer->sanitize('<script>alert(1)</script>')));
        $this->assertFalse($sanitizer->isEmpty('<p>Hello</p>'));
    }
}
