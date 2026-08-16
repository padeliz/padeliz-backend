<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\Player;
use App\Models\PlayerProfileImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Helpers\FileUploadTestHelper;
use Tests\TestCase;

class PlayerControllerTest extends TestCase
{
    use RefreshDatabase;
    use FileUploadTestHelper;

    private Player $player;

    public function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('filesystems.user_uploads_disk', 'public'));

        $this->player = Player::factory()->playerOnly()->create([
            'first_name' => 'Test',
            'middle_name' => null,
            'last_name' => 'Player',
            'user_types' => ['player'],
        ]);
    }

    public function test_player_can_store_profile()
    {
        Sanctum::actingAs($this->player);

        $response = $this->postJson('/api/v1/app/players', [
            'first_name' => 'Updated',
            'last_name' => 'Player',
            'skill_division' => 4.5,
            'being_student' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id_player',
                    'name',
                    'skill_division',
                    'being_student',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Player profile updated successfully',
                'data' => [
                    'name' => 'Updated Player',
                ],
            ]);

        $this->assertDatabaseHas('players', [
            'id_player' => $this->player->id_player,
            'first_name' => 'Updated',
            'last_name' => 'Player',
        ]);
    }

    public function test_player_can_view_own_profile()
    {
        Sanctum::actingAs($this->player);

        $response = $this->getJson("/api/v1/app/players/{$this->player->id_player}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id_player',
                    'name',
                    'uploaded_profile_images',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Player details retrieved successfully',
                'data' => [
                    'id_player' => $this->player->id_player,
                    'name' => 'Test Player',
                ],
            ]);
    }

    public function test_player_can_update_own_profile()
    {
        Sanctum::actingAs($this->player);

        $response = $this->putJson("/api/v1/app/players/{$this->player->id_player}", [
            'first_name' => 'Updated',
            'middle_name' => 'Player',
            'last_name' => 'Name',
            'skill_division' => 3.5,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id_player',
                    'name',
                    'skill_division',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Player profile updated successfully',
                'data' => [
                    'id_player' => $this->player->id_player,
                    'name' => 'Updated Player Name',
                    'skill_division' => 3.5,
                ],
            ]);
    }

    public function test_player_can_view_other_player_profile()
    {
        Sanctum::actingAs($this->player);

        $otherPlayer = Player::factory()->create([
            'first_name' => 'Other',
            'middle_name' => null,
            'last_name' => 'Player',
        ]);

        $response = $this->getJson("/api/v1/app/players/{$otherPlayer->id_player}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id_player',
                    'name',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Player details retrieved successfully',
                'data' => [
                    'id_player' => $otherPlayer->id_player,
                    'name' => 'Other Player',
                ],
            ]);
    }

    public function test_player_can_upload_profile_image()
    {
        Sanctum::actingAs($this->player);

        $file = $this->createFakeImage('profile.png');

        $response = $this->post('/api/v1/app/players/image', [
            'image' => $file,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'uploaded_profile_images',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Image uploaded successfully',
            ]);

        $data = $response->json('data.uploaded_profile_images');
        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('id', $data[0]);
        $this->assertArrayHasKey('url', $data[0]);

        $this->assertDatabaseHas('player_profile_images', [
            'player_id' => $this->player->id_player,
        ]);
    }

    public function test_player_can_upload_multiple_profile_images()
    {
        Sanctum::actingAs($this->player);

        $file1 = $this->createFakeImage('profile1.png');
        $response1 = $this->post('/api/v1/app/players/image', ['image' => $file1]);
        $response1->assertStatus(201);

        $file2 = $this->createFakeImage('profile2.png');
        $response2 = $this->post('/api/v1/app/players/image', ['image' => $file2]);
        $response2->assertStatus(201);

        $file3 = $this->createFakeImage('profile3.png');
        $response3 = $this->post('/api/v1/app/players/image', ['image' => $file3]);
        $response3->assertStatus(201);

        $this->assertSame(3, PlayerProfileImage::where('player_id', $this->player->id_player)->count());
    }

    public function test_player_cannot_upload_more_than_three_images()
    {
        Sanctum::actingAs($this->player);

        for ($i = 1; $i <= 3; $i++) {
            $file = $this->createFakeImage("profile{$i}.png");
            $response = $this->post('/api/v1/app/players/image', ['image' => $file]);
            $response->assertStatus(201);
            $this->assertSame($i, PlayerProfileImage::where('player_id', $this->player->id_player)->count());
        }

        $file4 = $this->createFakeImage('profile4.png');
        $response = $this->post('/api/v1/app/players/image', ['image' => $file4]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Maximum number of profile images (3) reached. Delete an existing image first.',
            ]);

        $this->assertSame(3, PlayerProfileImage::where('player_id', $this->player->id_player)->count());
    }

    public function test_player_can_delete_profile_image()
    {
        Sanctum::actingAs($this->player);

        $file = $this->createFakeImage('profile.png');
        $uploadResponse = $this->post('/api/v1/app/players/image', ['image' => $file]);
        $uploadResponse->assertStatus(201);

        $id = $uploadResponse->json('data.uploaded_profile_images.0.id');
        $this->assertNotNull($id);

        $response = $this->deleteJson("/api/v1/app/players/image/{$id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'uploaded_profile_images',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Image deleted successfully',
            ]);

        $this->assertSame(0, PlayerProfileImage::where('player_id', $this->player->id_player)->count());
    }

    public function test_player_cannot_delete_nonexistent_image()
    {
        Sanctum::actingAs($this->player);

        $response = $this->deleteJson('/api/v1/app/players/image/999999');

        $response->assertStatus(404);
    }

    public function test_player_cannot_delete_another_players_profile_image()
    {
        Sanctum::actingAs($this->player);

        $other = Player::factory()->create();
        $image = PlayerProfileImage::factory()->create([
            'player_id' => $other->id_player,
            'profile_path' => 'player-profile-images/999/profile/x.png',
            'sort_order' => 0,
        ]);

        Storage::disk(config('filesystems.user_uploads_disk', 'public'))->put($image->profile_path, 'x');

        $response = $this->deleteJson("/api/v1/app/players/image/{$image->id}");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You can only delete your own profile images.',
            ]);

        $this->assertDatabaseHas('player_profile_images', ['id' => $image->id]);
    }

    public function test_image_upload_validation()
    {
        Sanctum::actingAs($this->player);

        $response = $this->postJson('/api/v1/app/players/image', []);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['image']);

        $textFile = $this->createFakeTextFile('test.txt');
        $response = $this->postJson('/api/v1/app/players/image', ['image' => $textFile]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['image']);

        $largeFile = UploadedFile::fake()->create('large.jpg', 6000);
        $response = $this->postJson('/api/v1/app/players/image', ['image' => $largeFile]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['image']);
    }

    public function test_player_cannot_update_other_player_profile()
    {
        Sanctum::actingAs($this->player);

        $otherPlayer = Player::factory()->create([
            'first_name' => 'Other', 'middle_name' => null, 'last_name' => 'Player',
        ]);

        $response = $this->putJson("/api/v1/app/players/{$otherPlayer->id_player}", [
            'first_name' => 'Hacked',
            'last_name' => 'Name',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You can only update your own profile',
            ]);
    }

    public function test_profile_requires_authentication()
    {
        $response = $this->postJson('/api/v1/app/players', [
            'first_name' => 'Test',
        ]);

        $response->assertStatus(401);
    }

    public function test_profile_validation()
    {
        Sanctum::actingAs($this->player);

        $response = $this->postJson('/api/v1/app/players', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['first_name']);
    }

    public function test_image_upload_requires_authentication()
    {
        $file = $this->createFakeImage('profile.png');

        $response = $this->postJson('/api/v1/app/players/image', ['image' => $file]);

        $response->assertStatus(401);
    }

    public function test_image_delete_requires_authentication()
    {
        $response = $this->deleteJson('/api/v1/app/players/image/1');

        $response->assertStatus(401);
    }
}
