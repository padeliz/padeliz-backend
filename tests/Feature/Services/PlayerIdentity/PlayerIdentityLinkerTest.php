<?php

declare(strict_types=1);

namespace Tests\Feature\Services\PlayerIdentity;

use App\Models\Player;
use App\Models\PlayerEmail;
use App\Models\PlayerIdentity;
use App\Services\PlayerIdentity\ContactConflictException;
use App\Services\PlayerIdentity\IdentityConflictException;
use App\Services\PlayerIdentity\PlayerIdentityLinkResult;
use App\Services\PlayerIdentity\PlayerIdentityLinker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PlayerIdentityLinkerTest extends TestCase
{
    use RefreshDatabase;

    private PlayerIdentityLinker $linker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->linker = app(PlayerIdentityLinker::class);
    }

    public function test_links_new_google_identity_with_primary_email(): void
    {
        $player = Player::factory()->create();
        $player->emails()->delete();

        $result = $this->linker->link(
            player: $player,
            provider: PlayerIdentity::PROVIDER_GOOGLE,
            subject: 'google-sub-1',
            email: 'one@example.com',
            displayName: 'One',
            avatarUrl: 'https://example.com/a.png',
            asLogin: true,
        );

        $this->assertSame(PlayerIdentityLinkResult::LINKED, $result->status);
        $this->assertDatabaseHas('player_identities', [
            'player_id' => $player->id_player,
            'provider' => PlayerIdentity::PROVIDER_GOOGLE,
            'provider_subject' => 'google-sub-1',
            'display_name' => 'One',
            'avatar_url' => 'https://example.com/a.png',
        ]);
        $this->assertDatabaseHas('player_emails', [
            'player_id' => $player->id_player,
            'email' => 'one@example.com',
            'is_primary' => 1,
        ]);
        $this->assertNotNull($result->identity->last_login_at);
        $email = PlayerEmail::query()->where('email', 'one@example.com')->first();
        $this->assertContains('login', $email->purposes);
        $this->assertContains('contact', $email->purposes);
    }

    public function test_idempotent_same_player_touches_login_when_as_login(): void
    {
        $player = Player::factory()->withGoogleIdentity('same-sub')->create();
        $identity = $player->identities()->first();
        $identity->update(['last_login_at' => 100]);

        $result = $this->linker->link(
            player: $player,
            provider: PlayerIdentity::PROVIDER_GOOGLE,
            subject: 'same-sub',
            asLogin: true,
        );

        $this->assertTrue($result->wasAlreadyLinked());
        $this->assertSame(1, PlayerIdentity::query()->where('provider_subject', 'same-sub')->count());
        $this->assertGreaterThan(100, (int) $identity->fresh()->last_login_at);
    }

    public function test_identity_conflict_other_player(): void
    {
        Player::factory()->withGoogleIdentity('taken-sub')->create();
        $other = Player::factory()->create();

        $this->expectException(IdentityConflictException::class);

        $this->linker->link(
            player: $other,
            provider: PlayerIdentity::PROVIDER_GOOGLE,
            subject: 'taken-sub',
            asLogin: true,
        );
    }

    public function test_contact_conflict_other_player_email(): void
    {
        $owner = Player::factory()->create();
        PlayerEmail::factory()->create([
            'player_id' => $owner->id_player,
            'email' => 'owned@example.com',
            'is_primary' => true,
            'purposes' => ['contact'],
        ]);
        $target = Player::factory()->create();

        $this->expectException(ContactConflictException::class);

        $this->linker->link(
            player: $target,
            provider: PlayerIdentity::PROVIDER_GOOGLE,
            subject: 'new-sub',
            email: 'owned@example.com',
            asLogin: true,
        );
    }

    public function test_reuses_same_player_email_and_adds_login_purpose(): void
    {
        $player = Player::factory()->create();
        $email = PlayerEmail::factory()->create([
            'player_id' => $player->id_player,
            'email' => 'reuse@example.com',
            'is_primary' => true,
            'purposes' => ['contact'],
            'verified_at' => null,
        ]);

        $result = $this->linker->link(
            player: $player,
            provider: PlayerIdentity::PROVIDER_GOOGLE,
            subject: 'reuse-sub',
            email: 'reuse@example.com',
            asLogin: false,
        );

        $this->assertSame(PlayerIdentityLinkResult::LINKED, $result->status);
        $email->refresh();
        $this->assertContains('login', $email->purposes);
        $this->assertNotNull($email->verified_at);
        $this->assertNull($result->identity->last_login_at);
        $this->assertFalse(
            PlayerEmail::query()->where('email', 'reuse@example.com')->where('is_primary', false)->exists()
        );
    }

    public function test_second_email_is_not_primary_when_primary_exists(): void
    {
        $player = Player::factory()->create();
        $this->assertTrue($player->emails()->where('is_primary', true)->exists());

        $this->linker->link(
            player: $player,
            provider: PlayerIdentity::PROVIDER_GOOGLE,
            subject: 'second-sub',
            email: 'second@example.com',
            asLogin: true,
        );

        $this->assertDatabaseHas('player_emails', [
            'email' => 'second@example.com',
            'is_primary' => 0,
        ]);
    }
}
