<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Player;
use App\Models\PlayerIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LinkPlayerIdentityCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_links_identity_successfully(): void
    {
        $player = Player::factory()->create();

        $this->artisan('players:link-identity', [
            'player_id' => $player->id_player,
            'provider' => 'google',
            'subject' => 'cli-sub-1',
            '--email' => 'cli@example.com',
        ])->assertSuccessful();

        $this->assertDatabaseHas('player_identities', [
            'player_id' => $player->id_player,
            'provider' => PlayerIdentity::PROVIDER_GOOGLE,
            'provider_subject' => 'cli-sub-1',
        ]);
        $this->assertDatabaseHas('player_emails', [
            'player_id' => $player->id_player,
            'email' => 'cli@example.com',
        ]);
    }

    public function test_conflict_with_other_player_fails(): void
    {
        Player::factory()->withGoogleIdentity('cli-taken')->create();
        $other = Player::factory()->create();

        $this->artisan('players:link-identity', [
            'player_id' => $other->id_player,
            'provider' => 'google',
            'subject' => 'cli-taken',
        ])->assertFailed();
    }

    public function test_already_linked_same_player_succeeds(): void
    {
        $player = Player::factory()->withGoogleIdentity('cli-same')->create();

        $this->artisan('players:link-identity', [
            'player_id' => $player->id_player,
            'provider' => 'google',
            'subject' => 'cli-same',
        ])->assertSuccessful();

        $this->assertSame(1, PlayerIdentity::query()->where('provider_subject', 'cli-same')->count());
    }
}
