<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\Division;
use App\Models\Edition;
use App\Models\EditionDivision;
use App\Models\EditionParticipation;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MyEditionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_my_editions(): void
    {
        $this->getJson('/api/v1/app/my/editions?' . http_build_query([
            'date_from' => '2026-04-01',
            'date_to' => '2026-04-30',
        ]))->assertUnauthorized();
    }

    public function test_requires_date_from_and_date_to_when_authenticated(): void
    {
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        $this->getJson('/api/v1/app/my/editions')
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validation failed',
            ])
            ->assertJsonStructure(['errors' => ['date_from', 'date_to']]);
    }

    public function test_returns_only_editions_the_player_participated_in_within_range(): void
    {
        $player = Player::factory()->playerOnly()->create();
        Sanctum::actingAs($player);

        $division = Division::factory()->create();

        $inRangeWithParticipation = Edition::factory()->create(['date' => '2026-04-15']);
        $edInRange = EditionDivision::factory()->create([
            'edition_id' => $inRangeWithParticipation->id_edition,
            'division_id' => $division->id_division,
        ]);
        EditionParticipation::factory()->create([
            'edition_id' => $inRangeWithParticipation->id_edition,
            'player_id' => $player->id_player,
            'edition_division_id' => $edInRange->id_edition_division,
        ]);

        $inRangeWithoutParticipation = Edition::factory()->create(['date' => '2026-04-16']);

        $outOfRangeWithParticipation = Edition::factory()->create(['date' => '2026-05-01']);
        $edOut = EditionDivision::factory()->create([
            'edition_id' => $outOfRangeWithParticipation->id_edition,
            'division_id' => $division->id_division,
        ]);
        EditionParticipation::factory()->create([
            'edition_id' => $outOfRangeWithParticipation->id_edition,
            'player_id' => $player->id_player,
            'edition_division_id' => $edOut->id_edition_division,
        ]);

        $response = $this->getJson('/api/v1/app/my/editions?' . http_build_query([
            'date_from' => '2026-04-01',
            'date_to' => '2026-04-30',
        ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_edition', $inRangeWithParticipation->id_edition);
    }
}
