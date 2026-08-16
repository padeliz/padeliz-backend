<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\Eligibility;
use App\Models\EligibilityPortraitPhoto;
use App\Models\EligibilityStudentProof;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Helpers\FileUploadTestHelper;
use Tests\TestCase;

class MyEligibilityUploadControllerTest extends TestCase
{
    use RefreshDatabase;
    use FileUploadTestHelper;

    private string $uploadsDisk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->uploadsDisk = config('filesystems.user_uploads_disk', 'public');
        Storage::fake($this->uploadsDisk);
    }

    private function actingPlayer(): Player
    {
        $player = Player::factory()->playerOnly()->create();

        return $player;
    }

    private function createPendingEligibility(Player $player, bool $beingStudent = true): Eligibility
    {
        return Eligibility::factory()->pending()->create([
            'player_id' => $player->id_player,
            'being_student' => $beingStudent,
            'submitted_at' => time(),
            'inserted_at' => time(),
        ]);
    }

    public function test_upload_routes_require_authentication(): void
    {
        $this->postJson('/api/v1/app/my/eligibility/pending/student-proof', [])->assertUnauthorized();

        $this->deleteJson('/api/v1/app/my/eligibility/pending/student-proof/1')->assertUnauthorized();

        $this->postJson('/api/v1/app/my/eligibility/pending/portrait-photo', [])->assertUnauthorized();

        $this->deleteJson('/api/v1/app/my/eligibility/pending/portrait-photo/1')->assertUnauthorized();
    }

    public function test_student_proof_post_returns_404_without_pending(): void
    {
        $player = $this->actingPlayer();
        Sanctum::actingAs($player);

        $this->post('/api/v1/app/my/eligibility/pending/student-proof', [
            'file' => UploadedFile::fake()->create('proof.pdf', 50, 'application/pdf'),
        ])->assertNotFound();
    }

    public function test_student_proof_post_422_when_not_student(): void
    {
        $player = $this->actingPlayer();
        Sanctum::actingAs($player);
        $this->createPendingEligibility($player, false);

        $this->post('/api/v1/app/my/eligibility/pending/student-proof', [
            'file' => UploadedFile::fake()->create('proof.pdf', 50, 'application/pdf'),
        ])
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Student proof uploads require being_student to be enabled on your pending eligibility.',
            ]);
    }

    public function test_student_proof_post_422_when_being_student_null(): void
    {
        $player = $this->actingPlayer();
        Sanctum::actingAs($player);
        $this->createPendingEligibility($player, false);
        Eligibility::where('player_id', $player->id_player)->update(['being_student' => null]);

        $this->post('/api/v1/app/my/eligibility/pending/student-proof', [
            'file' => UploadedFile::fake()->create('proof.pdf', 50, 'application/pdf'),
        ])->assertStatus(422);
    }

    public function test_student_proof_upload_pdf_and_image_when_student(): void
    {
        $player = $this->actingPlayer();
        Sanctum::actingAs($player);
        $this->createPendingEligibility($player, true);

        $pdf = UploadedFile::fake()->create('proof.pdf', 200, 'application/pdf');
        $r = $this->post('/api/v1/app/my/eligibility/pending/student-proof', ['file' => $pdf]);
        $r->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['student_proofs', 'portrait_photos']]);

        $this->assertCount(1, $r->json('data.student_proofs'));

        $png = $this->createFakeImage('scan.png');
        $r2 = $this->post('/api/v1/app/my/eligibility/pending/student-proof', ['file' => $png]);
        $r2->assertCreated();
        $this->assertCount(2, $r2->json('data.student_proofs'));
    }

    public function test_student_proof_max_three(): void
    {
        $player = $this->actingPlayer();
        Sanctum::actingAs($player);
        $this->createPendingEligibility($player, true);

        for ($i = 0; $i < 3; $i++) {
            $this->post('/api/v1/app/my/eligibility/pending/student-proof', [
                'file' => UploadedFile::fake()->create("p{$i}.pdf", 100, 'application/pdf'),
            ])->assertCreated();
        }

        $this->post('/api/v1/app/my/eligibility/pending/student-proof', [
            'file' => UploadedFile::fake()->create('extra.pdf', 100, 'application/pdf'),
        ])
            ->assertStatus(422)
            ->assertJsonFragment(['success' => false]);
    }

    public function test_student_proof_delete_and_forbidden_for_other_player(): void
    {
        $player = $this->actingPlayer();
        $other = $this->actingPlayer();
        Sanctum::actingAs($player);
        $this->createPendingEligibility($player, true);

        $id = $this->post('/api/v1/app/my/eligibility/pending/student-proof', [
            'file' => UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf'),
        ])->json('data.student_proofs.0.id');

        $proofPath = EligibilityStudentProof::findOrFail($id)->student_proof_path;
        Storage::disk($this->uploadsDisk)->assertExists($proofPath);

        Sanctum::actingAs($other);
        $this->deleteJson("/api/v1/app/my/eligibility/pending/student-proof/{$id}")
            ->assertForbidden();

        Sanctum::actingAs($player);
        Storage::disk($this->uploadsDisk)->assertExists($proofPath);
        $this->deleteJson("/api/v1/app/my/eligibility/pending/student-proof/{$id}")
            ->assertOk()
            ->assertJsonPath('data.student_proofs', []);

        $this->assertDatabaseMissing('eligibility_student_proofs', ['id' => $id]);
        Storage::disk($this->uploadsDisk)->assertMissing($proofPath);
    }

    public function test_portrait_photo_upload_without_student_gate(): void
    {
        $player = $this->actingPlayer();
        Sanctum::actingAs($player);
        $this->createPendingEligibility($player, false);

        $file = $this->createFakeImage('face.png');
        $r = $this->post('/api/v1/app/my/eligibility/pending/portrait-photo', ['file' => $file]);
        $r->assertCreated();
        $this->assertCount(1, $r->json('data.portrait_photos'));
    }

    public function test_portrait_max_three(): void
    {
        $player = $this->actingPlayer();
        Sanctum::actingAs($player);
        $this->createPendingEligibility($player, true);

        for ($i = 0; $i < 3; $i++) {
            $this->post('/api/v1/app/my/eligibility/pending/portrait-photo', [
                'file' => $this->createFakeImage("f{$i}.png"),
            ])->assertCreated();
        }

        $this->post('/api/v1/app/my/eligibility/pending/portrait-photo', [
            'file' => $this->createFakeImage('extra.png'),
        ])->assertStatus(422);
    }

    public function test_portrait_photo_delete_and_forbidden_for_other_player(): void
    {
        $player = $this->actingPlayer();
        $other = $this->actingPlayer();
        Sanctum::actingAs($player);
        $this->createPendingEligibility($player, true);

        $id = $this->post('/api/v1/app/my/eligibility/pending/portrait-photo', [
            'file' => $this->createFakeImage('p.png'),
        ])->json('data.portrait_photos.0.id');

        $photoPath = EligibilityPortraitPhoto::findOrFail($id)->portrait_photo_path;
        Storage::disk($this->uploadsDisk)->assertExists($photoPath);

        Sanctum::actingAs($other);
        $this->deleteJson("/api/v1/app/my/eligibility/pending/portrait-photo/{$id}")
            ->assertForbidden();

        Sanctum::actingAs($player);
        Storage::disk($this->uploadsDisk)->assertExists($photoPath);
        $this->deleteJson("/api/v1/app/my/eligibility/pending/portrait-photo/{$id}")
            ->assertOk()
            ->assertJsonPath('data.portrait_photos', []);

        $this->assertDatabaseMissing('eligibility_portrait_photos', ['id' => $id]);
        Storage::disk($this->uploadsDisk)->assertMissing($photoPath);
    }

    public function test_patch_being_student_false_keeps_proofs_but_blocks_new_student_upload(): void
    {
        $player = $this->actingPlayer();
        Sanctum::actingAs($player);
        $this->createPendingEligibility($player, true);

        $this->post('/api/v1/app/my/eligibility/pending/student-proof', [
            'file' => UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf'),
        ])->assertCreated();

        $this->patchJson('/api/v1/app/my/eligibility/pending', [
            'being_student' => false,
        ])->assertOk();

        $this->assertDatabaseCount('eligibility_student_proofs', 1);

        $this->post('/api/v1/app/my/eligibility/pending/student-proof', [
            'file' => UploadedFile::fake()->create('proof2.pdf', 100, 'application/pdf'),
        ])->assertStatus(422);
    }

    public function test_discard_deletes_upload_rows_and_storage(): void
    {
        $player = $this->actingPlayer();
        Sanctum::actingAs($player);
        $this->createPendingEligibility($player, true);

        $this->post('/api/v1/app/my/eligibility/pending/student-proof', [
            'file' => UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf'),
        ])->assertCreated();

        $this->post('/api/v1/app/my/eligibility/pending/portrait-photo', [
            'file' => $this->createFakeImage('p.png'),
        ])->assertCreated();

        $proofPath = EligibilityStudentProof::first()->student_proof_path;
        $photoPath = EligibilityPortraitPhoto::first()->portrait_photo_path;

        $this->postJson('/api/v1/app/my/eligibility/discard')->assertOk();

        $this->assertDatabaseCount('eligibility_student_proofs', 0);
        $this->assertDatabaseCount('eligibility_portrait_photos', 0);
        Storage::disk($this->uploadsDisk)->assertMissing($proofPath);
        Storage::disk($this->uploadsDisk)->assertMissing($photoPath);
    }

    public function test_delete_student_proof_forbidden_when_eligibility_not_pending(): void
    {
        $player = $this->actingPlayer();
        Sanctum::actingAs($player);

        $eligibility = Eligibility::factory()->pending()->create([
            'player_id' => $player->id_player,
            'being_student' => true,
            'submitted_at' => time(),
            'inserted_at' => time(),
        ]);

        $proof = EligibilityStudentProof::factory()->create([
            'eligibility_id' => $eligibility->id_eligibility,
            'student_proof_path' => 'eligibility-student-proofs/999/student-proof/x.pdf',
            'sort_order' => 0,
        ]);
        Storage::disk($this->uploadsDisk)->put($proof->student_proof_path, 'x');

        $eligibility->update(['review_status' => 'approved', 'reviewed_at' => time()]);

        Sanctum::actingAs($player);
        $this->deleteJson("/api/v1/app/my/eligibility/pending/student-proof/{$proof->id}")
            ->assertForbidden();
    }
}
