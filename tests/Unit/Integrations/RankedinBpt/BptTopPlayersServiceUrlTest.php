<?php

declare(strict_types=1);

namespace Tests\Unit\Integrations\RankedinBpt;

use App\Integrations\RankedinBpt\BptTopPlayersService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BptTopPlayersServiceUrlTest extends TestCase
{
    #[DataProvider('playerKeyFromUrlProvider')]
    public function test_player_key_from_url(?string $url, ?string $expected): void
    {
        self::assertSame($expected, BptTopPlayersService::playerKeyFromUrl($url));
    }

    /**
     * @return iterable<string, array{0: ?string, 1: ?string}>
     */
    public static function playerKeyFromUrlProvider(): iterable
    {
        yield 'null' => [null, null];
        yield 'empty' => ['', null];
        yield 'r slug' => ['https://rankedin.com/player/R-john-doe/', 'R-john-doe'];
        yield 'f slug' => ['https://rankedin.com/player/F-jane/', 'F-jane'];
        yield 'no match' => ['https://rankedin.com/club/x/', null];
    }

    public function test_rankedin_id_from_player_url_alias(): void
    {
        self::assertSame('R-x', BptTopPlayersService::rankedinIdFromPlayerUrl('https://x/player/R-x/'));
    }
}
