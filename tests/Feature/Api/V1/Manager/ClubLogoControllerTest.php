<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Club;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Helpers\FileUploadTestHelper;
use Tests\TestCase;

final class ClubLogoControllerTest extends TestCase
{
    use RefreshDatabase;
    use FileUploadTestHelper;

    private Player $manager;

    private Club $club;

    private string $disk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->disk = config('filesystems.user_uploads_disk', 'public');
        Storage::fake($this->disk);

        $this->manager = Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        $this->club = Club::factory()->create(['logo_path' => null]);
    }

    public function test_club_logo_upload_requires_manager_authentication(): void
    {
        $this->post(
            "/api/v1/manager/clubs/{$this->club->id_club}/logo",
            ['file' => $this->createFakeImage('logo.png')],
            ['Accept' => 'application/json']
        )->assertStatus(401);
    }

    public function test_manager_can_upload_and_delete_club_logo(): void
    {
        Sanctum::actingAs($this->manager);

        $upload = $this->post(
            "/api/v1/manager/clubs/{$this->club->id_club}/logo",
            ['file' => $this->createFakeImage('logo.png')],
            ['Accept' => 'application/json']
        );

        $upload->assertStatus(200)
            ->assertJson(['message' => 'Club logo uploaded successfully']);

        $storedPath = $upload->json('data.logo_path');
        $this->assertNotEmpty($storedPath);
        $this->assertNotEmpty($upload->json('data.url'));
        $this->assertStringStartsWith('clubs/' . $this->club->id_club . '/logo/', $storedPath);
        Storage::disk($this->disk)->assertExists($storedPath);

        $delete = $this->deleteJson("/api/v1/manager/clubs/{$this->club->id_club}/logo");

        $delete->assertStatus(200)
            ->assertJsonPath('data.logo_path', null)
            ->assertJsonPath('data.url', null);
        Storage::disk($this->disk)->assertMissing($storedPath);
        $this->assertNull($this->club->fresh()->logo_path);
    }

    public function test_club_logo_upload_replaces_the_previous_file(): void
    {
        Sanctum::actingAs($this->manager);

        $first = $this->post(
            "/api/v1/manager/clubs/{$this->club->id_club}/logo",
            ['file' => $this->createFakeImage('one.png')],
            ['Accept' => 'application/json']
        )->assertStatus(200);

        $second = $this->post(
            "/api/v1/manager/clubs/{$this->club->id_club}/logo",
            ['file' => $this->createFakeImage('two.png')],
            ['Accept' => 'application/json']
        )->assertStatus(200);

        Storage::disk($this->disk)->assertMissing($first->json('data.logo_path'));
        Storage::disk($this->disk)->assertExists($second->json('data.logo_path'));
    }

    public function test_club_logo_upload_rejects_non_image(): void
    {
        Sanctum::actingAs($this->manager);

        $this->post(
            "/api/v1/manager/clubs/{$this->club->id_club}/logo",
            ['file' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain')],
            ['Accept' => 'application/json']
        )->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_club_show_exposes_logo_path_and_url(): void
    {
        Sanctum::actingAs($this->manager);

        $this->post(
            "/api/v1/manager/clubs/{$this->club->id_club}/logo",
            ['file' => $this->createFakeImage('logo.png')],
            ['Accept' => 'application/json']
        )->assertStatus(200);

        $this->getJson("/api/v1/manager/clubs/{$this->club->id_club}")
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['id_club', 'name', 'city', 'logo_path', 'url'],
            ])
            ->assertJsonPath('data.logo_path', $this->club->fresh()->logo_path);
    }
}
