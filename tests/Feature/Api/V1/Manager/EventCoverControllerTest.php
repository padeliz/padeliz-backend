<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\EventCoverImage;
use App\Models\PadelEvent;
use App\Models\User;
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

    private User $manager;

    private PadelEvent $event;

    private string $disk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->disk = config('filesystems.user_uploads_disk', 'public');
        Storage::fake($this->disk);

        $this->manager = User::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        $this->event = PadelEvent::factory()->create();
    }

    public function test_edition_cover_upload_requires_manager_authentication(): void
    {
        $this->post(
            "/api/v1/manager/events/{$this->event->id_event}/cover-images",
            ['file' => $this->createFakeImage('cover.png')],
            ['Accept' => 'application/json']
        )->assertStatus(401);
    }

    public function test_manager_can_upload_cover_via_legacy_alias_and_cover_images_route(): void
    {
        Sanctum::actingAs($this->manager);

        $legacy = $this->post(
            "/api/v1/manager/events/{$this->event->id_event}/cover",
            ['file' => $this->createFakeImage('cover.png', 150, 100)],
            ['Accept' => 'application/json']
        );

        $legacy->assertStatus(200)
            ->assertJson(['message' => 'PadelEvent cover uploaded successfully'])
            ->assertJsonPath('data.cover_images.0.aspect_ratio', 1.5);

        $this->assertNotEmpty($legacy->json('data.cover_url'));
        $this->assertCount(1, $this->event->fresh()->coverImages);

        $second = $this->post(
            "/api/v1/manager/events/{$this->event->id_event}/cover-images",
            ['file' => $this->createFakeImage('cover-two.png', 200, 100)],
            ['Accept' => 'application/json']
        )->assertStatus(200);

        $second->assertJsonCount(2, 'data.cover_images');
        $this->assertSame(2.0, (float) $second->json('data.cover_images.1.aspect_ratio'));
        $this->assertCount(2, $this->event->fresh()->coverImages);
    }

    public function test_manager_cannot_upload_more_than_two_cover_images(): void
    {
        Sanctum::actingAs($this->manager);

        EventCoverImage::factory()->create([
            'event_id' => $this->event->id_event,
            'sort_order' => 0,
        ]);
        EventCoverImage::factory()->create([
            'event_id' => $this->event->id_event,
            'sort_order' => 1,
        ]);

        $this->post(
            "/api/v1/manager/events/{$this->event->id_event}/cover-images",
            ['file' => $this->createFakeImage('cover-three.png')],
            ['Accept' => 'application/json']
        )->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_manager_can_delete_cover_but_not_the_last_one(): void
    {
        Sanctum::actingAs($this->manager);

        $first = EventCoverImage::factory()->create([
            'event_id' => $this->event->id_event,
            'sort_order' => 0,
        ]);
        $second = EventCoverImage::factory()->create([
            'event_id' => $this->event->id_event,
            'sort_order' => 1,
        ]);

        $this->deleteJson(
            "/api/v1/manager/events/{$this->event->id_event}/cover-images/{$first->id_event_cover_image}"
        )->assertStatus(200)
            ->assertJsonCount(1, 'data.cover_images');

        $this->deleteJson(
            "/api/v1/manager/events/{$this->event->id_event}/cover-images/{$second->id_event_cover_image}"
        )->assertStatus(422)
            ->assertJsonValidationErrors(['cover_images']);
    }

    public function test_edition_cover_upload_rejects_non_image(): void
    {
        Sanctum::actingAs($this->manager);

        $this->post(
            "/api/v1/manager/events/{$this->event->id_event}/cover-images",
            ['file' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain')],
            ['Accept' => 'application/json']
        )->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_edition_cover_save_failure_returns_500_not_file_message(): void
    {
        Sanctum::actingAs($this->manager);

        EventCoverImage::saving(function () {
            throw new QueryException(
                'mysql',
                'insert into pm_event_cover_images',
                [],
                new PDOException('SQLSTATE[42S22]: Column not found')
            );
        });

        $response = $this->post(
            "/api/v1/manager/events/{$this->event->id_event}/cover-images",
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
        $this->assertSame(0, $this->event->fresh()->coverImages()->count());
    }

    public function test_edition_cover_storage_failure_returns_422_file_message(): void
    {
        Sanctum::actingAs($this->manager);

        Storage::shouldReceive('disk')->andReturnSelf();
        Storage::shouldReceive('putFile')->andReturn(false);
        Storage::shouldReceive('delete')->andReturn(true);

        $this->post(
            "/api/v1/manager/events/{$this->event->id_event}/cover-images",
            ['file' => $this->createFakeImage('cover.png')],
            ['Accept' => 'application/json']
        )->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'The cover failed to upload. Please try again with a different file.',
            ]);
    }
}
