<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Court;
use App\Models\Division;
use App\Models\Edition;
use App\Models\EditionDivision;
use App\Models\EditionLottery;
use App\Models\EditionLotteryLucky;
use App\Models\EditionLotteryRule;
use App\Models\EditionParticipation;
use App\Models\Eligibility;
use App\Models\Location;
use App\Models\Player;
use App\Models\Prize;
use App\Models\PrizeFromClub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Convention: Manager show responses must include BelongsTo nests when FKs are non-null.
 *
 * Covers Resource whenLoaded BelongsTo keys for standard Manager CRUD show endpoints.
 */
class ManagerBelongsToEagerLoadTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_show_includes_belongs_to_nests_when_fk_present(): void
    {
        Sanctum::actingAs(Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]));

        foreach ($this->managerBelongsToShowCases() as $label => [$uri, $nestedKeys]) {
            $response = $this->getJson($uri);

            $response->assertOk();
            $response->assertJsonPath('success', true);

            $data = $response->json('data');
            $this->assertIsArray($data, "Expected data array for {$label}");

            foreach ($nestedKeys as $nestedKey) {
                $this->assertArrayHasKey(
                    $nestedKey,
                    $data,
                    "Expected BelongsTo nest \"{$nestedKey}\" on {$label} ({$uri})"
                );
                $this->assertIsArray(
                    $data[$nestedKey],
                    "Expected BelongsTo nest \"{$nestedKey}\" to be an object on {$label}"
                );
                $this->assertNotEmpty(
                    $data[$nestedKey],
                    "Expected BelongsTo nest \"{$nestedKey}\" to be non-empty on {$label}"
                );
            }
        }
    }

    public function test_manager_division_index_includes_its_first_edition_nest(): void
    {
        Sanctum::actingAs(Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]));

        $division = Division::factory()->create();

        $response = $this->getJson('/api/v1/manager/divisions');

        $response->assertOk();

        $row = collect($response->json('data'))
            ->firstWhere('id_division', $division->id_division);

        $this->assertIsArray($row);
        $this->assertArrayHasKey('its_first_edition', $row);
        $this->assertIsArray($row['its_first_edition']);
        $this->assertSame(
            $division->its_first_edition_id,
            $row['its_first_edition']['id_edition'] ?? null
        );
    }

    public function test_manager_edition_lottery_show_does_not_dump_luckies(): void
    {
        Sanctum::actingAs(Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]));

        $lottery = EditionLottery::factory()->create([
            'prize_id' => Prize::factory()->create()->id_prize,
        ]);
        $rule = EditionLotteryRule::factory()->create([
            'lottery_id' => $lottery->id_lottery,
        ]);
        EditionLotteryLucky::factory()->create([
            'lottery_rule_id' => $rule->id_lottery_rule,
        ]);

        $response = $this->getJson("/api/v1/manager/edition-lotteries/{$lottery->id_lottery}");

        $response->assertOk();
        $response->assertJsonPath('data.edition.id_edition', $lottery->edition_id);
        $this->assertArrayNotHasKey('luckies', $response->json('data'));
    }

    /**
     * @return array<string, array{0: string, 1: list<string>}>
     */
    private function managerBelongsToShowCases(): array
    {
        $court = Court::factory()->create();
        $division = Division::factory()->create();
        $edition = Edition::factory()->create();
        $editionDivision = EditionDivision::factory()->create();
        $lottery = EditionLottery::factory()->create([
            'prize_id' => Prize::factory()->create()->id_prize,
        ]);
        $lucky = EditionLotteryLucky::factory()->create();
        $rule = EditionLotteryRule::factory()->create([
            'edition_division_id' => EditionDivision::factory()->create()->id_edition_division,
        ]);
        $participation = EditionParticipation::factory()->create();
        $eligibility = Eligibility::factory()->create();
        $location = Location::factory()->create();
        $prizeFromClub = PrizeFromClub::factory()->create();

        return [
            'courts.location' => [
                "/api/v1/manager/courts/{$court->id_court}",
                ['location'],
            ],
            'divisions.its_first_edition' => [
                "/api/v1/manager/divisions/{$division->id_division}",
                ['its_first_edition'],
            ],
            'editions.location' => [
                "/api/v1/manager/editions/{$edition->id_edition}",
                ['location'],
            ],
            'edition-divisions.edition_and_division' => [
                "/api/v1/manager/edition-divisions/{$editionDivision->id_edition_division}",
                ['edition', 'division'],
            ],
            'edition-lotteries.edition_and_prize' => [
                "/api/v1/manager/edition-lotteries/{$lottery->id_lottery}",
                ['edition', 'prize'],
            ],
            'edition-lottery-luckies.lottery_rule' => [
                "/api/v1/manager/edition-lottery-luckies/{$lucky->id_lucky}",
                ['lottery_rule'],
            ],
            'edition-lottery-rules.edition_division' => [
                "/api/v1/manager/edition-lottery-rules/{$rule->id_lottery_rule}",
                ['edition_division'],
            ],
            'edition-participations.edition_player_division' => [
                "/api/v1/manager/edition-participations/{$participation->id_participation}",
                ['edition', 'player', 'edition_division'],
            ],
            'eligibilities.player' => [
                "/api/v1/manager/eligibilities/{$eligibility->id_eligibility}",
                ['player'],
            ],
            'locations.club' => [
                "/api/v1/manager/locations/{$location->id_location}",
                ['club'],
            ],
            'prize-from-clubs.club_and_prize' => [
                "/api/v1/manager/prize-from-clubs/{$prizeFromClub->id_club_prize}",
                ['club', 'prize'],
            ],
        ];
    }
}
