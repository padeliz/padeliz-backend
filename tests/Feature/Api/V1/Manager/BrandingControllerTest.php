<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Manager;

use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BrandingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_organizers_and_partners_from_config(): void
    {
        $manager = Player::factory()->manager()->create([
            'user_types' => ['player', 'manager'],
        ]);
        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/manager/branding');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.organizers.0.id', 1)
            ->assertJsonPath('data.organizers.0.name', 'ARSH Padel MiniTour')
            ->assertJsonPath('data.partners.0.id', 1)
            ->assertJsonPath('data.partners.0.name', 'PadelMania');

        $this->assertStringContainsString(
            'media/organizers/organizer-1.png',
            (string) $response->json('data.organizers.0.image')
        );
    }
}
