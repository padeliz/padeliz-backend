<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Pdf;

use App\Models\CachedPdfPlayerImage;
use App\Models\Player;
use App\Services\Pdf\PosterPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PosterPdfServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_scratch_ranking_succeeds_without_profile_images(): void
    {
        $players = Player::factory()->count(4)->create();

        $result = (new PosterPdfService())->generateRankingFromScratch([
            'player_ids' => $players->pluck('id_player')->all(),
            'court_names' => ['Court 1'],
            'time_start' => '14:00',
            'time_end' => '17:00',
            'opponents_per_player' => 3,
            'repeat_partners' => 1,
            'title' => 'Test Division',
            'organizer_id' => null,
            'club_id' => null,
            'include_final' => false,
            'has_demonstrative_match' => false,
        ]);

        $this->assertNotSame('', $result->bytes);
        $this->assertStringStartsWith('%PDF', $result->bytes);
        $this->assertStringContainsString('Test Division', $result->filename);
    }

    public function test_scratch_ranking_writes_no_cache_rows(): void
    {
        $players = Player::factory()->count(4)->create();

        (new PosterPdfService())->generateRankingFromScratch([
            'player_ids' => $players->pluck('id_player')->all(),
            'court_names' => ['Court 1'],
            'time_start' => '14:00',
            'time_end' => '17:00',
            'opponents_per_player' => 3,
            'repeat_partners' => 1,
            'title' => 'Test Division',
            'organizer_id' => null,
            'club_id' => null,
        ]);

        $this->assertSame(0, CachedPdfPlayerImage::query()->count());
    }

    public function test_scratch_ranking_cleans_up_initials_temp_files(): void
    {
        $players = Player::factory()->count(4)->create();
        $dir = storage_path('app/tmp/pdf-initials-placeholders');

        (new PosterPdfService())->generateRankingFromScratch([
            'player_ids' => $players->pluck('id_player')->all(),
            'court_names' => ['Court 1'],
            'time_start' => '14:00',
            'time_end' => '17:00',
            'opponents_per_player' => 3,
            'repeat_partners' => 1,
            'title' => 'Test Division',
            'organizer_id' => null,
            'club_id' => null,
        ]);

        foreach ($players as $player) {
            $leftovers = is_dir($dir) ? glob($dir . '/player_' . $player->id_player . '_*.png') : [];
            $this->assertSame([], $leftovers ?: []);
        }
    }
}
