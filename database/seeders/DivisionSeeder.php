<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Division;
use App\Models\PadelEvent;
use Illuminate\Database\Seeder;

/**
 * Canonical divisions used by schedule tooling.
 *
 * MiniTour Couples (id 5) marks the fixed-partner Couples division for seed/demo data only.
 * Schedule generate always uses mixed templates; this id/name is not used by schedule APIs.
 *
 * {@see DatabaseSeeder} also creates this row (id 5) among the standard division set.
 */
class DivisionSeeder extends Seeder
{
    public const MINITOUR_COUPLES_ID = 5;

    public const MINITOUR_COUPLES_NAME = 'MiniTour Couples';

    public function run(): void
    {
        $existing = Division::query()->find(self::MINITOUR_COUPLES_ID);
        $firstEditionId = $existing?->its_first_event_id
            ?? PadelEvent::query()->value('id_event')
            ?? PadelEvent::factory()->create()->id_event;

        $division = $existing ?? new Division();
        $division->id_division = self::MINITOUR_COUPLES_ID;
        $division->name = self::MINITOUR_COUPLES_NAME;
        $division->color = '#ff0000';
        $division->its_first_event_id = $firstEditionId;
        $division->save();
    }
}
