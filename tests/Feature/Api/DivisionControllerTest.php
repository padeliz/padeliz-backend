<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\Division;
use App\Models\Edition;
use App\Models\EditionDivision;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DivisionControllerTest extends TestCase
{
    use RefreshDatabase;

    private $division;

    public function setUp(): void
    {
        parent::setUp();

        // Create main test division
        $this->division = Division::factory()->create([
            'name' => 'Test Division',
            'color' => '#FF0000',
            'is_students_only' => true
        ]);

        // Create other divisions
        Division::factory()->count(3)->create();

        // Create some editions and link them to divisions
        $edition = Edition::factory()->create();
        EditionDivision::factory()->create([
            'edition_id' => $edition->id_edition,
            'division_id' => $this->division->id_division,
            'players_limit' => 16,
            'court' => 'Court 1',
            'include_scores' => true
        ]);
    }

    public function test_index_returns_paginated_divisions()
    {
        $response = $this->getJson('/api/divisions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'current_page',
                'data' => [
                    '*' => [
                        'id_division',
                        'name',
                        'color',
                        'is_students_only'
                    ]
                ],
                'first_page_url',
                'last_page'
            ])
            ->assertJsonCount(4, 'data'); // Total divisions created
    }

    public function test_show_returns_single_division()
    {
        $response = $this->getJson("/api/divisions/{$this->division->id_division}");

        $response->assertStatus(200)
            ->assertJson([
                'id_division' => $this->division->id_division,
                'name' => 'Test Division',
                'color' => '#FF0000',
                'is_students_only' => true
            ]);
    }

    public function test_show_returns_404_for_nonexistent_division()
    {
        $response = $this->getJson('/api/divisions/99999');
        $response->assertStatus(404);
    }

    public function test_divisions_are_ordered_by_name()
    {
        $response = $this->getJson('/api/divisions');

        $names = collect($response->json('data'))->pluck('name')->toArray();
        $sortedNames = collect($names)->sort(SORT_STRING | SORT_FLAG_CASE)->values()->toArray();

        $this->assertEquals($sortedNames, $names, 'Divisions are not properly sorted by name');
    }

    public function test_index_includes_correct_student_only_flag()
    {
        // Create a division with is_students_only = true
        $this->division->is_students_only = true;
        $this->division->save();

        $response = $this->getJson('/api/divisions');

        $studentDivision = collect($response->json('data'))
            ->firstWhere('id_division', $this->division->id_division);

        $this->assertTrue(
            $studentDivision['is_students_only'],
            'Student-only flag is not correctly represented'
        );
    }
}
