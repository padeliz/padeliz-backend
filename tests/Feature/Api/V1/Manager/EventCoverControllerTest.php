<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\PadelEvent;
use App\Models\Player;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PDOException;
use Tests\Helpers\FileUploadTestHelper;
use Tests\TestCase;

final class EventCoverControllerTest extends TestCase
{
    use RefreshDatabase;
    use FileUploadTestHelper;

    private Player $manager;

    private PadelEvent $event;

    private string $disk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->disk = config('filesystems.user_uploads_disk', 'public');
        Storage::fake($this->disk);

        $this->manager = Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        $this->event = PadelEvent::factory()->create(['cover_path' => null]);
    }

    public function test_edition_cover_upload_requires_manager_authentication(): void
    {
        $this->post(
            "/api/v1/manager/events/{$this->event->id_event}/cover",
            ['file' => $this->createFakeImage('cover.png')],
            ['Accept' => 'application/json']
        )->assertStatus(401);
    }

    public function test_manager_can_upload_and_replace_edition_cover(): void
    {
        Sanctum::actingAs($this->manager);

        $upload = $this->post(
            "/api/v1/manager/events/{$this->event->id_event}/cover",
            ['file' => $this->createFakeImage('cover.png')],
            ['Accept' => 'application/json']
        );

        $upload->assertStatus(200)
            ->assertJson(['message' => 'PadelEvent cover uploaded successfully']);

        $this->assertNotEmpty($upload->json('data.cover_url'));

        $dbPath = $this->event->fresh()->cover_path;
        $this->assertNotEmpty($dbPath);
        $this->assertStringStartsWith('events/' . $this->event->id_event . '/cover/', $dbPath);
        Storage::disk($this->disk)->assertExists($dbPath);

        $second = $this->post(
            "/api/v1/manager/events/{$this->event->id_event}/cover",
            ['file' => $this->createFakeImage('cover-two.png')],
            ['Accept' => 'application/json']
        )->assertStatus(200);

        $secondPath = $this->event->fresh()->cover_path;
        $this->assertNotSame($dbPath, $secondPath);
        Storage::disk($this->disk)->assertMissing($dbPath);
        Storage::disk($this->disk)->assertExists($secondPath);
    }

    public function test_edition_cover_delete_route_is_not_registered(): void
    {
        Sanctum::actingAs($this->manager);

        $this->deleteJson("/api/v1/manager/events/{$this->event->id_event}/cover")
            ->assertStatus(405);
    }

    public function test_edition_cover_upload_rejects_non_image(): void
    {
        Sanctum::actingAs($this->manager);

        $this->post(
            "/api/v1/manager/events/{$this->event->id_event}/cover",
            ['file' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain')],
            ['Accept' => 'application/json']
        )->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_edition_cover_save_failure_returns_500_not_file_message(): void
    {
        Sanctum::actingAs($this->manager);

        PadelEvent::saving(function () {
            throw new QueryException(
                'mysql',
                'update pm_events set cover_path = ?',
                ['events/39/cover/x.png'],
                new PDOException('SQLSTATE[42S22]: Column not found: 1054 Unknown column \'cover_path\'')
            );
        });

        $response = $this->post(
            "/api/v1/manager/events/{$this->event->id_event}/cover",
            ['file' => $this->createFakeImage('cover.png')],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'message' => 'PadelEvent cover could not be saved.',
            ]);
        $this->assertStringNotContainsString(
            'different file',
            (string) $response->json('message')
        );
        $this->assertNull($this->event->fresh()->cover_path);
    }

    public function test_edition_cover_storage_failure_returns_422_file_message(): void
    {
        Sanctum::actingAs($this->manager);

        Storage::shouldReceive('disk')->andReturnSelf();
        Storage::shouldReceive('putFile')->andReturn(false);
        Storage::shouldReceive('delete')->andReturn(true);

        $this->post(
            "/api/v1/manager/events/{$this->event->id_event}/cover",
            ['file' => $this->createFakeImage('cover.png')],
            ['Accept' => 'application/json']
        )->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'The cover failed to upload. Please try again with a different file.',
            ]);
    }
}
