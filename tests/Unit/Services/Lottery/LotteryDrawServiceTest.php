<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Lottery;

use App\Models\EventParticipation;
use App\Services\Lottery\LotteryDrawService;
use App\Services\Lottery\LotteryHtmlHelper;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

final class LotteryDrawServiceTest extends TestCase
{
    public function test_filter_excludes_absence_already_drawn_and_division_wide_replaced(): void
    {
        $service = new LotteryDrawService();

        $eligible = $this->participation(1, null, 10);
        $absent = $this->participation(2, 'SICK', 10);
        $drawn = $this->participation(3, null, 10);
        $replaced = $this->participation(4, null, 10);
        $otherDivision = $this->participation(5, null, 20);

        $filtered = $service->filterEligibleParticipations(
            new Collection([$eligible, $absent, $drawn, $replaced, $otherDivision]),
            alreadyDrawnParticipationIds: [3],
            divisionWideReplacedParticipationIds: [4],
            preferEventDivisionId: 10,
        );

        $ids = $filtered->pluck('id_participation')->all();
        $this->assertSame([1, 5], $ids);
        $this->assertSame(1, $filtered->first()->id_participation, 'Preferred division should sort first');
    }

    public function test_filter_ignores_demographic_fields_entirely(): void
    {
        $service = new LotteryDrawService();

        $guest = $this->participation(11, null, 1);
        $guest->setAttribute('is_guest', true);

        $filtered = $service->filterEligibleParticipations(new Collection([$guest]));

        $this->assertCount(1, $filtered);
        $this->assertSame(11, $filtered->first()->id_participation);
    }

    public function test_placeholders_left_unreplaced_when_next_edition_missing(): void
    {
        $text = 'See you at {{edition.next.name}} on {{edition.next.date.short}}';
        $this->assertSame($text, LotteryHtmlHelper::replaceEditionNextPlaceholders($text, null));
    }

    private function participation(int $id, ?string $absenceReason, int $eventDivisionId): EventParticipation
    {
        $p = new EventParticipation();
        $p->forceFill([
            'id_participation' => $id,
            'absence_reason' => $absenceReason,
            'event_division_id' => $eventDivisionId,
            'event_id' => 1,
            'player_id' => $id,
        ]);

        return $p;
    }
}
