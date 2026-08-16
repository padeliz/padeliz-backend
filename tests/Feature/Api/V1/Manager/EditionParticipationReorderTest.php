<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Division;
use App\Models\Edition;
use App\Models\EditionDivision;
use App\Models\EditionParticipation;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EditionParticipationReorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_reorders_and_moves_participations_within_edition(): void
    {
        $manager = Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);

        $edition = Edition::factory()->create();
        $divisionA = Division::factory()->create();
        $divisionB = Division::factory()->create();

        $edA = EditionDivision::factory()->create([
            'edition_id' => $edition->id_edition,
            'division_id' => $divisionA->id_division,
        ]);
        $edB = EditionDivision::factory()->create([
            'edition_id' => $edition->id_edition,
            'division_id' => $divisionB->id_division,
        ]);

        $p1 = EditionParticipation::factory()->create([
            'edition_id' => $edition->id_edition,
            'edition_division_id' => $edA->id_edition_division,
            'sort_order' => 0,
        ]);
        $p2 = EditionParticipation::factory()->create([
            'edition_id' => $edition->id_edition,
            'edition_division_id' => $edA->id_edition_division,
            'sort_order' => 1,
        ]);

        $response = $this->patchJson('/api/v1/manager/edition-participations/reorder', [
            'participations' => [
                [
                    'id_participation' => $p1->id_participation,
                    'edition_division_id' => $edA->id_edition_division,
                    'sort_order' => 1,
                ],
                [
                    'id_participation' => $p2->id_participation,
                    'edition_division_id' => $edB->id_edition_division,
                    'sort_order' => 0,
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('edition_participations', [
            'id_participation' => $p1->id_participation,
            'edition_division_id' => $edA->id_edition_division,
            'sort_order' => 1,
        ]);
        $this->assertDatabaseHas('edition_participations', [
            'id_participation' => $p2->id_participation,
            'edition_division_id' => $edB->id_edition_division,
            'sort_order' => 0,
        ]);
    }

    public function test_rejects_when_edition_is_finalized(): void
    {
        $manager = Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);

        $edition = Edition::factory()->create([
            'finalized_at' => time(),
        ]);
        $divisionA = Division::factory()->create();
        $divisionB = Division::factory()->create();

        $edA = EditionDivision::factory()->create([
            'edition_id' => $edition->id_edition,
            'division_id' => $divisionA->id_division,
        ]);
        $edB = EditionDivision::factory()->create([
            'edition_id' => $edition->id_edition,
            'division_id' => $divisionB->id_division,
        ]);

        $p1 = EditionParticipation::factory()->create([
            'edition_id' => $edition->id_edition,
            'edition_division_id' => $edA->id_edition_division,
            'sort_order' => 0,
        ]);

        $this->patchJson('/api/v1/manager/edition-participations/reorder', [
            'participations' => [
                [
                    'id_participation' => $p1->id_participation,
                    'edition_division_id' => $edB->id_edition_division,
                    'sort_order' => 0,
                ],
            ],
        ])->assertStatus(409);
    }
}
