<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\User;
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

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.user_uploads_disk', 'public'));
        $this->manager = User::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($this->manager);
    }

    public function test_manager_can_upload_and_delete_player_profile_image(): void
    {
        $player = User::factory()->create();
        $file = $this->createFakeImage('face.png');

        $upload = $this->post(
            "/api/v1/manager/players/{$player->id_user}/profile-images",
            ['image' => $file],
            ['Accept' => 'application/json']
        );
        $upload->assertCreated();
        $this->assertCount(1, $upload->json('data.uploaded_profile_images'));
        $childId = $upload->json('data.uploaded_profile_images.0.id');

        $delete = $this->deleteJson(
            "/api/v1/manager/players/{$player->id_user}/profile-images/{$childId}"
        );
        $delete->assertOk();
        $this->assertCount(0, $delete->json('data.uploaded_profile_images'));
    }

    public function test_manager_cannot_create_student_proofs(): void
    {
        $player = User::factory()->create();
        $file = $this->createFakeImage('proof.png');

        $this->post(
            '/api/v1/manager/player-student-proofs',
            ['file' => $file, 'player_user_id' => $player->id_user, 'student_until' => now()->addMonth()->format('Y-m-d')],
            ['Accept' => 'application/json']
        )->assertStatus(405);

        $this->assertDatabaseCount('player_student_proofs', 0);
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
