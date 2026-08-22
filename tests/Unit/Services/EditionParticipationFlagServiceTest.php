<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Edition;
use App\Models\EditionDivision;
use App\Models\EditionLotteryLucky;
use App\Models\EditionParticipation;
use App\Models\EditionRegistration;
use App\Models\Player;
use App\Services\EditionParticipationFlagService;
use App\Services\EditionParticipationLotteryGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EditionParticipationFlagServiceTest extends TestCase
{
    use RefreshDatabase;

    private EditionParticipationFlagService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EditionParticipationFlagService(new EditionParticipationLotteryGuard());
    }

    public function test_duplicate_and_not_registered_flags(): void
    {
        $edition = Edition::factory()->create();
        $edA = EditionDivision::factory()->create(['edition_id' => $edition->id_edition]);
        $edB = EditionDivision::factory()->create(['edition_id' => $edition->id_edition]);
        $registered = Player::factory()->create();
        $unregistered = Player::factory()->create();

        EditionRegistration::factory()->withoutEditionDivision()->create([
            'edition_id' => $edition->id_edition,
            'player_id' => $registered->id_player,
            'is_student' => false,
            'payment' => 50,
        ]);

        $p1 = EditionParticipation::factory()->create([
            'edition_id' => $edition->id_edition,
            'edition_division_id' => $edA->id_edition_division,
            'player_id' => $registered->id_player,
        ]);
        $p2 = EditionParticipation::factory()->create([
            'edition_id' => $edition->id_edition,
            'edition_division_id' => $edB->id_edition_division,
            'player_id' => $registered->id_player,
        ]);
        $p3 = EditionParticipation::factory()->create([
            'edition_id' => $edition->id_edition,
            'edition_division_id' => $edA->id_edition_division,
            'player_id' => $unregistered->id_player,
        ]);

        $payload = $this->service->forEditionParticipations(
            (int) $edition->id_edition,
            collect([$p1, $p2, $p3])
        );

        $this->assertSame(['duplicate'], $payload['flags_by_participation_id'][(int) $p1->id_participation]);
        $this->assertSame(['duplicate'], $payload['flags_by_participation_id'][(int) $p2->id_participation]);
        $this->assertSame(['not_registered'], $payload['flags_by_participation_id'][(int) $p3->id_participation]);
    }

    public function test_registered_not_participating_count_and_participations_locked(): void
    {
        $edition = Edition::factory()->create();
        $ed = EditionDivision::factory()->create(['edition_id' => $edition->id_edition]);
        $registeredOnly = Player::factory()->create();
        $participating = Player::factory()->create();

        EditionRegistration::factory()->withoutEditionDivision()->create([
            'edition_id' => $edition->id_edition,
            'player_id' => $registeredOnly->id_player,
            'is_student' => false,
            'payment' => 50,
        ]);
        EditionRegistration::factory()->withoutEditionDivision()->create([
            'edition_id' => $edition->id_edition,
            'player_id' => $participating->id_player,
            'is_student' => false,
            'payment' => 50,
        ]);

        $participation = EditionParticipation::factory()->create([
            'edition_id' => $edition->id_edition,
            'edition_division_id' => $ed->id_edition_division,
            'player_id' => $participating->id_player,
        ]);

        EditionLotteryLucky::factory()->create([
            'drawn_participation_id' => $participation->id_participation,
        ]);

        $payload = $this->service->forEditionParticipations(
            (int) $edition->id_edition,
            collect([$participation])
        );

        $this->assertSame(1, $payload['edition_summary']['registered_not_participating_count']);
        $this->assertTrue($payload['edition_summary']['participations_locked']);
    }
}
