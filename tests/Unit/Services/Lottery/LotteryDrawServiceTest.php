<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Lottery;

use App\Models\EventParticipation;
use App\Services\EventCalendarTiming;
use App\Services\Lottery\LotteryDrawService;
use App\Services\Lottery\LotteryHtmlHelper;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use ReflectionMethod;
use Tests\TestCase;

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

    public function test_format_interval_label_uses_bucharest_clocks_from_unix(): void
    {
        $tz = EventCalendarTiming::timezone();
        $service = new LotteryDrawService();
        $method = new ReflectionMethod(LotteryDrawService::class, 'formatIntervalLabel');
        $method->setAccessible(true);

        $overnight = $method->invoke(
            $service,
            Carbon::parse('2026-08-14 18:00:00', $tz)->timestamp,
            Carbon::parse('2026-08-15 00:00:00', $tz)->timestamp,
        );
        $this->assertSame('18:00 – 00:00', $overnight);

        $sameDay = $method->invoke(
            $service,
            Carbon::parse('2026-08-14 10:00:00', $tz)->timestamp,
            Carbon::parse('2026-08-14 13:00:00', $tz)->timestamp,
        );
        $this->assertSame('10:00 – 13:00', $sameDay);
    }

    private function participation(int $id, ?string $absenceReason, int $eventDivisionId): EventParticipation
    {
        $p = new EventParticipation();
        $p->forceFill([
            'id_participation' => $id,
            'absence_reason' => $absenceReason,
            'event_division_id' => $eventDivisionId,
            'event_id' => 1,
            'player_user_id' => $id,
        ]);

        return $p;
    }
}
