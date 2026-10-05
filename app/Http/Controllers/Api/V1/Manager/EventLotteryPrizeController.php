<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Resources\EventLotteryPrizeCollection;
use App\Http\Resources\EventLotteryPrizeResource;
use App\Models\EventLottery;
use App\Models\EventLotteryPrize;
use App\Services\Lottery\LotteryPrizeLuckiesAssembler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="🐧 Manager: PadelEvent Lottery Prizes",
 *     description="(v1) Prize lines on named edition lotteries"
 * )
 */
class EventLotteryPrizeController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/manager/event-lottery-prizes",
     *     summary="Get paginated list of lottery prize lines",
     *     tags={"🐧 Manager: PadelEvent Lottery Prizes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(ref="#/components/parameters/page"),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=15, maximum=100)),
     *     @OA\Parameter(
     *         name="include",
     *         in="query",
     *         description="Comma-separated includes. Use luckies to nest assembled luckies on each prize line.",
     *         required=false,
     *         @OA\Schema(type="string", example="luckies")
     *     ),
     *     @OA\Parameter(
     *         name="event_division_id",
     *         in="query",
     *         description="When include=luckies, scopes which rules feed luckies and rule_prize_quantity_total. Ignored otherwise. Not a prize-line column filter.",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiPaginatedResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="data",
     *                         type="array",
     *                         @OA\Items(ref="#/components/schemas/EventLotteryPrizeIndexItem")
     *                     ),
     *                     @OA\Property(
     *                         property="meta",
     *                         type="object",
     *                         @OA\Property(property="current_page", type="integer"),
     *                         @OA\Property(property="has_unresolved_pending", type="boolean", description="Present when include=luckies. Lottery-wide pending lucky.")
     *                     )
     *                 )
     *             }
     *         )
     *     )
     * )
     *
     * @OA\Schema(
     *     schema="EventLotteryPrizeIndexItem",
     *     allOf={
     *         @OA\Schema(ref="#/components/schemas/EventLotteryPrize"),
     *         @OA\Schema(
     *             @OA\Property(property="rules_count", type="integer", description="Always present"),
     *             @OA\Property(
     *                 property="rule_prize_quantity_total",
     *                 type="integer",
     *                 description="Always present. Sum of rule_prize_quantity for this line; when include=luckies may be overwritten with a division-scoped sum"
     *             ),
     *             @OA\Property(property="activity_at", type="integer", nullable=true, description="Present when include=luckies"),
     *             @OA\Property(
     *                 property="luckies",
     *                 type="array",
     *                 description="Present when include=luckies",
     *                 @OA\Items(ref="#/components/schemas/EventLotteryLucky")
     *             )
     *         )
     *     }
     * )
     */
    public function index(Request $request)
    {
        $query = EventLotteryPrize::with(['prize', 'sponsor', 'lottery'])
            ->withCount('rules')
            ->withSum('rules as rule_prize_quantity_total', 'rule_prize_quantity');

        $lotteryId = $this->pullJoinFilter($request, 'lottery_id');
        if ($lotteryId !== null) {
            $ids = is_array($lotteryId) ? $lotteryId : [$lotteryId];
            $query->whereIn('lottery_id', $ids);
        }

        $this->applyAdvancedPagination(
            $query,
            $request,
            EventLotteryPrize::$searchableFields,
            EventLotteryPrize::$filterableFields,
            EventLotteryPrize::$sortableFields
        );

        $paginationParams = $this->getPaginationParams($request);

        if (! $request->has('sort_by')) {
            $query->orderBy('display_order')->orderBy('id_lottery_prize', 'desc');
        }

        $lines = $query->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);

        $includeLuckies = $this->includeContainsLuckies($request);
        if (! $includeLuckies) {
            return new EventLotteryPrizeCollection($lines);
        }

        $eventDivisionId = $this->assemblyEventDivisionId($request);
        $assembler = app(LotteryPrizeLuckiesAssembler::class);
        $assembler->attach($lines->getCollection(), $eventDivisionId);

        $sorted = $lines->getCollection()->sort(function ($a, $b): int {
            $aAt = $a->activity_at;
            $bAt = $b->activity_at;
            $aNull = $aAt === null;
            $bNull = $bAt === null;
            if ($aNull && $bNull) {
                return ((int) $a->display_order) <=> ((int) $b->display_order);
            }
            if ($aNull) {
                return 1;
            }
            if ($bNull) {
                return -1;
            }
            if ((int) $bAt !== (int) $aAt) {
                return ((int) $bAt) <=> ((int) $aAt);
            }

            return ((int) $a->display_order) <=> ((int) $b->display_order);
        })->values();
        $lines->setCollection($sorted);

        $pendingLotteryId = $this->singleLotteryId($lotteryId, $sorted);
        $hasUnresolved = $pendingLotteryId !== null
            ? $assembler->hasUnresolvedPendingForLottery($pendingLotteryId)
            : false;

        return new EventLotteryPrizeCollection($lines, $hasUnresolved);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/event-lottery-prizes/{lotteryPrize}",
     *     summary="Get lottery prize line details",
     *     tags={"🐧 Manager: PadelEvent Lottery Prizes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="lotteryPrize", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function show(EventLotteryPrize $lotteryPrize)
    {
        $lotteryPrize->load(['prize', 'sponsor', 'lottery']);
        $lotteryPrize->loadCount('rules');
        $lotteryPrize->loadSum('rules as rule_prize_quantity_total', 'rule_prize_quantity');

        return $this->resourceResponse(
            new EventLotteryPrizeResource($lotteryPrize),
            'Lottery prize details retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/event-lottery-prizes/reorder",
     *     summary="Rewrite display_order for every prize line of one lottery",
     *     tags={"🐧 Manager: PadelEvent Lottery Prizes"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"lottery_id", "lottery_prize_ids"},
     *             @OA\Property(property="lottery_id", type="integer"),
     *             @OA\Property(
     *                 property="lottery_prize_ids",
     *                 type="array",
     *                 description="Must list every prize line of the lottery exactly once, in desired order",
     *                 @OA\Items(type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Reordered"),
     *     @OA\Response(response=409, description="PadelEvent is finalized or lottery has luckies"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'lottery_id' => 'required|integer|exists:event_lotteries,id_lottery',
            'lottery_prize_ids' => 'required|array|min:1',
            'lottery_prize_ids.*' => 'integer|distinct',
        ]);

        $lotteryId = (int) $validated['lottery_id'];
        $lottery = EventLottery::with('event')->find($lotteryId);
        if ($lottery?->event?->finalized_at !== null) {
            return $this->errorResponse('PadelEvent is finalized.', 409);
        }

        if ($lottery->lotteryRules()->whereHas('luckies')->exists()) {
            return $this->errorResponse('Lottery already has luckies.', 409);
        }

        $existing = EventLotteryPrize::query()
            ->where('lottery_id', $lotteryId)
            ->get()
            ->keyBy('id_lottery_prize');

        $requestedIds = array_map('intval', $validated['lottery_prize_ids']);

        $missing = $existing->keys()
            ->map(static fn ($id): int => (int) $id)
            ->diff($requestedIds)
            ->values()
            ->all();

        if ($missing !== []) {
            return $this->errorResponse(
                'lottery_prize_ids must include every prize line of this lottery.',
                422,
                ['missing_lottery_prize_ids' => $missing]
            );
        }

        $unknown = array_values(array_diff(
            $requestedIds,
            $existing->keys()->map(static fn ($id): int => (int) $id)->all()
        ));
        if ($unknown !== []) {
            return $this->errorResponse(
                'One or more lottery prize lines do not belong to this lottery.',
                422,
                ['unknown_lottery_prize_ids' => $unknown]
            );
        }

        $reordered = DB::transaction(function () use ($requestedIds, $existing, $lotteryId) {
            foreach ($requestedIds as $index => $prizeId) {
                $line = $existing->get($prizeId);
                $line->display_order = $index;
                $line->save();
            }

            return EventLotteryPrize::query()
                ->with(['prize', 'sponsor', 'lottery'])
                ->withCount('rules')
                ->withSum('rules as rule_prize_quantity_total', 'rule_prize_quantity')
                ->where('lottery_id', $lotteryId)
                ->orderBy('display_order')
                ->orderBy('id_lottery_prize')
                ->get();
        });

        return $this->successResponse(
            EventLotteryPrizeResource::collection($reordered),
            'Lottery prizes reordered successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/event-lottery-prizes",
     *     summary="Create a lottery prize line",
     *     tags={"🐧 Manager: PadelEvent Lottery Prizes"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"lottery_id", "prize_id", "prize_quantity"},
     *             @OA\Property(property="lottery_id", type="integer"),
     *             @OA\Property(property="prize_id", type="integer"),
     *             @OA\Property(property="prize_quantity", type="integer"),
     *             @OA\Property(property="sponsor_id", type="integer", nullable=true),
     *             @OA\Property(property="display_order", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Created"),
     *     @OA\Response(response=409, description="PadelEvent is finalized")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->lineValidation(true));

        if ($response = $this->refuseIfEventFinalizedForLotteryId((int) $validated['lottery_id'])) {
            return $response;
        }

        $validated['display_order'] = $validated['display_order'] ?? 0;

        $line = EventLotteryPrize::create($validated);
        $line->load(['prize', 'sponsor', 'lottery']);
        $line->loadCount('rules');
        $line->loadSum('rules as rule_prize_quantity_total', 'rule_prize_quantity');

        return $this->resourceResponse(
            new EventLotteryPrizeResource($line),
            'Lottery prize created successfully',
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/manager/event-lottery-prizes/{lotteryPrize}",
     *     summary="Update a lottery prize line",
     *     tags={"🐧 Manager: PadelEvent Lottery Prizes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="lotteryPrize", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Updated"),
     *     @OA\Response(response=409, description="PadelEvent is finalized")
     * )
     */
    public function update(Request $request, EventLotteryPrize $lotteryPrize)
    {
        if ($response = $this->refuseIfEventFinalizedForPrize($lotteryPrize)) {
            return $response;
        }

        $validated = $request->validate($this->lineValidation(true));

        if (isset($validated['lottery_id'])
            && ($response = $this->refuseIfEventFinalizedForLotteryId((int) $validated['lottery_id']))) {
            return $response;
        }

        $lotteryPrize->update($validated);
        $lotteryPrize->load(['prize', 'sponsor', 'lottery']);
        $lotteryPrize->loadCount('rules');
        $lotteryPrize->loadSum('rules as rule_prize_quantity_total', 'rule_prize_quantity');

        return $this->resourceResponse(
            new EventLotteryPrizeResource($lotteryPrize),
            'Lottery prize updated successfully'
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/manager/event-lottery-prizes/{lotteryPrize}",
     *     summary="Partially update a lottery prize line",
     *     tags={"🐧 Manager: PadelEvent Lottery Prizes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="lotteryPrize", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Updated"),
     *     @OA\Response(response=409, description="PadelEvent is finalized")
     * )
     */
    public function patch(Request $request, EventLotteryPrize $lotteryPrize)
    {
        if ($response = $this->refuseIfEventFinalizedForPrize($lotteryPrize)) {
            return $response;
        }

        $validated = $request->validate($this->lineValidation(false));

        if (isset($validated['lottery_id'])
            && ($response = $this->refuseIfEventFinalizedForLotteryId((int) $validated['lottery_id']))) {
            return $response;
        }

        $lotteryPrize->update($validated);
        $lotteryPrize->load(['prize', 'sponsor', 'lottery']);
        $lotteryPrize->loadCount('rules');
        $lotteryPrize->loadSum('rules as rule_prize_quantity_total', 'rule_prize_quantity');

        return $this->resourceResponse(
            new EventLotteryPrizeResource($lotteryPrize),
            'Lottery prize partially updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/event-lottery-prizes/{lotteryPrize}",
     *     summary="Delete a lottery prize line",
     *     tags={"🐧 Manager: PadelEvent Lottery Prizes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="lotteryPrize", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted"),
     *     @OA\Response(response=409, description="Linked rules or edition finalized")
     * )
     */
    public function destroy(EventLotteryPrize $lotteryPrize)
    {
        if ($response = $this->refuseIfEventFinalizedForPrize($lotteryPrize)) {
            return $response;
        }

        return $this->deleteResourceWithResponse(
            $lotteryPrize,
            ['rules' => 'rules_count'],
            'lottery prize',
            'Lottery prize deleted successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/event-lottery-prizes/bulk",
     *     summary="Bulk delete lottery prize lines",
     *     tags={"🐧 Manager: PadelEvent Lottery Prizes"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Deleted"),
     *     @OA\Response(response=409, description="PadelEvent is finalized")
     * )
     */
    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'lottery_prize_ids' => 'required|array|min:1',
            'lottery_prize_ids.*' => 'integer',
        ]);

        $lines = EventLotteryPrize::query()
            ->with('lottery.event')
            ->whereIn('id_lottery_prize', $request->lottery_prize_ids)
            ->get();

        foreach ($lines as $line) {
            if ($line->lottery?->event?->finalized_at !== null) {
                return $this->errorResponse('PadelEvent is finalized.', 409);
            }
        }

        return $this->deleteBulkResourcesWithResponse(
            $request->lottery_prize_ids,
            EventLotteryPrize::class,
            ['rules' => 'rules_count'],
            'lottery prize',
            'Lottery prizes deleted successfully'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function lineValidation(bool $requireCore): array
    {
        $required = $requireCore ? 'required' : 'sometimes';

        return [
            'lottery_id' => $required . '|integer|exists:event_lotteries,id_lottery',
            'prize_id' => $required . '|integer|exists:prizes,id_prize',
            'prize_quantity' => $required . '|integer|min:1',
            'sponsor_id' => 'nullable|integer|exists:sponsors,id_sponsor',
            'display_order' => 'nullable|integer',
            'rules' => 'prohibited',
        ];
    }

    private function refuseIfEventFinalizedForLotteryId(int $lotteryId): ?JsonResponse
    {
        $lottery = EventLottery::with('event')->find($lotteryId);
        if ($lottery?->event?->finalized_at !== null) {
            return $this->errorResponse('PadelEvent is finalized.', 409);
        }

        return null;
    }

    private function refuseIfEventFinalizedForPrize(EventLotteryPrize $lotteryPrize): ?JsonResponse
    {
        $lotteryPrize->loadMissing('lottery.event');
        if ($lotteryPrize->lottery?->event?->finalized_at !== null) {
            return $this->errorResponse('PadelEvent is finalized.', 409);
        }

        return null;
    }

    private function includeContainsLuckies(Request $request): bool
    {
        $include = $request->query('include');
        if ($include === null || $include === '') {
            return false;
        }
        $parts = is_array($include)
            ? $include
            : (preg_split('/\s*,\s*/', (string) $include) ?: []);

        return in_array('luckies', $parts, true);
    }

    private function assemblyEventDivisionId(Request $request): ?int
    {
        $raw = $request->query('event_division_id');
        if ($raw === null || $raw === '') {
            return null;
        }

        return (int) $raw;
    }

    /**
     * @param  mixed  $lotteryIdFilter
     */
    private function singleLotteryId(mixed $lotteryIdFilter, \Illuminate\Support\Collection $lines): ?int
    {
        if (is_array($lotteryIdFilter) && count($lotteryIdFilter) === 1) {
            return (int) reset($lotteryIdFilter);
        }
        if ($lotteryIdFilter !== null && ! is_array($lotteryIdFilter)) {
            return (int) $lotteryIdFilter;
        }
        $ids = $lines->pluck('lottery_id')->unique()->filter()->values();
        if ($ids->count() === 1) {
            return (int) $ids->first();
        }

        return null;
    }
}
