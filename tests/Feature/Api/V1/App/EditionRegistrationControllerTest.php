<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\Division;
use App\Models\Edition;
use App\Models\EditionDivision;
use App\Models\EditionParticipation;
use App\Models\EditionRegistration;
use App\Models\Eligibility;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EditionRegistrationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Player $player;

    protected Edition $edition;

    protected EditionDivision $editionDivision;

    protected function setUp(): void
    {
        parent::setUp();

        $this->player = Player::factory()->playerOnly()->create();
        $this->edition = Edition::factory()->create([
            'date' => now()->addDays(30)->format('Y-m-d'),
            'entry_fee' => 55,
            'students_entry_fee' => 45,
        ]);
        $division = Division::factory()->create();
        $this->editionDivision = EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => $division->id_division,
            'division_title' => $division->name,
            'players_limit' => 10,
        ]);

        Eligibility::factory()->create([
            'player_id' => $this->player->id_player,
            'review_status' => 'approved',
            'being_student' => false,
        ]);
    }

    public function test_store_creates_registration_with_edition_division(): void
    {
        Sanctum::actingAs($this->player);

        $response = $this->postJson('/api/v1/app/edition-registrations', [
            'edition_id' => $this->edition->id_edition,
            'edition_division_id' => $this->editionDivision->id_edition_division,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Registration created',
                'data' => [
                    'player_id' => $this->player->id_player,
                    'edition_id' => $this->edition->id_edition,
                    'edition_division_id' => $this->editionDivision->id_edition_division,
                    'is_minor' => false,
                    'is_student' => false,
                    'discount' => 0,
                    'payment' => 55,
                ],
            ]);
        $this->assertDatabaseCount('edition_participations', 0);
    }

    public function test_store_creates_registration_with_omitted_edition_division(): void
    {
        Sanctum::actingAs($this->player);

        $response = $this->postJson('/api/v1/app/edition-registrations', [
            'edition_id' => $this->edition->id_edition,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.edition_division_id', null);
    }

    public function test_store_creates_registration_with_explicit_null_edition_division(): void
    {
        Sanctum::actingAs($this->player);

        $response = $this->postJson('/api/v1/app/edition-registrations', [
            'edition_id' => $this->edition->id_edition,
            'edition_division_id' => null,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.edition_division_id', null);
    }

    public function test_store_ignores_player_id_in_body(): void
    {
        Sanctum::actingAs($this->player);
        $other = Player::factory()->playerOnly()->create();

        $response = $this->postJson('/api/v1/app/edition-registrations', [
            'edition_id' => $this->edition->id_edition,
            'player_id' => $other->id_player,
            'edition_division_id' => $this->editionDivision->id_edition_division,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.player_id', $this->player->id_player);
    }

    public function test_store_uses_student_fee_when_eligibility_is_student(): void
    {
        Eligibility::query()->where('player_id', $this->player->id_player)->update(['being_student' => true]);
        Sanctum::actingAs($this->player);

        $response = $this->postJson('/api/v1/app/edition-registrations', [
            'edition_id' => $this->edition->id_edition,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.is_student', true)
            ->assertJsonPath('data.payment', 45);
    }

    public function test_store_does_not_create_or_change_participation(): void
    {
        EditionParticipation::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'player_id' => $this->player->id_player,
            'edition_division_id' => $this->editionDivision->id_edition_division,
        ]);
        Sanctum::actingAs($this->player);

        $this->postJson('/api/v1/app/edition-registrations', [
            'edition_id' => $this->edition->id_edition,
            'edition_division_id' => $this->editionDivision->id_edition_division,
        ])->assertStatus(201);

        $this->assertDatabaseCount('edition_participations', 1);
    }

    public function test_store_rejects_past_and_today_dates(): void
    {
        Sanctum::actingAs($this->player);

        $this->edition->update(['date' => now()->subDay()->format('Y-m-d')]);
        $this->postJson('/api/v1/app/edition-registrations', [
            'edition_id' => $this->edition->id_edition,
        ])->assertStatus(422);

        $this->edition->update(['date' => now()->format('Y-m-d')]);
        $this->postJson('/api/v1/app/edition-registrations', [
            'edition_id' => $this->edition->id_edition,
        ])->assertStatus(422);
    }

    public function test_store_allows_future_date_even_if_finalized(): void
    {
        $this->edition->update(['finalized_at' => time()]);
        Sanctum::actingAs($this->player);

        $this->postJson('/api/v1/app/edition-registrations', [
            'edition_id' => $this->edition->id_edition,
        ])->assertStatus(201);
    }

    public function test_store_forbidden_without_approved_eligibility(): void
    {
        Eligibility::query()->where('player_id', $this->player->id_player)->delete();
        Sanctum::actingAs($this->player);

        $this->postJson('/api/v1/app/edition-registrations', [
            'edition_id' => $this->edition->id_edition,
        ])->assertStatus(403);
    }

    public function test_unauthenticated_post_and_delete_are_401(): void
    {
        $this->postJson('/api/v1/app/edition-registrations', [
            'edition_id' => $this->edition->id_edition,
        ])->assertStatus(401);

        $this->deleteJson('/api/v1/app/edition-registrations/1')->assertStatus(401);
    }

    public function test_store_requires_edition_id(): void
    {
        Sanctum::actingAs($this->player);

        $this->postJson('/api/v1/app/edition-registrations', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['edition_id']);
    }

    public function test_store_rejects_edition_division_from_another_edition(): void
    {
        Sanctum::actingAs($this->player);
        $otherEd = EditionDivision::factory()->create();

        $this->postJson('/api/v1/app/edition-registrations', [
            'edition_id' => $this->edition->id_edition,
            'edition_division_id' => $otherEd->id_edition_division,
        ])->assertStatus(422);
    }

    public function test_xor_second_null_same_edition_is_422(): void
    {
        Sanctum::actingAs($this->player);
        $this->postJson('/api/v1/app/edition-registrations', [
            'edition_id' => $this->edition->id_edition,
        ])->assertStatus(201);

        $this->postJson('/api/v1/app/edition-registrations', [
            'edition_id' => $this->edition->id_edition,
        ])->assertStatus(422);
    }

    public function test_xor_null_when_specific_exists_is_422(): void
    {
        Sanctum::actingAs($this->player);
        $this->postJson('/api/v1/app/edition-registrations', [
            'edition_id' => $this->edition->id_edition,
            'edition_division_id' => $this->editionDivision->id_edition_division,
        ])->assertStatus(201);

        $this->postJson('/api/v1/app/edition-registrations', [
            'edition_id' => $this->edition->id_edition,
            'edition_division_id' => null,
        ])->assertStatus(422);
    }

    public function test_xor_specific_when_null_exists_is_422(): void
    {
        Sanctum::actingAs($this->player);
        $this->postJson('/api/v1/app/edition-registrations', [
            'edition_id' => $this->edition->id_edition,
        ])->assertStatus(201);

        $this->postJson('/api/v1/app/edition-registrations', [
            'edition_id' => $this->edition->id_edition,
            'edition_division_id' => $this->editionDivision->id_edition_division,
        ])->assertStatus(422);
    }

    public function test_xor_two_different_specific_edition_divisions_are_allowed(): void
    {
        Sanctum::actingAs($this->player);
        $second = $this->secondEditionDivision();

        $this->postJson('/api/v1/app/edition-registrations', [
            'edition_id' => $this->edition->id_edition,
            'edition_division_id' => $this->editionDivision->id_edition_division,
        ])->assertStatus(201);

        $this->postJson('/api/v1/app/edition-registrations', [
            'edition_id' => $this->edition->id_edition,
            'edition_division_id' => $second->id_edition_division,
        ])->assertStatus(201);
    }

    public function test_xor_same_specific_edition_division_is_422(): void
    {
        Sanctum::actingAs($this->player);
        $this->postJson('/api/v1/app/edition-registrations', [
            'edition_id' => $this->edition->id_edition,
            'edition_division_id' => $this->editionDivision->id_edition_division,
        ])->assertStatus(201);

        $this->postJson('/api/v1/app/edition-registrations', [
            'edition_id' => $this->edition->id_edition,
            'edition_division_id' => $this->editionDivision->id_edition_division,
        ])->assertStatus(422);
    }

    public function test_xor_null_on_two_different_editions_is_allowed(): void
    {
        Sanctum::actingAs($this->player);
        $otherEdition = Edition::factory()->create(['date' => now()->addDays(40)->format('Y-m-d')]);

        $this->postJson('/api/v1/app/edition-registrations', [
            'edition_id' => $this->edition->id_edition,
        ])->assertStatus(201);

        $this->postJson('/api/v1/app/edition-registrations', [
            'edition_id' => $otherEdition->id_edition,
        ])->assertStatus(201);
    }

    public function test_capacity_full_registrations_is_422(): void
    {
        $this->editionDivision->update(['players_limit' => 1]);
        EditionRegistration::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'edition_division_id' => $this->editionDivision->id_edition_division,
            'player_id' => Player::factory(),
            'is_student' => false,
            'payment' => 55,
        ]);
        Sanctum::actingAs($this->player);

        $this->postJson('/api/v1/app/edition-registrations', [
            'edition_id' => $this->edition->id_edition,
            'edition_division_id' => $this->editionDivision->id_edition_division,
        ])->assertStatus(422);
    }

    public function test_capacity_skipped_for_null_edition_division(): void
    {
        $this->editionDivision->update(['players_limit' => 1]);
        EditionRegistration::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'edition_division_id' => $this->editionDivision->id_edition_division,
            'player_id' => Player::factory(),
            'is_student' => false,
            'payment' => 55,
        ]);
        Sanctum::actingAs($this->player);

        $this->postJson('/api/v1/app/edition-registrations', [
            'edition_id' => $this->edition->id_edition,
        ])->assertStatus(201);
    }

    public function test_capacity_ignores_full_participation_roster(): void
    {
        $this->editionDivision->update(['players_limit' => 1]);
        EditionParticipation::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'edition_division_id' => $this->editionDivision->id_edition_division,
            'player_id' => Player::factory(),
        ]);
        Sanctum::actingAs($this->player);

        $this->postJson('/api/v1/app/edition-registrations', [
            'edition_id' => $this->edition->id_edition,
            'edition_division_id' => $this->editionDivision->id_edition_division,
        ])->assertStatus(201);
    }

    public function test_destroy_own_registration_leaves_participation(): void
    {
        Sanctum::actingAs($this->player);
        $participation = EditionParticipation::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'player_id' => $this->player->id_player,
            'edition_division_id' => $this->editionDivision->id_edition_division,
        ]);
        $registration = EditionRegistration::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'player_id' => $this->player->id_player,
            'edition_division_id' => $this->editionDivision->id_edition_division,
            'is_student' => false,
            'payment' => 55,
        ]);

        $this->deleteJson("/api/v1/app/edition-registrations/{$registration->id_registration}")
            ->assertStatus(200)
            ->assertJson(['message' => 'Registration deleted']);

        $this->assertDatabaseMissing('edition_registrations', [
            'id_registration' => $registration->id_registration,
        ]);
        $this->assertDatabaseHas('edition_participations', [
            'id_participation' => $participation->id_participation,
        ]);
    }

    public function test_destroy_missing_is_404(): void
    {
        Sanctum::actingAs($this->player);

        $this->deleteJson('/api/v1/app/edition-registrations/99999')->assertStatus(404);
    }

    public function test_destroy_other_players_registration_is_404(): void
    {
        $other = Player::factory()->playerOnly()->create();
        $registration = EditionRegistration::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'player_id' => $other->id_player,
            'edition_division_id' => $this->editionDivision->id_edition_division,
            'is_student' => false,
            'payment' => 55,
        ]);
        Sanctum::actingAs($this->player);

        $this->deleteJson("/api/v1/app/edition-registrations/{$registration->id_registration}")
            ->assertStatus(404);
        $this->assertDatabaseHas('edition_registrations', [
            'id_registration' => $registration->id_registration,
        ]);
    }

    public function test_destroy_rejects_when_date_is_today_or_past(): void
    {
        Sanctum::actingAs($this->player);
        $registration = EditionRegistration::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'player_id' => $this->player->id_player,
            'edition_division_id' => $this->editionDivision->id_edition_division,
            'is_student' => false,
            'payment' => 55,
        ]);

        $this->edition->update(['date' => now()->format('Y-m-d')]);
        $this->deleteJson("/api/v1/app/edition-registrations/{$registration->id_registration}")
            ->assertStatus(422);

        $this->edition->update(['date' => now()->subDay()->format('Y-m-d')]);
        $this->deleteJson("/api/v1/app/edition-registrations/{$registration->id_registration}")
            ->assertStatus(422);
        $this->assertDatabaseHas('edition_registrations', [
            'id_registration' => $registration->id_registration,
        ]);
    }

    public function test_destroy_allows_future_date_even_if_finalized(): void
    {
        Sanctum::actingAs($this->player);
        $registration = EditionRegistration::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'player_id' => $this->player->id_player,
            'edition_division_id' => $this->editionDivision->id_edition_division,
            'is_student' => false,
            'payment' => 55,
        ]);
        $this->edition->update(['finalized_at' => time()]);

        $this->deleteJson("/api/v1/app/edition-registrations/{$registration->id_registration}")
            ->assertStatus(200);
    }

    public function test_old_participate_and_withdraw_are_unmatched(): void
    {
        Sanctum::actingAs($this->player);

        $this->postJson("/api/v1/app/editions/{$this->edition->id_edition}/participate", [
            'edition_division_id' => $this->editionDivision->id_edition_division,
        ])->assertStatus(404);

        $this->deleteJson("/api/v1/app/editions/{$this->edition->id_edition}/withdraw")
            ->assertStatus(404);
    }

    public function test_app_get_patch_put_must_not_succeed(): void
    {
        Sanctum::actingAs($this->player);
        $registration = EditionRegistration::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'player_id' => $this->player->id_player,
            'edition_division_id' => $this->editionDivision->id_edition_division,
            'is_student' => false,
            'payment' => 55,
        ]);

        foreach ([
            $this->getJson('/api/v1/app/edition-registrations'),
            $this->getJson("/api/v1/app/edition-registrations/{$registration->id_registration}"),
            $this->patchJson("/api/v1/app/edition-registrations/{$registration->id_registration}", []),
            $this->putJson("/api/v1/app/edition-registrations/{$registration->id_registration}", []),
        ] as $response) {
            $this->assertContains($response->status(), [404, 405]);
        }
    }

    private function secondEditionDivision(): EditionDivision
    {
        return EditionDivision::factory()->create([
            'edition_id' => $this->edition->id_edition,
            'division_id' => Division::factory(),
            'players_limit' => 10,
        ]);
    }
}
