<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Models\PadelEvent;
use App\Models\EventParticipation;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="🐥 App: Event History",
 *     description="(v1) Player event history"
 * )
 */
class EventHistoryController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/app/my/event-history",
     *     summary="Paginated edition-aggregated participation history",
     *     tags={"🐥 App: Event History"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(ref="#/components/parameters/page"),
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request)
    {
        $playerId = (int) $request->user()->id_player;

        $editionPage = PadelEvent::query()
            ->whereHas('participations', function ($q) use ($playerId) {
                $q->where('player_id', $playerId);
            })
            ->orderByDesc('date')
            ->paginate($this->paginationLimit);

        $pageEditionIds = $editionPage->getCollection()
            ->pluck('id_event')
            ->map(fn ($id) => (int) $id)
            ->all();

        $participations = EventParticipation::query()
            ->where('player_id', $playerId)
            ->whereIn('event_id', $pageEditionIds)
            ->with([
                'event:id_event,name,date',
                'eventDivision.division:id_division,name',
            ])
            ->get()
            ->groupBy('event_id');

        $data = [];
        foreach ($pageEditionIds as $editionId) {
            $group = $participations->get($editionId) ?? collect();
            /** @var EventParticipation|null $first */
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
                'date' => (string) ($event->formatted_date ?? $event->date),
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
