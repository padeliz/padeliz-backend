<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\User;
use App\Models\Prize;
use App\Models\Sponsor;
use App\Models\SponsorPrize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Helpers\FileUploadTestHelper;
use Tests\TestCase;

class SponsorPrizeControllerTest extends TestCase
{
    use RefreshDatabase;
    use FileUploadTestHelper;

    private User $manager;

    private Sponsor $sponsor;

    private Prize $prize;

    public function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('filesystems.user_uploads_disk', 'public'));

        $this->manager = User::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        $this->sponsor = Sponsor::factory()->create();
        $this->prize = Prize::factory()->create();
    }

    public function test_unique_sponsor_id_prize_id(): void
    {
        Sanctum::actingAs($this->manager);

        SponsorPrize::factory()->create([
            'sponsor_id' => $this->sponsor->id_sponsor,
            'prize_id' => $this->prize->id_prize,
        ]);

        $response = $this->postJson('/api/v1/manager/sponsor-prizes', [
            'sponsor_id' => $this->sponsor->id_sponsor,
            'prize_id' => $this->prize->id_prize,
            'box_1_text' => 'Duplicate',
        ]);

        $response->assertStatus(422);
    }

    public function test_manager_can_create_sponsor_prize(): void
    {
        Sanctum::actingAs($this->manager);

        $response = $this->postJson('/api/v1/manager/sponsor-prizes', [
            'sponsor_id' => $this->sponsor->id_sponsor,
            'prize_id' => $this->prize->id_prize,
            'template' => 'IMAGE_TOP',
            'box_1_text' => 'Hello',
            'box_1_bg_color' => '#112233',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.sponsor_id', $this->sponsor->id_sponsor)
            ->assertJsonPath('data.prize_id', $this->prize->id_prize)
            ->assertJsonPath('data.box_1_text', 'Hello');
    }

    public function test_media_upload_and_delete(): void
    {
        Sanctum::actingAs($this->manager);

        $sponsorPrize = SponsorPrize::factory()->create([
            'sponsor_id' => $this->sponsor->id_sponsor,
            'prize_id' => $this->prize->id_prize,
            'media_path' => null,
        ]);
        $file = $this->createFakeImage('overlay.png');

        $upload = $this->post(
            "/api/v1/manager/sponsor-prizes/{$sponsorPrize->id_sponsor_prize}/media",
            ['file' => $file],
            ['Accept' => 'application/json']
        );
        $upload->assertOk();
        $this->assertNotEmpty($upload->json('data.media_path') ?? $upload->json('data.url'));

        $delete = $this->deleteJson("/api/v1/manager/sponsor-prizes/{$sponsorPrize->id_sponsor_prize}/media");
        $delete->assertOk();
        $sponsorPrize->refresh();
        $this->assertTrue($sponsorPrize->media_path === null || $sponsorPrize->media_path === '');
    }
}
