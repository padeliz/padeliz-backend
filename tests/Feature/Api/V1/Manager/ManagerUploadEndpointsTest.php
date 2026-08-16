<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Eligibility;
use App\Models\Player;
use App\Models\Prize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Helpers\FileUploadTestHelper;
use Tests\TestCase;

final class ManagerUploadEndpointsTest extends TestCase
{
    use RefreshDatabase;
    use FileUploadTestHelper;

    private Player $manager;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.user_uploads_disk', 'public'));
        $this->manager = Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($this->manager);
    }

    public function test_manager_can_upload_and_delete_player_profile_image(): void
    {
        $player = Player::factory()->create();
        $file = $this->createFakeImage('face.png');

        $upload = $this->post(
            "/api/v1/manager/players/{$player->id_player}/profile-images",
            ['image' => $file],
            ['Accept' => 'application/json']
        );
        $upload->assertCreated();
        $this->assertCount(1, $upload->json('data.uploaded_profile_images'));
        $childId = $upload->json('data.uploaded_profile_images.0.id');

        $delete = $this->deleteJson(
            "/api/v1/manager/players/{$player->id_player}/profile-images/{$childId}"
        );
        $delete->assertOk();
        $this->assertCount(0, $delete->json('data.uploaded_profile_images'));
    }

    public function test_eligibility_uploads_rejected_when_approved(): void
    {
        $eligibility = Eligibility::factory()->create([
            'review_status' => 'approved',
            'being_student' => true,
        ]);
        $file = $this->createFakeImage('proof.png');

        $this->post(
            "/api/v1/manager/eligibilities/{$eligibility->id_eligibility}/student-proofs",
            ['file' => $file],
            ['Accept' => 'application/json']
        )->assertForbidden();
    }

    public function test_eligibility_student_proof_upload_when_not_approved(): void
    {
        $eligibility = Eligibility::factory()->create([
            'review_status' => 'pending',
            'being_student' => true,
        ]);
        $file = $this->createFakeImage('proof.png');

        $this->post(
            "/api/v1/manager/eligibilities/{$eligibility->id_eligibility}/student-proofs",
            ['file' => $file],
            ['Accept' => 'application/json']
        )->assertCreated();
    }

    public function test_prize_media_upload_and_delete(): void
    {
        $prize = Prize::factory()->create(['media_path' => null]);
        $file = $this->createFakeImage('prize.png');

        $upload = $this->post(
            "/api/v1/manager/prizes/{$prize->id_prize}/media",
            ['file' => $file],
            ['Accept' => 'application/json']
        );
        $upload->assertOk();
        $this->assertNotEmpty($upload->json('data.media_path') ?? $upload->json('data.url'));

        $delete = $this->deleteJson("/api/v1/manager/prizes/{$prize->id_prize}/media");
        $delete->assertOk();
        $prize->refresh();
        $this->assertTrue($prize->media_path === null || $prize->media_path === '');
    }

    public function test_prize_media_rejects_invalid_mime(): void
    {
        $prize = Prize::factory()->create();
        $file = UploadedFile::fake()->create('notes.txt', 10, 'text/plain');

        $this->post(
            "/api/v1/manager/prizes/{$prize->id_prize}/media",
            ['file' => $file],
            ['Accept' => 'application/json']
        )->assertStatus(422);
    }
}
