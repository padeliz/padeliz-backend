<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Pdf;

use App\Models\CachedPdfPlayerImage;
use App\Models\Edition;
use App\Models\EditionDivision;
use App\Models\EditionParticipation;
use App\Models\Player;
use App\Services\Pdf\CachedPdfAvatarAttacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class CachedPdfAvatarAttacherTest extends TestCase
{
    use RefreshDatabase;

    private CachedPdfAvatarAttacher $attacher;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.user_uploads_disk', 'public'));
        $this->attacher = new CachedPdfAvatarAttacher();
    }

    public function test_overlay_participations_on_array_sets_and_clears_urls(): void
    {
        $player = Player::factory()->create();
        CachedPdfPlayerImage::query()->create([
            'player_id' => $player->id_player,
            'player_is_collecting_points' => true,
            'picture_path' => 'cached-pdf-player-images/' . $player->id_player . '/true.png',
        ]);

        $rows = [
            [
                'player_id' => $player->id_player,
                'is_collecting_points' => true,
            ],
            [
                'player_id' => $player->id_player,
                'is_collecting_points' => false,
            ],
        ];

        $this->attacher->overlayParticipationsOnArray($rows);

        $this->assertNotNull($rows[0]['pdf_avatar_url']);
        $this->assertNull($rows[1]['pdf_avatar_url']);
    }

    public function test_hydrate_participations_sets_model_attribute(): void
    {
        $edition = Edition::factory()->create();
        $division = EditionDivision::factory()->create(['edition_id' => $edition->id_edition]);
        $player = Player::factory()->create();
        $participation = EditionParticipation::factory()->create([
            'edition_id' => $edition->id_edition,
            'edition_division_id' => $division->id_edition_division,
            'player_id' => $player->id_player,
            'is_collecting_points' => true,
        ]);
        CachedPdfPlayerImage::query()->create([
            'player_id' => $player->id_player,
            'player_is_collecting_points' => true,
            'picture_path' => 'cached-pdf-player-images/' . $player->id_player . '/true.png',
        ]);

        $this->attacher->hydrateParticipation($participation);

        $this->assertArrayHasKey('pdf_avatar_url', $participation->getAttributes());
        $this->assertNotNull($participation->getAttribute('pdf_avatar_url'));
    }

    public function test_overlay_players_prefers_collecting_true_else_any(): void
    {
        $withTrue = Player::factory()->create();
        $onlyFalse = Player::factory()->create();
        CachedPdfPlayerImage::query()->create([
            'player_id' => $withTrue->id_player,
            'player_is_collecting_points' => true,
            'picture_path' => 'cached-pdf-player-images/' . $withTrue->id_player . '/true.png',
        ]);
        CachedPdfPlayerImage::query()->create([
            'player_id' => $withTrue->id_player,
            'player_is_collecting_points' => false,
            'picture_path' => 'cached-pdf-player-images/' . $withTrue->id_player . '/false.png',
        ]);
        CachedPdfPlayerImage::query()->create([
            'player_id' => $onlyFalse->id_player,
            'player_is_collecting_points' => false,
            'picture_path' => 'cached-pdf-player-images/' . $onlyFalse->id_player . '/false.png',
        ]);

        $rows = [
            ['id_player' => $withTrue->id_player],
            ['id_player' => $onlyFalse->id_player],
        ];
        $this->attacher->overlayPlayersOnArray($rows);

        $this->assertSame(
            CachedPdfPlayerImage::urlForPlayer((int) $withTrue->id_player, true),
            $rows[0]['pdf_avatar_url']
        );
        $this->assertSame(
            CachedPdfPlayerImage::urlForPlayer((int) $onlyFalse->id_player, false),
            $rows[1]['pdf_avatar_url']
        );
    }
}
