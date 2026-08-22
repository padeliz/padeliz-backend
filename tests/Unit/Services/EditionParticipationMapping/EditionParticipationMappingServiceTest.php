<?php

declare(strict_types=1);

namespace Tests\Unit\Services\EditionParticipationMapping;

use App\Models\EditionDivision;
use App\Services\EditionParticipationMapping\EditionParticipationMappingService;
use Tests\TestCase;

final class EditionParticipationMappingServiceTest extends TestCase
{
    private EditionParticipationMappingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EditionParticipationMappingService::class);
    }

    public function test_pick_edition_division_prefers_capacity_within_history_match(): void
    {
        $advancers = $this->editionDivision(221, 8, 'Advancers', 0, 12);
        $explorers = $this->editionDivision(222, 6, 'Explorers', 1, 12);
        $divisions = collect([$advancers, $explorers]);
        $countsByEdId = [221 => 5, 222 => 0];

        $picked = $this->service->pickEditionDivision(
            $divisions,
            $countsByEdId,
            [
                'division_id' => 8,
                'division_title' => 'Advancers',
                'rank' => 3,
                'participant_count' => 10,
            ]
        );

        $this->assertSame(221, (int) $picked->id_edition_division);
    }

    public function test_pick_edition_division_allows_over_limit_when_history_match_is_full_but_other_eds_have_capacity(): void
    {
        $advancers = $this->editionDivision(221, 8, 'Advancers', 0, 12);
        $explorers = $this->editionDivision(222, 6, 'Explorers', 1, 12);
        $divisions = collect([$advancers, $explorers]);
        $countsByEdId = [221 => 12, 222 => 3];

        $picked = $this->service->pickEditionDivision(
            $divisions,
            $countsByEdId,
            [
                'division_id' => 8,
                'division_title' => 'Advancers',
                'rank' => 7,
                'participant_count' => 8,
            ]
        );

        $this->assertSame(221, (int) $picked->id_edition_division);
    }

    public function test_pick_edition_division_allows_over_limit_for_title_match_when_matched_ed_full(): void
    {
        $advancers = $this->editionDivision(221, 8, 'Advancers', 0, 2);
        $explorers = $this->editionDivision(222, 6, 'Explorers', 1, 12);
        $divisions = collect([$advancers, $explorers]);
        $countsByEdId = [221 => 2, 222 => 1];

        $picked = $this->service->pickEditionDivision(
            $divisions,
            $countsByEdId,
            [
                'division_title' => 'Advancers',
                'rank' => 4,
                'participant_count' => 8,
            ]
        );

        $this->assertSame(221, (int) $picked->id_edition_division);
    }

    public function test_pick_edition_division_falls_back_to_lowest_skill_order_without_history(): void
    {
        $advancers = $this->editionDivision(221, 8, 'Advancers', 0, 12);
        $explorers = $this->editionDivision(222, 6, 'Explorers', 1, 12);
        $divisions = collect([$advancers, $explorers]);
        $countsByEdId = [221 => 12, 222 => 0];

        $picked = $this->service->pickEditionDivision($divisions, $countsByEdId, null);

        $this->assertSame(222, (int) $picked->id_edition_division);
    }

    private function editionDivision(
        int $id,
        int $divisionId,
        string $title,
        int $skillOrder,
        int $playersLimit
    ): EditionDivision {
        $ed = new EditionDivision();
        $ed->id_edition_division = $id;
        $ed->division_id = $divisionId;
        $ed->division_title = $title;
        $ed->skill_order = $skillOrder;
        $ed->players_limit = $playersLimit;

        return $ed;
    }
}
