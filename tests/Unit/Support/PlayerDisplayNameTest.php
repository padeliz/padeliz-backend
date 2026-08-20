<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\PlayerDisplayName;
use PHPUnit\Framework\TestCase;

final class PlayerDisplayNameTest extends TestCase
{
    public function test_normalize_stem_collapses_whitespace(): void
    {
        $this->assertSame('Stefan Popescu', PlayerDisplayName::normalizeStem('  Stefan   Popescu '));
    }

    public function test_from_parts_joins_non_empty_parts(): void
    {
        $this->assertSame(
            'Stefan Ion Popescu',
            PlayerDisplayName::fromParts('Stefan', 'Ion', 'Popescu')
        );
    }

    public function test_from_parts_returns_empty_when_all_blank(): void
    {
        $this->assertSame('', PlayerDisplayName::fromParts(' ', null, ''));
    }
}
