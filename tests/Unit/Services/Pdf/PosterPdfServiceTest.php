<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Pdf;

use App\Models\Player;
use App\Services\Pdf\MissingPlayerProfileException;
use App\Services\Pdf\PosterPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PosterPdfServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_scratch_ranking_fails_without_profile_path(): void
    {
        $players = Player::factory()->count(4)->create();

        $service = new PosterPdfService();

        $this->expectException(MissingPlayerProfileException::class);

        $service->generateRankingFromScratch([
            'player_ids' => $players->pluck('id_player')->all(),
            'court_names' => ['Court 1'],
            'time_start' => '14:00',
            'time_end' => '17:00',
            'opponents_per_player' => 3,
            'repeat_partners' => 1,
            'title' => 'Test Division',
            'organizer_id' => 1,
            'partner_id' => 1,
            'include_final' => false,
            'has_demonstrative_match' => false,
        ]);
    }

    public function test_missing_profile_exception_lists_player_ids(): void
    {
        $players = Player::factory()->count(4)->create();
        $service = new PosterPdfService();

        try {
            $service->generateRankingFromScratch([
                'player_ids' => $players->pluck('id_player')->all(),
                'court_names' => ['Court 1'],
                'time_start' => '14:00',
                'time_end' => '17:00',
                'opponents_per_player' => 3,
                'repeat_partners' => 1,
                'title' => 'Test Division',
                'organizer_id' => 1,
                'partner_id' => 1,
            ]);
            $this->fail('Expected MissingPlayerProfileException');
        } catch (MissingPlayerProfileException $e) {
            foreach ($players as $player) {
                $this->assertContains((int) $player->id_player, $e->playerIds);
            }
        }
    }
}
