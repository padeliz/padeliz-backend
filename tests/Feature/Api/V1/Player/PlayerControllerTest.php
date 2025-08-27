<?php

namespace Tests\Feature\Api\V1\Player;

use Tests\TestCase;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class PlayerControllerTest extends TestCase
{
    use RefreshDatabase;

    private $player;

    public function setUp(): void
    {
        parent::setUp();

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
                    'name'
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
        Storage::fake('local');

        // Skip this test if GD extension is not available or imagejpeg function is not available
        if (!extension_loaded('gd') || !function_exists('imagejpeg')) {
            $this->markTestSkipped('GD extension or imagejpeg function not available for image testing');
        }

        $file = UploadedFile::fake()->image('profile.jpg');

        $response = $this->postJson('/api/v1/player/players/image', [
            'image' => $file
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'filename'
            ]);

        $this->assertTrue(Storage::disk('local')->exists('uploads/images/' . $response->json('filename')));
    }

    public function test_player_can_delete_profile_image()
    {
        Sanctum::actingAs($this->player);
        Storage::fake('local');

        // Skip this test if GD extension is not available or imagejpeg function is not available
        if (!extension_loaded('gd') || !function_exists('imagejpeg')) {
            $this->markTestSkipped('GD extension or imagejpeg function not available for image testing');
        }

        // First upload an image
        $file = UploadedFile::fake()->image('profile.jpg');
        $uploadResponse = $this->postJson('/api/v1/player/players/image', [
            'image' => $file
        ]);
        $filename = $uploadResponse->json('filename');

        // Then delete it
        $response = $this->deleteJson("/api/v1/player/players/image/{$filename}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Image deleted successfully']);

        $this->assertFalse(Storage::disk('local')->exists('uploads/images/' . $filename));
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
}
