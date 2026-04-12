<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\Edition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_date_from_and_date_to_query_parameters(): void
    {
        $this->getJson('/api/v1/app/editions')
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validation failed',
            ])
            ->assertJsonStructure(['errors' => ['date_from', 'date_to']]);
    }

    public function test_rejects_when_date_to_is_before_date_from(): void
    {
        $this->getJson('/api/v1/app/editions?' . http_build_query([
            'date_from' => '2026-04-10',
            'date_to' => '2026-04-09',
        ]))
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonStructure(['errors' => ['date_to']]);
    }

    public function test_guest_can_list_editions_in_date_range(): void
    {
        Edition::factory()->create(['date' => '2026-04-05']);
        Edition::factory()->create(['date' => '2026-04-15']);
        Edition::factory()->create(['date' => '2026-04-25']);

        $response = $this->getJson('/api/v1/app/editions?' . http_build_query([
            'date_from' => '2026-04-10',
            'date_to' => '2026-04-20',
        ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.date', '2026-04-15');
    }
}
