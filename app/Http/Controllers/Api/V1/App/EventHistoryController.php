<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Models\EventParticipation;
use App\Models\PadelEvent;
use App\Services\EventCalendarTiming;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="🐥 App: Event History",
 *     description="(v1) Player event history (My Participations)"
 * )
 */
class EventHistoryController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/app/my/event-history",
     *     summary="Paginated edition-aggregated participation history (calendar Past only)",
     *     tags={"🐥 App: Event History"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(ref="#/components/parameters/page"),
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request)
    {
        $playerId = (int) $request->user()->id_user;

        $editionPage = PadelEvent::query()
            ->whereHas('participations', function ($q) use ($playerId) {
                $q->where('player_user_id', $playerId);
            })
            ->tap(static fn ($q) => EventCalendarTiming::applyCalendarPast($q))
            ->orderByDesc('event_starts_at')
            ->orderByDesc('id_event')
            ->paginate($this->paginationLimit);

        $pageEditionIds = $editionPage->getCollection()
            ->pluck('id_event')
            ->map(fn ($id) => (int) $id)
            ->all();

        $participations = EventParticipation::query()
            ->where('player_user_id', $playerId)
            ->whereIn('event_id', $pageEditionIds)
            ->with([
                'event:id_event,name,event_starts_at,event_ends_at,slug',
                'eventDivision.division:id_division,name',
            ])
            ->get()
            ->groupBy('event_id');

        $data = [];
        foreach ($pageEditionIds as $editionId) {
            $group = $participations->get($editionId) ?? collect();
            /** @var null|EventParticipation $first */
            $first = $group->first();
            $event = $first?->event;
            if ($event === null) {
                continue;
            }

            $titles = $group
                ->map(function (EventParticipation $p) {
                    return (string) (
                        $p->eventDivision?->division_title
                        ?? $p->eventDivision?->division?->name
                        ?? ''
                    );
                })
                ->filter(fn (string $t) => $t !== '')
                ->unique()
                ->values()
                ->all();

            $data[] = [
                'id_event' => (int) $event->id_event,
                'name' => (string) $event->name,
                'slug' => $event->slug !== null && $event->slug !== '' ? (string) $event->slug : null,
                'event_starts_at' => (int) $event->event_starts_at,
                'event_ends_at' => (int) $event->event_ends_at,
                'date' => EventCalendarTiming::dayYmd((int) $event->event_starts_at),
                'division_titles' => $titles,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $data,
            'links' => [
                'first' => $editionPage->url(1),
                'last' => $editionPage->url($editionPage->lastPage()),
                'prev' => $editionPage->previousPageUrl(),
                'next' => $editionPage->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $editionPage->currentPage(),
                'from' => $editionPage->firstItem(),
                'last_page' => $editionPage->lastPage(),
                'path' => $editionPage->path(),
                'per_page' => $editionPage->perPage(),
                'to' => $editionPage->lastItem(),
                'total' => $editionPage->total(),
            ],
        ]);
    }
}
