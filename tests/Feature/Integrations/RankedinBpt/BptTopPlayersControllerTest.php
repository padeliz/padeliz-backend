<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations\RankedinBpt;

use App\Integrations\RankedinBpt\BptCanonicalDivision;
use App\Integrations\RankedinBpt\Contracts\BptTopPlayersReportBuilder;
use Tests\TestCase;

final class BptTopPlayersControllerTest extends TestCase
{
    public function test_top_players_endpoint_invokes_builder_with_clamped_take_and_returns_json(): void
    {
        $spy = new class () implements BptTopPlayersReportBuilder {
            public int $lastTake = 0;

            public bool $lastDebug = false;

            public function build(int $take, bool $debug): array
            {
                $this->lastTake = $take;
                $this->lastDebug = $debug;

                $divisions = [];
                foreach (BptCanonicalDivision::cases() as $case) {
                    $divisions[] = ['divisionName' => $case->value, 'players' => []];
                }

                return [
                    'events' => [],
                    'divisionsRanking' => $divisions,
                ];
            }
        };

        $this->app->instance(BptTopPlayersReportBuilder::class, $spy);

        $response = $this->getJson('/api/external/bpt-rankedin/top-players?take=500&debug=1');

        $response->assertOk();
        $response->assertJsonPath('events', []);
        self::assertSame(100, $spy->lastTake);
        self::assertTrue($spy->lastDebug);
    }

    public function test_top_players_returns_502_when_builder_throws(): void
    {
        $this->app->instance(BptTopPlayersReportBuilder::class, new class () implements BptTopPlayersReportBuilder {
            public function build(int $take, bool $debug): array
            {
                throw new \RuntimeException('RankedIn unavailable');
            }
        });

        $this->getJson('/api/external/bpt-rankedin/top-players')
            ->assertStatus(502)
            ->assertJsonPath('message', 'RankedIn BPT aggregation failed')
            ->assertJsonPath('error', 'RankedIn unavailable');
    }
}
