<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\Club;
use App\Models\Location;
use App\Models\Edition;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LocationControllerTest extends TestCase
{
    use RefreshDatabase;

    private $location;
    private $club;

    public function setUp(): void
    {
        parent::setUp();

        // Create a club
        $this->club = Club::factory()->create();

        // Create main test location
        $this->location = Location::factory()->create([
            'club_id' => $this->club->id_club
        ]);

        // Create other locations
        Location::factory()->count(3)->create([
            'club_id' => $this->club->id_club
        ]);

        // Create editions for the main location
        Edition::factory()->count(2)->create([
            'location_id' => $this->location->id_location,
            'date' => Carbon::now()->subDays(5)
        ]);

        Edition::factory()->create([
            'location_id' => $this->location->id_location,
            'date' => Carbon::now()->addDays(5)
        ]);
    }

    public function test_index_returns_paginated_locations()
    {
        $response = $this->getJson('/api/locations');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'current_page',
                'data' => [
                    '*' => [
                        'id_location',
                        'name',
                        'club_id',
                        'club' => [
                            'id_club',
                            'name',
                            'city'
                        ]
                    ]
                ],
                'first_page_url',
                'last_page'
            ])
            ->assertJsonCount(4, 'data'); // Total locations created
    }

    public function test_show_returns_single_location()
    {
        $response = $this->getJson("/api/locations/{$this->location->id_location}");

        $response->assertStatus(200)
            ->assertJson([
                'id_location' => $this->location->id_location,
                'name' => $this->location->name,
                'club_id' => $this->club->id_club
            ]);
    }

    public function test_show_returns_404_for_nonexistent_location()
    {
        $response = $this->getJson('/api/locations/99999');
        $response->assertStatus(404);
    }

    public function test_editions_returns_paginated_location_editions()
    {
        $response = $this->getJson("/api/locations/{$this->location->id_location}/editions");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'current_page',
                'data' => [
                    '*' => [
                        'id_edition',
                        'name',
                        'date',
                        'location_id',
                        'divisions'
                    ]
                ],
                'first_page_url',
                'last_page'
            ])
            ->assertJsonCount(3, 'data') // Total editions created for this location
            ->assertJsonPath('data.0.location_id', $this->location->id_location);
    }

    public function test_editions_returns_404_for_nonexistent_location()
    {
        $response = $this->getJson('/api/locations/99999/editions');
        $response->assertStatus(404);
    }

    public function test_editions_are_ordered_by_date_desc()
    {
        $response = $this->getJson("/api/locations/{$this->location->id_location}/editions");

        $dates = collect($response->json('data'))->pluck('date')->toArray();
        $sortedDates = collect($dates)->sort()->reverse()->values()->toArray();

        $this->assertEquals($sortedDates, $dates, 'Editions are not properly sorted by date');
    }
}
