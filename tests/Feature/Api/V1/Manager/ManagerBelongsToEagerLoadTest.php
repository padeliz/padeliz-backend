<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Court;
use App\Models\Division;
use App\Models\EventDivision;
use App\Models\EventLottery;
use App\Models\EventLotteryLucky;
use App\Models\EventLotteryPrize;
use App\Models\EventLotteryRule;
use App\Models\EventParticipation;
use App\Models\Location;
use App\Models\PadelEvent;
use App\Models\User;
use App\Models\PlayerLevelAssessment;
use App\Models\PlayerStudentProof;
use App\Models\Prize;
use App\Models\Sponsor;
use App\Models\SponsorPrize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        Sanctum::actingAs(User::factory()->manager()->create([
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

    public function test_manager_division_index_includes_its_first_event_nest(): void
    {
        Sanctum::actingAs(User::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]));

        $division = Division::factory()->create();

        $response = $this->getJson('/api/v1/manager/divisions');

        $response->assertOk();

        $row = collect($response->json('data'))
            ->firstWhere('id_division', $division->id_division);

        $this->assertIsArray($row);
        $this->assertArrayHasKey('its_first_event', $row);
        $this->assertIsArray($row['its_first_event']);
        $this->assertSame(
            $division->its_first_event_id,
            $row['its_first_event']['id_event'] ?? null
        );
    }

    public function test_manager_edition_lottery_show_nests_edition_and_lottery_prizes_without_luckies(): void
    {
        Sanctum::actingAs(User::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]));

        $lottery = EventLottery::factory()->create([
            'name' => 'Eager Lottery',
        ]);
        $lotteryPrize = EventLotteryPrize::factory()->create([
            'lottery_id' => $lottery->id_lottery,
            'prize_id' => Prize::factory()->create()->id_prize,
        ]);
        $rule = EventLotteryRule::factory()->create([
            'lottery_prize_id' => $lotteryPrize->id_lottery_prize,
        ]);
        EventLotteryLucky::factory()->create([
            'lottery_rule_id' => $rule->id_lottery_rule,
        ]);

        $response = $this->getJson("/api/v1/manager/event-lotteries/{$lottery->id_lottery}");

        $response->assertOk();
        $response->assertJsonPath('data.event.id_event', $lottery->event_id);
        $this->assertArrayHasKey('lottery_prizes', $response->json('data'));
        $this->assertNotEmpty($response->json('data.lottery_prizes'));
        $this->assertArrayNotHasKey('prize', $response->json('data'));
        $this->assertArrayNotHasKey('luckies', $response->json('data'));
    }

    /**
     * @return array<string, array{0: string, 1: list<string>}>
     */
    private function managerBelongsToShowCases(): array
    {
        $court = Court::factory()->create();
        $division = Division::factory()->create();
        $event = PadelEvent::factory()->create();
        $eventDivision = EventDivision::factory()->create();
        $lottery = EventLottery::factory()->create([
            'name' => 'Show Lottery',
        ]);
        EventLotteryPrize::factory()->create([
            'lottery_id' => $lottery->id_lottery,
            'prize_id' => Prize::factory()->create()->id_prize,
        ]);
        $lucky = EventLotteryLucky::factory()->create();
        $rule = EventLotteryRule::factory()->create([
            'event_division_id' => EventDivision::factory()->create()->id_event_division,
        ]);
        $participation = EventParticipation::factory()->create();
        $assessment = PlayerLevelAssessment::factory()->readyForReview()->create();
        $studentProof = PlayerStudentProof::factory()->create();
        $location = Location::factory()->create();
        $sponsor = Sponsor::factory()->forClub(\App\Models\Club::factory()->create())->create();
        $player = User::factory()->create();
        $now = time();
        DB::table('sponsor_players')->insert([
            'sponsor_id' => $sponsor->id_sponsor,
            'player_user_id' => $player->id_user,
            'inserted_at' => $now,
            'updated_at' => $now,
        ]);
        $sponsorPrize = SponsorPrize::factory()->create([
            'sponsor_id' => $sponsor->id_sponsor,
            'prize_id' => Prize::factory()->create()->id_prize,
        ]);

        return [
            'courts.location' => [
                "/api/v1/manager/courts/{$court->id_court}",
                ['location'],
            ],
            'divisions.its_first_event' => [
                "/api/v1/manager/divisions/{$division->id_division}",
                ['its_first_event'],
            ],
            'events.location' => [
                "/api/v1/manager/events/{$event->id_event}",
                ['location'],
            ],
            'event-divisions.edition_and_division' => [
                "/api/v1/manager/event-divisions/{$eventDivision->id_event_division}",
                ['event', 'division'],
            ],
            'event-lotteries.edition_and_lottery_prizes' => [
                "/api/v1/manager/event-lotteries/{$lottery->id_lottery}",
                ['event', 'lottery_prizes'],
            ],
            'event-lottery-luckies.lottery_rule' => [
                "/api/v1/manager/event-lottery-luckies/{$lucky->id_lucky}",
                ['lottery_rule'],
            ],
            'event-lottery-rules.edition_division_and_lottery_prize' => [
                "/api/v1/manager/event-lottery-rules/{$rule->id_lottery_rule}",
                ['event_division', 'lottery_prize'],
            ],
            'event-participations.event_player_division' => [
                "/api/v1/manager/event-participations/{$participation->id_participation}",
                ['event', 'player', 'event_division'],
            ],
            'player-level-assessments.player' => [
                "/api/v1/manager/player-level-assessments/{$assessment->id_player_level_assessment}",
                ['player'],
            ],
            'player-student-proofs.player' => [
                "/api/v1/manager/player-student-proofs/{$studentProof->id_player_student_proof}",
                ['player'],
            ],
            'locations.club' => [
                "/api/v1/manager/locations/{$location->id_location}",
                ['club'],
            ],
            'sponsors.club_and_players' => [
                "/api/v1/manager/sponsors/{$sponsor->id_sponsor}",
                ['club', 'players'],
            ],
            'sponsor-prizes.sponsor_and_prize' => [
                "/api/v1/manager/sponsor-prizes/{$sponsorPrize->id_sponsor_prize}",
                ['sponsor', 'prize'],
            ],
        ];
    }
}
