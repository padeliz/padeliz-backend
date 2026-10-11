<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Enums\PlayerLevel\AssessmentReviewStatus;
use App\Models\User;
use App\Models\PlayerStudentProof;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class PlayerStudentProofControllerTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/api/v1/manager/player-student-proofs';

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]));
    }

    public function test_index_is_paginated_and_filterable(): void
    {
        PlayerStudentProof::factory()->create(['ready_for_review' => true]);
        PlayerStudentProof::factory()->rejected()->create();

        $this->getJson(self::BASE)->assertOk()->assertJsonCount(2, 'data');
        $this->getJson(self::BASE . '?filters[review_status]=rejected')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson(self::BASE . '?filters[review_status]=pending')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_show_loads_player_and_files_with_urls(): void
    {
        $proof = PlayerStudentProof::factory()->withFiles(3)->create();

        $response = $this->getJson(self::BASE . '/' . $proof->id_player_student_proof)
            ->assertOk()
            ->assertJsonPath('data.id_player_student_proof', $proof->id_player_student_proof)
            ->assertJsonPath('data.player.id_user', $proof->player_user_id)
            ->assertJsonCount(3, 'data.files');

        $expected = $proof->files->map(fn ($file) => $file->url)->all();
        $this->assertSame($expected, array_column($response->json('data.files'), 'url'));
        $this->assertSame([0, 1, 2], array_column($response->json('data.files'), 'sort_order'));
        $this->assertArrayNotHasKey('url', $response->json('data'));
    }

    public function test_show_returns_empty_files_array_when_proof_has_no_files(): void
    {
        $proof = PlayerStudentProof::factory()->create();

        $this->getJson(self::BASE . '/' . $proof->id_player_student_proof)
            ->assertOk()
            ->assertJsonPath('data.files', []);
    }

    public function test_index_includes_files_with_urls_per_proof(): void
    {
        $one = PlayerStudentProof::factory()->withFiles(1)->create(['ready_for_review' => true]);
        $three = PlayerStudentProof::factory()->withFiles(3)->create(['ready_for_review' => true]);

        $response = $this->getJson(self::BASE)->assertOk();

        $byId = collect($response->json('data'))->keyBy('id_player_student_proof');
        $this->assertCount(1, $byId[$one->id_player_student_proof]['files']);
        $this->assertCount(3, $byId[$three->id_player_student_proof]['files']);
        $this->assertSame(
            $three->files->map(fn ($file) => $file->url)->all(),
            array_column($byId[$three->id_player_student_proof]['files'], 'url')
        );
    }

    public function test_managers_cannot_create_proofs_or_upload_files(): void
    {
        $this->postJson(self::BASE, [])->assertStatus(405);

        $proof = PlayerStudentProof::factory()->create();
        $this->postJson(self::BASE . '/' . $proof->id_player_student_proof . '/files', [])->assertStatus(404);
        $this->assertSame(0, $proof->files()->count());
    }

    public function test_approve_marks_the_proof_approved_and_active(): void
    {
        $proof = PlayerStudentProof::factory()->withFiles(2)->create(['ready_for_review' => true]);
        $until = Carbon::now()->addYear()->format('Y-m-d');

        $this->patchJson(self::BASE . '/' . $proof->id_player_student_proof . '/approve', [
            'review_message' => 'Looks good',
            'student_until' => $until,
        ])->assertOk()
            ->assertJsonPath('data.review_status', 'approved')
            ->assertJsonPath('data.review_message', 'Looks good')
            ->assertJsonPath('data.student_until', $until)
            ->assertJsonCount(2, 'data.files');

        $this->assertTrue(PlayerStudentProof::hasActiveFor((int) $proof->player_user_id));
        $this->assertSame(2, $proof->files()->count());
    }

    public function test_reject_marks_the_proof_rejected(): void
    {
        $proof = PlayerStudentProof::factory()->withFiles()->create(['ready_for_review' => true]);

        $this->patchJson(self::BASE . '/' . $proof->id_player_student_proof . '/reject', [
            'review_message' => 'Blurry',
        ])->assertOk()
            ->assertJsonPath('data.review_status', 'rejected')
            ->assertJsonPath('data.review_message', 'Blurry')
            ->assertJsonCount(1, 'data.files');

        $this->assertFalse(PlayerStudentProof::hasActiveFor((int) $proof->player_user_id));
        $this->assertSame(AssessmentReviewStatus::Rejected, $proof->fresh()->review_status);
    }

    public function test_already_reviewed_proofs_cannot_be_reviewed_again(): void
    {
        $proof = PlayerStudentProof::factory()->rejected()->create();

        $this->patchJson(self::BASE . '/' . $proof->id_player_student_proof . '/approve')->assertStatus(422);
        $this->patchJson(self::BASE . '/' . $proof->id_player_student_proof . '/reject')->assertStatus(422);
    }

    public function test_approve_validates_student_until_format(): void
    {
        $proof = PlayerStudentProof::factory()->create(['ready_for_review' => true]);

        $this->patchJson(self::BASE . '/' . $proof->id_player_student_proof . '/approve', [
            'student_until' => '31/12/2030',
        ])->assertStatus(422);
    }

    public function test_deleting_a_proof_removes_its_child_rows_and_disk_files(): void
    {
        \Illuminate\Support\Facades\Storage::fake(config('filesystems.user_uploads_disk', 'public'));
        $disk = \Illuminate\Support\Facades\Storage::disk(config('filesystems.user_uploads_disk', 'public'));

        $proof = PlayerStudentProof::factory()->withFiles(2)->create();
        $paths = $proof->files->pluck('student_proof_path')->all();
        foreach ($paths as $path) {
            $disk->put($path, 'x');
        }

        $proof->delete();

        $this->assertDatabaseCount('player_student_proof_files', 0);
        foreach ($paths as $path) {
            $disk->assertMissing($path);
        }
    }

    public function test_requires_manager_role(): void
    {
        Sanctum::actingAs(User::factory()->playerOnly()->create());

        $this->getJson(self::BASE)->assertStatus(403);
    }
}
