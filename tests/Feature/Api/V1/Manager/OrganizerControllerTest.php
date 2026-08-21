<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Edition;
use App\Models\Organizer;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Helpers\FileUploadTestHelper;
use Tests\TestCase;

final class OrganizerControllerTest extends TestCase
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
    }

    public function test_organizers_require_manager_authentication(): void
    {
        $this->getJson('/api/v1/manager/organizers')->assertStatus(401);
    }

    public function test_regular_player_cannot_access_organizer_management(): void
    {
        Sanctum::actingAs(Player::factory()->playerOnly()->create());

        $this->getJson('/api/v1/manager/organizers')->assertStatus(403);
    }

    public function test_manager_can_list_organizers(): void
    {
        Sanctum::actingAs($this->manager);

        Organizer::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/manager/organizers');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id_organizer',
                        'name',
                        'logo_path',
                        'url',
                        'show_edition_label',
                        'inserted_at',
                        'updated_at',
                    ],
                ],
                'meta' => ['current_page', 'per_page', 'total'],
            ]);

        // 3 created in this test (no migration seed rows)
        $this->assertSame(3, $response->json('meta.total'));
    }

    public function test_manager_can_show_organizer(): void
    {
        Sanctum::actingAs($this->manager);

        $organizer = Organizer::factory()->create(['name' => 'Show Organizer']);

        $this->getJson("/api/v1/manager/organizers/{$organizer->id_organizer}")
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id_organizer' => $organizer->id_organizer,
                    'name' => 'Show Organizer',
                ],
            ]);
    }

    public function test_manager_can_create_organizer(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/organizers', [
            'name' => 'Padeliz Open Tour',
            'show_edition_label' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Padeliz Open Tour')
            ->assertJsonPath('data.show_edition_label', true)
            ->assertJsonPath('data.logo_path', null)
            ->assertJsonPath('data.url', null);

        $this->assertDatabaseHas('organizers', [
            'name' => 'Padeliz Open Tour',
            'show_edition_label' => 1,
        ]);
    }

    public function test_organizer_creation_defaults_show_edition_label_to_false(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson('/api/v1/manager/organizers', ['name' => 'No Label Tour'])
            ->assertStatus(201)
            ->assertJsonPath('data.show_edition_label', false);
    }

    public function test_organizer_creation_validation(): void
    {
        Sanctum::actingAs($this->manager);

        $this->postJson('/api/v1/manager/organizers', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        Organizer::factory()->create(['name' => 'Duplicate Tour']);

        $this->postJson('/api/v1/manager/organizers', ['name' => 'Duplicate Tour'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_manager_can_update_and_patch_organizer(): void
    {
        Sanctum::actingAs($this->manager);

        $organizer = Organizer::factory()->create([
            'name' => 'Old Name',
            'show_edition_label' => false,
        ]);

        $this->putJson("/api/v1/manager/organizers/{$organizer->id_organizer}", [
            'name' => 'New Name',
            'show_edition_label' => true,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.show_edition_label', true);

        $this->patchJson("/api/v1/manager/organizers/{$organizer->id_organizer}", [
            'show_edition_label' => false,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.show_edition_label', false);
    }

    public function test_manager_can_delete_unused_organizer(): void
    {
        Sanctum::actingAs($this->manager);

        $organizer = Organizer::factory()->create();

        $this->deleteJson("/api/v1/manager/organizers/{$organizer->id_organizer}")
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Organizer deleted successfully',
            ]);

        $this->assertDatabaseMissing('organizers', [
            'id_organizer' => $organizer->id_organizer,
        ]);
    }

    public function test_organizer_delete_is_restricted_by_editions(): void
    {
        Sanctum::actingAs($this->manager);

        $organizer = Organizer::factory()->create();
        Edition::factory()->create(['organizer_id' => $organizer->id_organizer]);

        $response = $this->deleteJson("/api/v1/manager/organizers/{$organizer->id_organizer}");

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'message' => 'Organizer cannot be deleted because it has linked data. Please remove all associated editions first.',
            ])
            ->assertJsonPath('errors.linked_data.editions_count', 1);

        $this->assertDatabaseHas('organizers', [
            'id_organizer' => $organizer->id_organizer,
        ]);
    }

    public function test_manager_can_bulk_delete_organizers(): void
    {
        Sanctum::actingAs($this->manager);

        $first = Organizer::factory()->create();
        $second = Organizer::factory()->create();

        $this->deleteJson('/api/v1/manager/organizers/bulk', [
            'organizer_ids' => [$first->id_organizer, $second->id_organizer],
        ])->assertStatus(200)
            ->assertJsonPath('data.total_successful', 2);

        $this->assertDatabaseMissing('organizers', ['id_organizer' => $first->id_organizer]);
        $this->assertDatabaseMissing('organizers', ['id_organizer' => $second->id_organizer]);
    }

    public function test_manager_can_upload_and_delete_organizer_logo(): void
    {
        Sanctum::actingAs($this->manager);

        $organizer = Organizer::factory()->create(['logo_path' => null]);
        $disk = config('filesystems.user_uploads_disk', 'public');

        $upload = $this->post(
            "/api/v1/manager/organizers/{$organizer->id_organizer}/logo",
            ['file' => $this->createFakeImage('organizer.png')],
            ['Accept' => 'application/json']
        );

        $upload->assertStatus(200)
            ->assertJson(['message' => 'Organizer logo uploaded successfully']);

        $storedPath = $upload->json('data.logo_path');
        $this->assertNotEmpty($storedPath);
        $this->assertNotEmpty($upload->json('data.url'));
        $this->assertStringStartsWith('organizers/' . $organizer->id_organizer . '/logo/', $storedPath);
        Storage::disk($disk)->assertExists($storedPath);

        $delete = $this->deleteJson("/api/v1/manager/organizers/{$organizer->id_organizer}/logo");

        $delete->assertStatus(200)
            ->assertJsonPath('data.logo_path', null)
            ->assertJsonPath('data.url', null);
        Storage::disk($disk)->assertMissing($storedPath);
    }

    public function test_organizer_logo_upload_replaces_the_previous_file(): void
    {
        Sanctum::actingAs($this->manager);

        $organizer = Organizer::factory()->create(['logo_path' => null]);
        $disk = config('filesystems.user_uploads_disk', 'public');

        $first = $this->post(
            "/api/v1/manager/organizers/{$organizer->id_organizer}/logo",
            ['file' => $this->createFakeImage('one.png')],
            ['Accept' => 'application/json']
        )->assertStatus(200);

        $second = $this->post(
            "/api/v1/manager/organizers/{$organizer->id_organizer}/logo",
            ['file' => $this->createFakeImage('two.png')],
            ['Accept' => 'application/json']
        )->assertStatus(200);

        $firstPath = $first->json('data.logo_path');
        $secondPath = $second->json('data.logo_path');

        $this->assertNotSame($firstPath, $secondPath);
        Storage::disk($disk)->assertMissing($firstPath);
        Storage::disk($disk)->assertExists($secondPath);
    }

    public function test_organizer_logo_upload_rejects_non_image(): void
    {
        Sanctum::actingAs($this->manager);

        $organizer = Organizer::factory()->create();

        $this->post(
            "/api/v1/manager/organizers/{$organizer->id_organizer}/logo",
            ['file' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain')],
            ['Accept' => 'application/json']
        )->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }
}
