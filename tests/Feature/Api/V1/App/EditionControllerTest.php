<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\Edition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EditionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_guest_can_list_editions_without_authentication(): void
    {
        Edition::factory()->create(['date' => now()->addDay()->format('Y-m-d')]);

        $this->getJson('/api/v1/app/editions/upcoming')->assertOk();
        $this->getJson('/api/v1/app/editions/ongoing')->assertOk();
        $this->getJson('/api/v1/app/editions/past')->assertOk();
    }

    public function test_upcoming_ongoing_and_past_partition_by_calendar_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-12 10:00:00', config('app.timezone')));

        $past = Edition::factory()->create(['date' => '2026-04-11']);
        $ongoing = Edition::factory()->create(['date' => '2026-04-12']);
        $upcoming = Edition::factory()->create(['date' => '2026-04-13']);

        $this->getJson('/api/v1/app/editions/upcoming')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_edition', $upcoming->id_edition);

        $this->getJson('/api/v1/app/editions/ongoing')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_edition', $ongoing->id_edition);

        $this->getJson('/api/v1/app/editions/past')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id_edition', $past->id_edition);
    }
}
