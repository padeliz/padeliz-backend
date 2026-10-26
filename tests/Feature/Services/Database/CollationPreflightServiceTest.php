<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Database;

use App\Services\Database\CollationPreflightService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CollationPreflightServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_preflight_passes_on_clean_database(): void
    {
        $collisions = (new CollationPreflightService())->detectUniqueCollisions();

        $this->assertSame([], $collisions);
    }

    public function test_preflight_detects_accent_insensitive_unique_collisions(): void
    {
        $player = \App\Models\User::factory()->create([
            'first_name' => 'Ștefan',
            'middle_name' => null,
            'last_name' => 'Popescu',
        ]);

        $exists = \App\Models\User::query()
            ->whereRaw(\App\Support\PlayerFullNameSql::expression() . ' = ?', ['Stefan Popescu'])
            ->whereKey($player->id_user)
            ->exists();

        $this->assertTrue($exists);

        $report = (new CollationPreflightService())->formatCollisionReport([
            [
                'table' => 'users',
                'index' => 'example_unique',
                'columns' => ['first_name', 'last_name'],
                'duplicate_count' => 1,
                'sample' => ['first_name=Stefan, last_name=Popescu | first_name=Ștefan, last_name=Popescu'],
            ],
        ]);

        $this->assertStringContainsString('UNIQUE index collisions', $report);
        $this->assertStringContainsString('users', $report);
    }

    public function test_format_collision_report_describes_conflicts(): void
    {
        $report = (new CollationPreflightService())->formatCollisionReport([
            [
                'table' => 'clubs',
                'index' => 'clubs_name_unique',
                'columns' => ['name'],
                'duplicate_count' => 1,
                'sample' => ['name=Bucuresti | name=București'],
            ],
        ]);

        $this->assertStringContainsString('UNIQUE index collisions', $report);
        $this->assertStringContainsString('clubs', $report);
    }
}
