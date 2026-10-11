<?php

declare(strict_types=1);

namespace Tests\Feature\Services\UserIdentity;

use App\Models\User;
use App\Models\UserEmail;
use App\Models\UserIdentity;
use App\Services\UserIdentity\ContactConflictException;
use App\Services\UserIdentity\IdentityConflictException;
use App\Services\UserIdentity\UserIdentityLinker;
use App\Services\UserIdentity\UserIdentityLinkResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class UserIdentityLinkerTest extends TestCase
{
    use RefreshDatabase;

    private UserIdentityLinker $linker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->linker = app(UserIdentityLinker::class);
    }

    public function test_links_new_google_identity_with_primary_email(): void
    {
        $player = User::factory()->create();

        $result = $this->linker->link(
            player: $player,
            provider: UserIdentity::PROVIDER_GOOGLE,
            subject: 'google-sub-1',
            email: 'one@example.com',
            displayName: 'One',
            avatarUrl: 'https://example.com/a.png',
            asLogin: true,
        );

        $this->assertSame(UserIdentityLinkResult::LINKED, $result->status);
        $this->assertDatabaseHas('user_identities', [
            'user_id' => $player->id_user,
            'provider' => UserIdentity::PROVIDER_GOOGLE,
            'provider_subject' => 'google-sub-1',
            'display_name' => 'One',
            'avatar_url' => 'https://example.com/a.png',
        ]);
        $this->assertDatabaseHas('user_emails', [
            'user_id' => $player->id_user,
            'email' => 'one@example.com',
            'is_primary' => 1,
        ]);
        $this->assertNotNull($result->identity->last_login_at);
        $email = UserEmail::query()->where('email', 'one@example.com')->first();
        $this->assertContains('login', $email->purposes);
        $this->assertContains('contact', $email->purposes);
    }

    public function test_idempotent_same_player_touches_login_when_as_login(): void
    {
        $player = User::factory()->withGoogleIdentity('same-sub')->create();
        $identity = $player->identities()->first();
        $identity->update(['last_login_at' => 100]);

        $result = $this->linker->link(
            player: $player,
            provider: UserIdentity::PROVIDER_GOOGLE,
            subject: 'same-sub',
            asLogin: true,
        );

        $this->assertTrue($result->wasAlreadyLinked());
        $this->assertSame(1, UserIdentity::query()->where('provider_subject', 'same-sub')->count());
        $this->assertGreaterThan(100, (int) $identity->fresh()->last_login_at);
    }

    public function test_already_linked_refreshes_avatar_url_from_claims(): void
    {
        $player = User::factory()->withGoogleIdentity('avatar-sub')->create();
        $identity = $player->identities()->first();
        $identity->update(['avatar_url' => 'https://example.com/old.png']);

        $result = $this->linker->link(
            player: $player,
            provider: UserIdentity::PROVIDER_GOOGLE,
            subject: 'avatar-sub',
            avatarUrl: 'https://example.com/new.png',
            asLogin: true,
        );

        $this->assertTrue($result->wasAlreadyLinked());
        $this->assertSame('https://example.com/new.png', $identity->fresh()->avatar_url);
        $this->assertSame('https://example.com/new.png', $result->identity->avatar_url);
    }

    public function test_already_linked_does_not_clear_avatar_url_when_claim_missing(): void
    {
        $player = User::factory()->withGoogleIdentity('keep-sub')->create();
        $identity = $player->identities()->first();
        $identity->update(['avatar_url' => 'https://example.com/keep.png']);

        $this->linker->link(
            player: $player,
            provider: UserIdentity::PROVIDER_GOOGLE,
            subject: 'keep-sub',
            avatarUrl: null,
            asLogin: true,
        );
        $this->linker->link(
            player: $player,
            provider: UserIdentity::PROVIDER_GOOGLE,
            subject: 'keep-sub',
            avatarUrl: '',
            asLogin: true,
        );

        $this->assertSame('https://example.com/keep.png', $identity->fresh()->avatar_url);
    }

    public function test_identity_conflict_other_player(): void
    {
        User::factory()->withGoogleIdentity('taken-sub')->create();
        $other = User::factory()->create();

        $this->expectException(IdentityConflictException::class);

        $this->linker->link(
            player: $other,
            provider: UserIdentity::PROVIDER_GOOGLE,
            subject: 'taken-sub',
            asLogin: true,
        );
    }

    public function test_contact_conflict_other_player_email(): void
    {
        $owner = User::factory()->create();
        UserEmail::factory()->create([
            'user_id' => $owner->id_user,
            'email' => 'owned@example.com',
            'is_primary' => true,
            'purposes' => ['contact'],
        ]);
        $target = User::factory()->create();

        $this->expectException(ContactConflictException::class);

        $this->linker->link(
            player: $target,
            provider: UserIdentity::PROVIDER_GOOGLE,
            subject: 'new-sub',
            email: 'owned@example.com',
            asLogin: true,
        );
    }

    public function test_reuses_same_player_email_and_adds_login_purpose(): void
    {
        $player = User::factory()->create();
        $email = UserEmail::factory()->create([
            'user_id' => $player->id_user,
            'email' => 'reuse@example.com',
            'is_primary' => true,
            'purposes' => ['contact'],
            'verified_at' => null,
        ]);

        $result = $this->linker->link(
            player: $player,
            provider: UserIdentity::PROVIDER_GOOGLE,
            subject: 'reuse-sub',
            email: 'reuse@example.com',
            asLogin: false,
        );

        $this->assertSame(UserIdentityLinkResult::LINKED, $result->status);
        $email->refresh();
        $this->assertContains('login', $email->purposes);
        $this->assertNotNull($email->verified_at);
        $this->assertNull($result->identity->last_login_at);
        $this->assertFalse(
            UserEmail::query()->where('email', 'reuse@example.com')->where('is_primary', false)->exists()
        );
    }

    public function test_second_email_is_not_primary_when_primary_exists(): void
    {
        $player = User::factory()->withPrimaryEmail()->create();
        $this->assertTrue($player->emails()->where('is_primary', true)->exists());

        $this->linker->link(
            player: $player,
            provider: UserIdentity::PROVIDER_GOOGLE,
            subject: 'second-sub',
            email: 'second@example.com',
            asLogin: true,
        );

        $this->assertDatabaseHas('user_emails', [
            'email' => 'second@example.com',
            'is_primary' => 0,
        ]);
    }
}
