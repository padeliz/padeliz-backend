<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\PlayerPhoneNormalizer;
use PHPUnit\Framework\TestCase;

final class PlayerPhoneNormalizerTest extends TestCase
{
    public function test_strips_spaces_and_dashes_and_keeps_plus(): void
    {
        $this->assertSame('+40712345678', PlayerPhoneNormalizer::normalize('+40 712-345-678'));
    }

    public function test_drops_trunk_zero_after_country_code_when_separated(): void
    {
        $this->assertSame('+40712345678', PlayerPhoneNormalizer::normalize('+40 0712 345678'));
    }

    public function test_does_not_drop_zero_in_compact_international_form(): void
    {
        $this->assertSame('+40712345678', PlayerPhoneNormalizer::normalize('+40712345678'));
    }

    public function test_does_not_default_country_code(): void
    {
        $this->assertSame('+0712345678', PlayerPhoneNormalizer::normalize('0712 345678'));
        $this->assertNotSame('+40712345678', PlayerPhoneNormalizer::normalize('0712 345678'));
    }

    public function test_empty_and_null(): void
    {
        $this->assertSame('', PlayerPhoneNormalizer::normalize(null));
        $this->assertSame('', PlayerPhoneNormalizer::normalize('   '));
    }
}
