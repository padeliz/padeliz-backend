<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\User;
use App\Models\UserEmail;
use App\Models\PlayerProfileImage;
use App\Models\PlayerProfileImageImport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Helpers\FileUploadTestHelper;
use Tests\TestCase;

class PlayerProfileImageImportControllerTest extends TestCase
{
    use FileUploadTestHelper;
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = User::factory()->manager()->create();
    }

    public function test_preview_maps_email_filename(): void
    {
        Sanctum::actingAs($this->manager);
        $player = User::factory()->create();
        UserEmail::factory()->create([
            'user_id' => $player->id_user,
            'email' => 'mara@example.test',
        ]);

        $response = $this->postJson('/api/v1/manager/players/profile-images/import/preview', [
            'filenames' => ['mara@example.test.jpg'],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.max_images_per_player', 10)
            ->assertJsonPath('data.rows.0.mapping.status', 'mapped')
            ->assertJsonPath('data.rows.0.mapping.matched_by', 'email')
            ->assertJsonPath('data.rows.0.mapping.player.id_user', $player->id_user);
    }

    public function test_save_uploads_included_rows(): void
    {
        Sanctum::actingAs($this->manager);
        $player = User::factory()->create();
        UserEmail::factory()->create([
            'user_id' => $player->id_user,
            'email' => 'mara@example.test',
        ]);

        $file = $this->createFakeImage('mara@example.test.jpg');
        $rows = json_encode([
            [
                'index' => 0,
                'filename' => 'mara@example.test.jpg',
                'player_user_id' => $player->id_user,
                'is_pdf_primary' => true,
            ],
        ]);

        $response = $this->post('/api/v1/manager/players/profile-images/import', [
            'original_filename' => 'mara@example.test.jpg',
            'rows' => $rows,
            'files' => [$file],
        ], ['Accept' => 'application/json']);

        $response->assertCreated();
        $this->assertSame(1, PlayerProfileImage::query()->count());
        $this->assertSame(1, PlayerProfileImageImport::query()->count());
        $image = PlayerProfileImage::query()->first();
        $this->assertTrue((bool) $image?->is_pdf_primary);
        $this->assertSame('import', $image?->uploaded_via);
        $this->assertSame('mara@example.test.jpg', $image?->original_filename);
    }

    public function test_preview_name_filename_matches_diacritic_player(): void
    {
        Sanctum::actingAs($this->manager);
        $player = User::factory()->create([
            'first_name' => 'Ștefan',
            'middle_name' => null,
            'last_name' => 'Popescu',
        ]);

        $response = $this->postJson('/api/v1/manager/players/profile-images/import/preview', [
            'filenames' => ['Stefan Popescu.jpg'],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.rows.0.mapping.status', 'unmapped')
            ->assertJsonPath('data.rows.0.mapping.name_matches.0.id_user', $player->id_user);
    }

    public function test_preview_allows_duplicate_filenames(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/players/profile-images/import/preview', [
            'filenames' => ['same.jpg', 'same.jpg'],
        ]);

        $response->assertOk();
        $this->assertCount(2, $response->json('data.rows'));
    }

    public function test_save_rejects_cap_exceeded(): void
    {
        Sanctum::actingAs($this->manager);
        $player = User::factory()->create();
        for ($i = 0; $i < PlayerProfileImage::MAX_PER_PLAYER; $i++) {
            PlayerProfileImage::factory()->create(['player_user_id' => $player->id_user]);
        }

        $file = $this->createFakeImage('extra.jpg');
        $rows = json_encode([
            [
                'index' => 0,
                'filename' => 'extra.jpg',
                'player_user_id' => $player->id_user,
                'is_pdf_primary' => false,
            ],
        ]);

        $response = $this->post('/api/v1/manager/players/profile-images/import', [
            'original_filename' => 'extra.jpg',
            'rows' => $rows,
            'files' => [$file],
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422);
        $this->assertSame(PlayerProfileImage::MAX_PER_PLAYER, PlayerProfileImage::query()->count());
    }

    public function test_set_pdf_primary_clears_siblings(): void
    {
        Sanctum::actingAs($this->manager);
        $player = User::factory()->create();
        $first = PlayerProfileImage::factory()->create([
            'player_user_id' => $player->id_user,
            'is_pdf_primary' => true,
            'sort_order' => 0,
        ]);
        $second = PlayerProfileImage::factory()->create([
            'player_user_id' => $player->id_user,
            'is_pdf_primary' => false,
            'sort_order' => 1,
        ]);

        $response = $this->postJson(
            "/api/v1/manager/players/{$player->id_user}/profile-images/{$second->id}/pdf-primary"
        );

        $response->assertOk();

        $this->assertFalse((bool) $first->fresh()?->is_pdf_primary);
        $this->assertTrue((bool) $second->fresh()?->is_pdf_primary);
    }
}
