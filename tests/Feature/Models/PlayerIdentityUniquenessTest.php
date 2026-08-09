<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\Player;
use App\Models\PlayerIdentity;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerIdentityUniquenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_provider_and_provider_subject_fails(): void
    {
        $player = Player::factory()->create();

        PlayerIdentity::factory()->create([
            'player_id' => $player->id_player,
            'provider' => PlayerIdentity::PROVIDER_GOOGLE,
            'provider_subject' => 'google-subject-1',
        ]);

        $this->expectException(QueryException::class);

        PlayerIdentity::factory()->create([
            'player_id' => Player::factory()->create()->id_player,
            'provider' => PlayerIdentity::PROVIDER_GOOGLE,
            'provider_subject' => 'google-subject-1',
        ]);
    }

    public function test_same_player_can_have_two_google_identities_with_different_subjects(): void
    {
        $player = Player::factory()->create();

        PlayerIdentity::factory()->create([
            'player_id' => $player->id_player,
            'provider' => PlayerIdentity::PROVIDER_GOOGLE,
            'provider_subject' => 'google-subject-a',
        ]);

        PlayerIdentity::factory()->create([
            'player_id' => $player->id_player,
            'provider' => PlayerIdentity::PROVIDER_GOOGLE,
            'provider_subject' => 'google-subject-b',
        ]);

        $this->assertSame(
            2,
            PlayerIdentity::query()
                ->where('player_id', $player->id_player)
                ->where('provider', PlayerIdentity::PROVIDER_GOOGLE)
                ->count()
        );
    }
}
