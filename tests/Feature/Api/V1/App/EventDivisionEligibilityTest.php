<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\Division;
use App\Models\EventDivision;
use App\Models\PadelEvent;
use App\Models\Player;
use App\Models\PlayerLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class EventDivisionEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private PadelEvent $event;

    protected function setUp(): void
    {
        parent::setUp();

        config(['responsecache.enabled' => false]);
        $this->event = PadelEvent::factory()->create([
            'date' => now()->addDays(30)->format('Y-m-d'),
        ]);
    }

    private function division(?string $levelMin, ?string $levelMax, string $title): EventDivision
    {
        $division = Division::factory()->create();

        return EventDivision::factory()->create([
            'event_id' => $this->event->id_event,
            'division_id' => $division->id_division,
            'division_title' => $title,
            'level_min' => $levelMin,
            'level_max' => $levelMax,
        ]);
    }

    /**
     * @return array<string, mixed>  is_eligible keyed by division title
     */
    private function eligibilityByTitle(): array
    {
        $response = $this->getJson('/api/v1/app/events/' . $this->event->id_event)->assertOk();

        return collect($response->json('data.event_divisions'))
            ->mapWithKeys(fn (array $row): array => [$row['division_title'] => array_key_exists('is_eligible', $row) ? $row['is_eligible'] : 'missing'])
            ->all();
    }

    public function test_guests_get_null_eligibility(): void
    {
        $this->division('4.99', '4.00', 'bounded');

        $this->assertSame(['bounded' => null], $this->eligibilityByTitle());
    }

    public function test_player_without_any_level_is_never_eligible(): void
    {
        Sanctum::actingAs(Player::factory()->playerOnly()->create());
        $this->division(null, null, 'open');
        $this->division('4.99', '4.00', 'bounded');

        $this->assertSame(['open' => false, 'bounded' => false], $this->eligibilityByTitle());
    }

    public function test_eligibility_follows_the_inverted_level_bounds(): void
    {
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);
        PlayerLevel::factory()->level('4.50')->create(['player_id' => $player->id_player]);

        $this->division('4.99', '4.00', 'inside');
        $this->division('3.99', '3.00', 'too-strong-division');
        $this->division('5.99', '5.00', 'too-weak-division');
        $this->division('4.50', '4.50', 'exact-bounds');

        $this->assertSame([
            'inside' => true,
            'too-strong-division' => false,
            'too-weak-division' => false,
            'exact-bounds' => true,
        ], $this->eligibilityByTitle());
    }

    public function test_unconstrained_bounds_only_need_some_level(): void
    {
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);
        PlayerLevel::factory()->level('1.50')->create(['player_id' => $player->id_player]);

        $this->division(null, null, 'open');
        $this->division(null, '4.00', 'only-max');
        $this->division('4.99', null, 'only-min');

        $this->assertSame([
            'open' => true,
            'only-max' => true,
            'only-min' => true,
        ], $this->eligibilityByTitle());
    }

    public function test_the_latest_player_level_is_the_one_used(): void
    {
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);
        PlayerLevel::factory()->level('1.50')->create([
            'player_id' => $player->id_player,
            'inserted_at' => time() - 1000,
        ]);
        PlayerLevel::factory()->level('4.50')->create([
            'player_id' => $player->id_player,
            'inserted_at' => time(),
        ]);

        $this->division('4.99', '4.00', 'bounded');

        $this->assertSame(['bounded' => true], $this->eligibilityByTitle());
    }
}
