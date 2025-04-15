<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\Edition;
use App\Models\Location;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EditionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        // Create test data
        $location = Location::factory()->create();

        // Past editions
        Edition::factory()->count(3)->create([
            'date' => Carbon::now()->subDays(5),
            'location_id' => $location->id_location
        ]);

        // Ongoing edition
        Edition::factory()->create([
            'date' => Carbon::today(),
            'arriving_time' => Carbon::now()->subHour()->format('H:i:s'),
            'closing_time' => Carbon::now()->addHour()->format('H:i:s'),
            'location_id' => $location->id_location
        ]);

        // Future editions
        Edition::factory()->count(2)->create([
            'date' => Carbon::now()->addDays(5),
            'location_id' => $location->id_location
        ]);
    }

    public function test_index_returns_paginated_editions()
    {
        $response = $this->getJson('/api/editions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'current_page',
                'data' => [
                    '*' => [
                        'id_edition',
                        'name',
                        'date',
                        'location' => [
                            'id_location',
                            'name'
                        ]
                    ]
                ],
                'first_page_url',
                'last_page'
            ]);
    }

    public function test_upcoming_returns_only_future_editions()
    {
        $response = $this->getJson('/api/editions/upcoming');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');

        $date = Carbon::parse($response->json('data.0.date'));
        $this->assertTrue($date->isFuture(), 'Edition date should be in the future');
    }

    public function test_ongoing_returns_only_current_editions()
    {
        $response = $this->getJson('/api/editions/ongoing');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $date = Carbon::parse($response->json('data.0.date'));
        $this->assertTrue($date->isToday(), 'Edition date should be today');
    }

    public function test_past_returns_only_past_editions()
    {
        $response = $this->getJson('/api/editions/past');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');

        $date = Carbon::parse($response->json('data.0.date'));
        $this->assertTrue($date->isPast(), 'Edition date should be in the past');
    }

    public function test_show_returns_single_edition()
    {
        $edition = Edition::first();

        $response = $this->getJson("/api/editions/{$edition->id_edition}");

        $response->assertStatus(200)
            ->assertJson([
                'id_edition' => $edition->id_edition,
                'name' => $edition->name
            ]);
    }

    public function test_show_returns_404_for_nonexistent_edition()
    {
        $response = $this->getJson('/api/editions/99999');
        $response->assertStatus(404);
    }
}
