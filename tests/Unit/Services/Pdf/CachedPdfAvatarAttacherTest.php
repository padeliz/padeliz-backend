<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Pdf;

use App\Models\CachedPdfPlayerImage;
use App\Models\EventDivision;
use App\Models\EventParticipation;
use App\Models\PadelEvent;
use App\Models\User;
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
        $player = User::factory()->create();
        CachedPdfPlayerImage::query()->create([
            'player_user_id' => $player->id_user,
            'player_is_collecting_points' => true,
            'picture_path' => 'cached-pdf-player-images/' . $player->id_user . '/true.png',
        ]);

        $rows = [
            [
                'player_user_id' => $player->id_user,
                'is_collecting_points' => true,
            ],
            [
                'player_user_id' => $player->id_user,
                'is_collecting_points' => false,
            ],
        ];

        $this->attacher->overlayParticipationsOnArray($rows);

        $this->assertNotNull($rows[0]['pdf_avatar_url']);
        $this->assertNull($rows[1]['pdf_avatar_url']);
    }

    public function test_hydrate_participations_sets_model_attribute(): void
    {
        $event = PadelEvent::factory()->create();
        $division = EventDivision::factory()->create(['event_id' => $event->id_event]);
        $player = User::factory()->create();
        $participation = EventParticipation::factory()->create([
            'event_id' => $event->id_event,
            'event_division_id' => $division->id_event_division,
            'player_user_id' => $player->id_user,
            'is_collecting_points' => true,
        ]);
        CachedPdfPlayerImage::query()->create([
            'player_user_id' => $player->id_user,
            'player_is_collecting_points' => true,
            'picture_path' => 'cached-pdf-player-images/' . $player->id_user . '/true.png',
        ]);

        $this->attacher->hydrateParticipation($participation);

        $this->assertArrayHasKey('pdf_avatar_url', $participation->getAttributes());
        $this->assertNotNull($participation->getAttribute('pdf_avatar_url'));
    }

    public function test_overlay_players_prefers_collecting_true_else_any(): void
    {
        $withTrue = User::factory()->create();
        $onlyFalse = User::factory()->create();
        CachedPdfPlayerImage::query()->create([
            'player_user_id' => $withTrue->id_user,
            'player_is_collecting_points' => true,
            'picture_path' => 'cached-pdf-player-images/' . $withTrue->id_user . '/true.png',
        ]);
        CachedPdfPlayerImage::query()->create([
            'player_user_id' => $withTrue->id_user,
            'player_is_collecting_points' => false,
            'picture_path' => 'cached-pdf-player-images/' . $withTrue->id_user . '/false.png',
        ]);
        CachedPdfPlayerImage::query()->create([
            'player_user_id' => $onlyFalse->id_user,
            'player_is_collecting_points' => false,
            'picture_path' => 'cached-pdf-player-images/' . $onlyFalse->id_user . '/false.png',
        ]);

        $rows = [
            ['id_user' => $withTrue->id_user],
            ['id_user' => $onlyFalse->id_user],
        ];
        $this->attacher->overlayPlayersOnArray($rows);

        $this->assertSame(
            CachedPdfPlayerImage::urlForPlayer((int) $withTrue->id_user, true),
            $rows[0]['pdf_avatar_url']
        );
        $this->assertSame(
            CachedPdfPlayerImage::urlForPlayer((int) $onlyFalse->id_user, false),
            $rows[1]['pdf_avatar_url']
        );
    }
}
