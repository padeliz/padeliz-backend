<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\User;
use App\Models\UserIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LinkPlayerIdentityCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_links_identity_successfully(): void
    {
        $player = User::factory()->create();

        $this->artisan('players:link-identity', [
            'player_user_id' => $player->id_user,
            'provider' => 'google',
            'subject' => 'cli-sub-1',
            '--email' => 'cli@example.com',
        ])->assertSuccessful();

        $this->assertDatabaseHas('user_identities', [
            'user_id' => $player->id_user,
            'provider' => UserIdentity::PROVIDER_GOOGLE,
            'provider_subject' => 'cli-sub-1',
        ]);
        $this->assertDatabaseHas('user_emails', [
            'user_id' => $player->id_user,
            'email' => 'cli@example.com',
        ]);
    }

    public function test_conflict_with_other_player_fails(): void
    {
        User::factory()->withGoogleIdentity('cli-taken')->create();
        $other = User::factory()->create();

        $this->artisan('players:link-identity', [
            'player_user_id' => $other->id_user,
            'provider' => 'google',
            'subject' => 'cli-taken',
        ])->assertFailed();
    }

    public function test_already_linked_same_player_succeeds(): void
    {
        $player = User::factory()->withGoogleIdentity('cli-same')->create();

        $this->artisan('players:link-identity', [
            'player_user_id' => $player->id_user,
            'provider' => 'google',
            'subject' => 'cli-same',
        ])->assertSuccessful();

        $this->assertSame(1, UserIdentity::query()->where('provider_subject', 'cli-same')->count());
    }
}
