<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\User;
use App\Models\UserIdentity;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserIdentityUniquenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_provider_and_provider_subject_fails(): void
    {
        $player = User::factory()->create();

        UserIdentity::factory()->create([
            'user_id' => $player->id_user,
            'provider' => UserIdentity::PROVIDER_GOOGLE,
            'provider_subject' => 'google-subject-1',
        ]);

        $this->expectException(QueryException::class);

        UserIdentity::factory()->create([
            'user_id' => User::factory()->create()->id_user,
            'provider' => UserIdentity::PROVIDER_GOOGLE,
            'provider_subject' => 'google-subject-1',
        ]);
    }

    public function test_same_player_can_have_two_google_identities_with_different_subjects(): void
    {
        $player = User::factory()->create();

        UserIdentity::factory()->create([
            'user_id' => $player->id_user,
            'provider' => UserIdentity::PROVIDER_GOOGLE,
            'provider_subject' => 'google-subject-a',
        ]);

        UserIdentity::factory()->create([
            'user_id' => $player->id_user,
            'provider' => UserIdentity::PROVIDER_GOOGLE,
            'provider_subject' => 'google-subject-b',
        ]);

        $this->assertSame(
            2,
            UserIdentity::query()
                ->where('user_id', $player->id_user)
                ->where('provider', UserIdentity::PROVIDER_GOOGLE)
                ->count()
        );
    }
}
