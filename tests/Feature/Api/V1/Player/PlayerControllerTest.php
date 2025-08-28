<?php

namespace Tests\Feature\Api\V1\Player;

use Tests\TestCase;
use Tests\Helpers\FileUploadTestHelper;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class PlayerControllerTest extends TestCase
{
    use RefreshDatabase, FileUploadTestHelper;

    private $player;

    public function setUp(): void
    {
        parent::setUp();
        $this->setupFileStorage();

        $this->player = Player::factory()->playerOnly()->create([
            'name' => 'Test Player',
            'phone' => '1234567890',
            'google_id' => 'google123',
            'google_name' => 'Test Player',
            'google_email' => 'player@example.com',
            'google_avatar' => 'https://example.com/avatar.jpg',
            'user_types' => ['player']
        ]);
    }

    public function tearDown(): void
    {
        $this->cleanupUploadedFiles();
        parent::tearDown();
    }

    public function test_player_can_store_profile()
    {
        Sanctum::actingAs($this->player);

        $response = $this->postJson('/api/v1/player/players', [
            'name' => 'Updated Player',
            'google_email' => 'updated@example.com',
            'phone' => '0987654321',
            'skill_division' => 4.5,
            'being_student' => true
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id_player',
                'name',
                'phone',
                'skill_division',
                'being_student'
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Player profile updated successfully'
            ]);

        $this->assertDatabaseHas('players', [
            'id_player' => $this->player->id_player,
            'name' => 'Updated Player',
            'google_email' => 'updated@example.com',
            'phone' => '0987654321'
        ]);
    }

    public function test_player_can_view_own_profile()
    {
        Sanctum::actingAs($this->player);

        $response = $this->getJson("/api/v1/player/players/{$this->player->id_player}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id_player',
                'name',
                'uploaded_profile_urls'
                ]
            ])
            ->assertJson([
            'success' => true,
            'message' => 'Player details retrieved successfully',
            'data' => [
                'id_player' => $this->player->id_player,
                'name' => 'Test Player'
            ]
            ]);
    }

    public function test_player_can_update_own_profile()
    {
        Sanctum::actingAs($this->player);

        $response = $this->putJson("/api/v1/player/players/{$this->player->id_player}", [
            'name' => 'Updated Player Name',
            'skill_division' => 3.5
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id_player',
                    'name',
                    'skill_division'
                ]
            ])
            ->assertJson([
            'success' => true,
            'message' => 'Player profile updated successfully',
            'data' => [
                'id_player' => $this->player->id_player,
                'name' => 'Updated Player Name',
                'skill_division' => 3.5
            ]
            ]);
    }

    public function test_player_can_view_other_player_profile()
    {
        Sanctum::actingAs($this->player);

        $otherPlayer = Player::factory()->create([
            'name' => 'Other Player',
            'google_email' => 'other@example.com'
        ]);

        $response = $this->getJson("/api/v1/player/players/{$otherPlayer->id_player}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id_player',
                    'name'
                ]
            ])
            ->assertJson([
            'success' => true,
            'message' => 'Player details retrieved successfully',
            'data' => [
                'id_player' => $otherPlayer->id_player,
                'name' => 'Other Player'
            ]
            ]);
    }

    public function test_player_can_upload_profile_image()
    {
        Sanctum::actingAs($this->player);

        $file = $this->createFakeImage('profile.png');

        $response = $this->postJson('/api/v1/player/players/image', [
            'image' => $file
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
            'success',
                'message',
                'data' => [
                    'urls'
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Image uploaded successfully'
            ]);

        // Check that the file was actually saved
        $this->assertNotEmpty($response->json('data.urls'));

        // Verify the file exists in the expected location
        $uploadPath = $this->player->getUploadPath('images');
        $this->assertTrue(file_exists($uploadPath));

        $files = $this->player->getUploadedFiles('images');
        $this->assertCount(1, $files);
    }

    public function test_player_can_upload_multiple_profile_images()
    {
        Sanctum::actingAs($this->player);

        // Upload first image
        $file1 = $this->createFakeImage('profile1.png');
        $response1 = $this->postJson('/api/v1/player/players/image', [
            'image' => $file1
        ]);
        $response1->assertStatus(201);

        // Upload second image
        $file2 = $this->createFakeImage('profile2.png');
        $response2 = $this->postJson('/api/v1/player/players/image', [
            'image' => $file2
        ]);
        $response2->assertStatus(201);

        // Upload third image
        $file3 = $this->createFakeImage('profile3.png');
        $response3 = $this->postJson('/api/v1/player/players/image', [
            'image' => $file3
        ]);
        $response3->assertStatus(201);

        // Check that we have 3 images
        $files = $this->player->getUploadedFiles('images');
        $this->assertCount(3, $files);
    }

    public function test_player_cannot_upload_more_than_three_images()
    {
        Sanctum::actingAs($this->player);

        // Upload 3 images first - ensure each upload completes successfully
        for ($i = 1; $i <= 3; $i++) {
            $file = $this->createFakeImage("profile{$i}.png");
            $response = $this->postJson('/api/v1/player/players/image', [
                'image' => $file
            ]);
            $response->assertStatus(201);

            // Verify the image was actually uploaded
            $files = $this->player->getUploadedFiles('images');
            $this->assertCount($i, $files, "Should have {$i} images after upload {$i}");
        }

        // Verify we have exactly 3 images before trying the 4th
        $files = $this->player->getUploadedFiles('images');
        $this->assertCount(3, $files, 'Should have exactly 3 images before trying to upload the 4th');

        // Try to upload a fourth image
        $file4 = $this->createFakeImage('profile4.png');
        $response = $this->postJson('/api/v1/player/players/image', [
            'image' => $file4
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Maximum number of profile images (3) reached. Delete an existing image first.'
            ]);

        // Verify we still only have 3 images
        $files = $this->player->getUploadedFiles('images');
        $this->assertCount(3, $files, 'Should still have exactly 3 images after failed upload');
    }

    public function test_player_can_delete_profile_image()
    {
        Sanctum::actingAs($this->player);

        // First upload an image
        $file = $this->createFakeImage('profile.png');
        $uploadResponse = $this->postJson('/api/v1/player/players/image', [
            'image' => $file
        ]);
        $uploadResponse->assertStatus(201);

        // Get the filename from the uploaded files
        $files = $this->player->getUploadedFiles('images');
        $this->assertCount(1, $files);
        $filename = $files[0];

        // Then delete it
        $response = $this->deleteJson("/api/v1/player/players/image/{$filename}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'urls'
                ]
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Image deleted successfully'
            ]);

        // Verify the file was deleted
        $filesAfterDelete = $this->player->getUploadedFiles('images');
        $this->assertCount(0, $filesAfterDelete);
    }

    public function test_player_cannot_delete_nonexistent_image()
    {
        Sanctum::actingAs($this->player);

        $response = $this->deleteJson('/api/v1/player/players/image/nonexistent-file.png');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Image not found'
            ]);
    }

    public function test_image_upload_validation()
    {
        Sanctum::actingAs($this->player);

        // Test with no file
        $response = $this->postJson('/api/v1/player/players/image', []);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['image']);

        // Test with text file instead of image
        $textFile = $this->createFakeTextFile('test.txt');
        $response = $this->postJson('/api/v1/player/players/image', [
            'image' => $textFile
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['image']);

        // Test with file too large (create a large file)
        $largeFile = UploadedFile::fake()->create('large.jpg', 3000); // 3MB
        $response = $this->postJson('/api/v1/player/players/image', [
            'image' => $largeFile
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['image']);
    }

    public function test_player_cannot_update_other_player_profile()
    {
        Sanctum::actingAs($this->player);

        $otherPlayer = Player::factory()->create([
            'name' => 'Other Player',
            'google_email' => 'other@example.com'
        ]);

        $response = $this->putJson("/api/v1/player/players/{$otherPlayer->id_player}", [
            'name' => 'Hacked Name'
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You can only update your own profile'
            ]);
    }

    public function test_profile_requires_authentication()
    {
        $response = $this->postJson('/api/v1/player/players', [
            'name' => 'Test'
        ]);

        $response->assertStatus(401);
    }

    public function test_profile_validation()
    {
        Sanctum::actingAs($this->player);

        $response = $this->postJson('/api/v1/player/players', [
            // Missing required fields
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'google_email', 'phone']);
    }

    public function test_image_upload_requires_authentication()
    {
        $file = $this->createFakeImage('profile.png');

        $response = $this->postJson('/api/v1/player/players/image', [
            'image' => $file
        ]);

        $response->assertStatus(401);
    }

    public function test_image_delete_requires_authentication()
    {
        $response = $this->deleteJson('/api/v1/player/players/image/test.png');

        $response->assertStatus(401);
    }
}
