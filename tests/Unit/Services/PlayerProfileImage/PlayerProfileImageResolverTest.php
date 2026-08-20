<?php

declare(strict_types=1);

namespace Tests\Unit\Services\PlayerProfileImage;

use App\Models\Player;
use App\Models\PlayerProfileImage;
use App\Services\PlayerProfileImage\PlayerProfileImageResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerProfileImageResolverTest extends TestCase
{
    use RefreshDatabase;

    private PlayerProfileImageResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new PlayerProfileImageResolver();
    }

    public function test_returns_null_when_no_images(): void
    {
        $player = Player::factory()->create();

        $this->assertNull($this->resolver->resolveForPdf($player));
    }

    public function test_prefers_flagged_pdf_primary(): void
    {
        $player = Player::factory()->create();
        $first = PlayerProfileImage::factory()->create([
            'player_id' => $player->id_player,
            'sort_order' => 0,
            'is_pdf_primary' => false,
        ]);
        $second = PlayerProfileImage::factory()->create([
            'player_id' => $player->id_player,
            'sort_order' => 1,
            'is_pdf_primary' => true,
        ]);

        $resolved = $this->resolver->resolveForPdf($player->fresh());

        $this->assertSame($second->id, $resolved?->id);
        $this->assertNotSame($first->id, $resolved?->id);
    }

    public function test_falls_back_to_canonical_when_none_flagged(): void
    {
        $player = Player::factory()->create();
        PlayerProfileImage::factory()->create([
            'player_id' => $player->id_player,
            'sort_order' => 1,
            'is_pdf_primary' => false,
        ]);
        $lowest = PlayerProfileImage::factory()->create([
            'player_id' => $player->id_player,
            'sort_order' => 0,
            'is_pdf_primary' => false,
        ]);

        $resolved = $this->resolver->resolveForPdf($player->fresh());

        $this->assertSame($lowest->id, $resolved?->id);
    }
}
