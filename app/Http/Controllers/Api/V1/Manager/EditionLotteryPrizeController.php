<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Resources\EditionLotteryPrizeCollection;
use App\Http\Resources\EditionLotteryPrizeResource;
use App\Models\EditionLotteryPrize;
use App\Services\Lottery\LotteryPrizeLuckiesAssembler;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Edition Lottery Prizes",
 *     description="(v1) Prize lines on named edition lotteries"
 * )
 */
class EditionLotteryPrizeController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/manager/edition-lottery-prizes",
     *     summary="Get paginated list of lottery prize lines",
     *     tags={"🐧 Manager: Edition Lottery Prizes"},
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
     *         name="edition_division_id",
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
     *                         @OA\Items(ref="#/components/schemas/EditionLotteryPrizeIndexItem")
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
     *     schema="EditionLotteryPrizeIndexItem",
     *     allOf={
     *         @OA\Schema(ref="#/components/schemas/EditionLotteryPrize"),
     *         @OA\Schema(
     *             @OA\Property(property="rule_prize_quantity_total", type="integer", description="Present when include=luckies"),
     *             @OA\Property(property="activity_at", type="integer", nullable=true, description="Present when include=luckies"),
     *             @OA\Property(
     *                 property="luckies",
     *                 type="array",
     *                 description="Present when include=luckies",
     *                 @OA\Items(ref="#/components/schemas/EditionLotteryLucky")
     *             )
     *         )
     *     }
     * )
     */
    public function index(Request $request)
    {
        $query = EditionLotteryPrize::with(['prize', 'sponsor', 'lottery']);

        $lotteryId = $this->pullJoinFilter($request, 'lottery_id');
        if ($lotteryId !== null) {
            $ids = is_array($lotteryId) ? $lotteryId : [$lotteryId];
            $query->whereIn('lottery_id', $ids);
        }

        $this->applyAdvancedPagination(
            $query,
            $request,
            EditionLotteryPrize::$searchableFields,
            EditionLotteryPrize::$filterableFields,
            EditionLotteryPrize::$sortableFields
        );

        $paginationParams = $this->getPaginationParams($request);

        if (! $request->has('sort_by')) {
            $query->orderBy('display_order')->orderBy('id_lottery_prize', 'desc');
        }

        $lines = $query->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);

        $includeLuckies = $this->includeContainsLuckies($request);
        if (! $includeLuckies) {
            return new EditionLotteryPrizeCollection($lines);
        }

        $editionDivisionId = $this->assemblyEditionDivisionId($request);
        $assembler = app(LotteryPrizeLuckiesAssembler::class);
        $assembler->attach($lines->getCollection(), $editionDivisionId);

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

        return new EditionLotteryPrizeCollection($lines, $hasUnresolved);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/edition-lottery-prizes/{lotteryPrize}",
     *     summary="Get lottery prize line details",
     *     tags={"🐧 Manager: Edition Lottery Prizes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="lotteryPrize", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function show(EditionLotteryPrize $lotteryPrize)
    {
        $lotteryPrize->load(['prize', 'sponsor', 'lottery']);

        return $this->resourceResponse(
            new EditionLotteryPrizeResource($lotteryPrize),
            'Lottery prize details retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/edition-lottery-prizes",
     *     summary="Create a lottery prize line",
     *     tags={"🐧 Manager: Edition Lottery Prizes"},
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
     *     @OA\Response(response=201, description="Created")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->lineValidation(true));
        $validated['display_order'] = $validated['display_order'] ?? 0;

        $line = EditionLotteryPrize::create($validated);
        $line->load(['prize', 'sponsor', 'lottery']);

        return $this->resourceResponse(
            new EditionLotteryPrizeResource($line),
            'Lottery prize created successfully',
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/manager/edition-lottery-prizes/{lotteryPrize}",
     *     summary="Update a lottery prize line",
     *     tags={"🐧 Manager: Edition Lottery Prizes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="lotteryPrize", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Updated")
     * )
     */
    public function update(Request $request, EditionLotteryPrize $lotteryPrize)
    {
        $validated = $request->validate($this->lineValidation(true));
        $lotteryPrize->update($validated);
        $lotteryPrize->load(['prize', 'sponsor', 'lottery']);

        return $this->resourceResponse(
            new EditionLotteryPrizeResource($lotteryPrize),
            'Lottery prize updated successfully'
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/manager/edition-lottery-prizes/{lotteryPrize}",
     *     summary="Partially update a lottery prize line",
     *     tags={"🐧 Manager: Edition Lottery Prizes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="lotteryPrize", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Updated")
     * )
     */
    public function patch(Request $request, EditionLotteryPrize $lotteryPrize)
    {
        $validated = $request->validate($this->lineValidation(false));
        $lotteryPrize->update($validated);
        $lotteryPrize->load(['prize', 'sponsor', 'lottery']);

        return $this->resourceResponse(
            new EditionLotteryPrizeResource($lotteryPrize),
            'Lottery prize partially updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/edition-lottery-prizes/{lotteryPrize}",
     *     summary="Delete a lottery prize line",
     *     tags={"🐧 Manager: Edition Lottery Prizes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="lotteryPrize", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted"),
     *     @OA\Response(response=409, description="Linked rules")
     * )
     */
    public function destroy(EditionLotteryPrize $lotteryPrize)
    {
        return $this->deleteResourceWithResponse(
            $lotteryPrize,
            ['rules' => 'rules_count'],
            'lottery prize',
            'Lottery prize deleted successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/edition-lottery-prizes/bulk",
     *     summary="Bulk delete lottery prize lines",
     *     tags={"🐧 Manager: Edition Lottery Prizes"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Deleted")
     * )
     */
    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'lottery_prize_ids' => 'required|array|min:1',
            'lottery_prize_ids.*' => 'integer',
        ]);

        return $this->deleteBulkResourcesWithResponse(
            $request->lottery_prize_ids,
            EditionLotteryPrize::class,
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
            'lottery_id' => $required . '|integer|exists:edition_lotteries,id_lottery',
            'prize_id' => $required . '|integer|exists:prizes,id_prize',
            'prize_quantity' => $required . '|integer|min:1',
            'sponsor_id' => 'nullable|integer|exists:sponsors,id_sponsor',
            'display_order' => 'nullable|integer',
            'rules' => 'prohibited',
        ];
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

    private function assemblyEditionDivisionId(Request $request): ?int
    {
        $raw = $request->query('edition_division_id');
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
