<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\User;
use App\Models\UserEmail;
use App\Models\UserIdentity;
use App\Models\UserPhone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlayerIdentityControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private User $player;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->manager()->create();
        $this->player = User::factory()->create();
    }

    public function test_index_requires_manager(): void
    {
        $this->getJson('/api/v1/manager/player-identities')->assertStatus(401);

        Sanctum::actingAs(User::factory()->playerOnly()->create());
        $this->getJson('/api/v1/manager/player-identities')->assertStatus(403);
    }

    public function test_manager_can_list_identities_filtered_by_player(): void
    {
        Sanctum::actingAs($this->manager);

        $mine = UserIdentity::factory()->create(['user_id' => $this->player->id_user]);
        UserIdentity::factory()->count(2)->create();

        $response = $this->getJson('/api/v1/manager/player-identities?filters[user_id]=' . $this->player->id_user);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [[
                    'id_user_identity', 'user_id', 'provider', 'provider_subject', 'email_id', 'phone_id',
                    'display_name', 'avatar_url', 'is_avatar_primary', 'linked_at', 'last_login_at',
                    'inserted_at', 'updated_at',
                ]],
                'meta',
            ]);
        $this->assertSame([$mine->id_user_identity], collect($response->json('data'))->pluck('id_user_identity')->all());
    }

    public function test_manager_can_filter_identities_by_provider(): void
    {
        Sanctum::actingAs($this->manager);

        UserIdentity::factory()->create(['provider' => UserIdentity::PROVIDER_GOOGLE]);
        $sms = UserIdentity::factory()->create(['provider' => UserIdentity::PROVIDER_SMS]);

        $response = $this->getJson('/api/v1/manager/player-identities?filters[provider]=sms');

        $response->assertStatus(200);
        $this->assertSame([$sms->id_user_identity], collect($response->json('data'))->pluck('id_user_identity')->all());
    }

    public function test_manager_can_show_identity(): void
    {
        Sanctum::actingAs($this->manager);

        $identity = UserIdentity::factory()->create(['user_id' => $this->player->id_user]);

        $this->getJson('/api/v1/manager/player-identities/' . $identity->id_user_identity)
            ->assertStatus(200)
            ->assertJsonPath('data.id_user_identity', $identity->id_user_identity)
            ->assertJsonPath('data.provider_subject', $identity->provider_subject);

        $this->getJson('/api/v1/manager/player-identities/999999')->assertStatus(404);
    }

    public function test_manager_can_update_profile_fields_of_identity(): void
    {
        Sanctum::actingAs($this->manager);

        $email = UserEmail::factory()->create(['user_id' => $this->player->id_user]);
        $identity = UserIdentity::factory()->create([
            'user_id' => $this->player->id_user,
            'display_name' => 'Old Name',
            'avatar_url' => null,
        ]);

        $this->putJson('/api/v1/manager/player-identities/' . $identity->id_user_identity, [
            'display_name' => 'New Name',
            'avatar_url' => 'https://example.test/avatar.png',
            'email_id' => $email->id_user_email,
        ])->assertStatus(200)
            ->assertJsonPath('data.display_name', 'New Name')
            ->assertJsonPath('data.email_id', $email->id_user_email);

        $this->assertDatabaseHas('user_identities', [
            'id_user_identity' => $identity->id_user_identity,
            'display_name' => 'New Name',
            'avatar_url' => 'https://example.test/avatar.png',
        ]);
    }

    public function test_update_cannot_change_the_provider_link(): void
    {
        Sanctum::actingAs($this->manager);

        $other = User::factory()->create();
        $identity = UserIdentity::factory()->create([
            'user_id' => $this->player->id_user,
            'provider' => UserIdentity::PROVIDER_GOOGLE,
            'provider_subject' => 'subject-1',
        ]);

        $this->putJson('/api/v1/manager/player-identities/' . $identity->id_user_identity, [
            'user_id' => $other->id_user,
            'provider' => UserIdentity::PROVIDER_APPLE,
            'provider_subject' => 'subject-2',
            'display_name' => 'Kept',
        ])->assertStatus(200);

        $identity->refresh();
        $this->assertSame($this->player->id_user, $identity->user_id);
        $this->assertSame(UserIdentity::PROVIDER_GOOGLE, $identity->provider);
        $this->assertSame('subject-1', $identity->provider_subject);
        $this->assertSame('Kept', $identity->display_name);
    }

    public function test_update_rejects_email_or_phone_of_another_player(): void
    {
        Sanctum::actingAs($this->manager);

        $identity = UserIdentity::factory()->create(['user_id' => $this->player->id_user]);
        $foreignEmail = UserEmail::factory()->create();
        $foreignPhone = UserPhone::factory()->create();

        $this->putJson('/api/v1/manager/player-identities/' . $identity->id_user_identity, [
            'email_id' => $foreignEmail->id_user_email,
            'phone_id' => $foreignPhone->id_user_phone,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['email_id', 'phone_id']);
    }

    public function test_marking_an_identity_avatar_primary_demotes_the_players_other_primary(): void
    {
        Sanctum::actingAs($this->manager);

        $first = UserIdentity::factory()->create([
            'user_id' => $this->player->id_user,
            'avatar_url' => null,
            'is_avatar_primary' => true,
        ]);
        $second = UserIdentity::factory()->create([
            'user_id' => $this->player->id_user,
            'avatar_url' => null,
            'is_avatar_primary' => false,
        ]);

        $this->putJson('/api/v1/manager/player-identities/' . $second->id_user_identity, [
            'is_avatar_primary' => true,
        ])->assertStatus(200)->assertJsonPath('data.is_avatar_primary', true);

        $this->assertFalse($first->refresh()->is_avatar_primary);
        $this->assertTrue($second->refresh()->is_avatar_primary);
    }

    public function test_manager_can_delete_identity(): void
    {
        Sanctum::actingAs($this->manager);

        $identity = UserIdentity::factory()->create();

        $this->deleteJson('/api/v1/manager/player-identities/' . $identity->id_user_identity)
            ->assertStatus(200);

        $this->assertDatabaseMissing('user_identities', ['id_user_identity' => $identity->id_user_identity]);
        $this->deleteJson('/api/v1/manager/player-identities/' . $identity->id_user_identity)->assertStatus(404);
    }

    public function test_identities_cannot_be_created_by_managers(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson('/api/v1/manager/player-identities', [
            'user_id' => $this->player->id_user,
            'provider' => UserIdentity::PROVIDER_GOOGLE,
            'provider_subject' => 'subject-1',
            'linked_at' => time(),
        ])->assertStatus(405);

        $this->assertSame(0, UserIdentity::query()->count());
    }

    public function test_no_store_route_is_registered_for_identities(): void
    {
        $storeRoutes = collect(Route::getRoutes()->getRoutes())->filter(
            static fn ($route): bool => in_array('POST', $route->methods(), true)
                && $route->uri() === 'api/v1/manager/player-identities'
        );

        $this->assertCount(0, $storeRoutes);
    }

    public function test_identity_model_exposes_resource_metadata(): void
    {
        $this->assertContains('user_id', UserIdentity::getFilterableFields());
        $this->assertSame('display_name', UserIdentity::$displayField);
    }
}
