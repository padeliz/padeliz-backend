<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Resources\EditionLotteryRuleResource;
use App\Models\EditionLottery;
use App\Models\EditionLotteryPrize;
use App\Models\EditionLotteryRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Edition Lottery Rules",
 *     description="(v1) Edition lottery rules management"
 * )
 */
class EditionLotteryRuleController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/manager/edition-lottery-rules",
     *     summary="Get paginated list of lottery rules",
     *     tags={"🐧 Manager: Edition Lottery Rules"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(ref="#/components/parameters/page"),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", default=15, maximum=100)),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function index(Request $request)
    {
        $with = ['lotteryPrize.prize', 'lotteryPrize.sponsor', 'lotteryPrize.lottery', 'editionDivision'];

        $query = EditionLotteryRule::with($with)->withCount('luckies');

        $lotteryId = $this->pullJoinFilter($request, 'lottery_id');
        if ($lotteryId !== null) {
            $ids = is_array($lotteryId) ? $lotteryId : [$lotteryId];
            $query->whereHas('lotteryPrize', fn ($q) => $q->whereIn('lottery_id', $ids));
        }

        $this->applyAdvancedPagination(
            $query,
            $request,
            EditionLotteryRule::$searchableFields,
            EditionLotteryRule::$filterableFields,
            EditionLotteryRule::$sortableFields
        );

        $paginationParams = $this->getPaginationParams($request);

        if (! $request->has('sort_by')) {
            $query->orderBy('orderliness')->orderBy('id_lottery_rule', 'desc');
        }

        $rules = $query->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);

        return EditionLotteryRuleResource::collection($rules);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/edition-lottery-rules/{lotteryRule}",
     *     summary="Get lottery rule details",
     *     tags={"🐧 Manager: Edition Lottery Rules"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="lotteryRule", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function show(EditionLotteryRule $lotteryRule)
    {
        $lotteryRule->load([
            'lotteryPrize.prize',
            'lotteryPrize.sponsor',
            'lotteryPrize.lottery',
            'editionDivision',
        ]);
        $lotteryRule->loadCount('luckies');

        return $this->resourceResponse(
            new EditionLotteryRuleResource($lotteryRule),
            'Lottery rule details retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/edition-lottery-rules/reorder",
     *     summary="Rewrite orderliness for every rule under one lottery",
     *     tags={"🐧 Manager: Edition Lottery Rules"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"lottery_id", "lottery_rule_ids"},
     *             @OA\Property(property="lottery_id", type="integer"),
     *             @OA\Property(
     *                 property="lottery_rule_ids",
     *                 type="array",
     *                 description="Must list every rule under the lottery's prize lines exactly once, in desired order",
     *                 @OA\Items(type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Reordered"),
     *     @OA\Response(response=409, description="Edition is finalized or lottery has luckies"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'lottery_id' => 'required|integer|exists:edition_lotteries,id_lottery',
            'lottery_rule_ids' => 'required|array|min:1',
            'lottery_rule_ids.*' => 'integer|distinct',
        ]);

        $lotteryId = (int) $validated['lottery_id'];
        $lottery = EditionLottery::with('edition')->find($lotteryId);
        if ($lottery?->edition?->finalized_at !== null) {
            return $this->errorResponse('Edition is finalized.', 409);
        }

        if ($lottery->lotteryRules()->whereHas('luckies')->exists()) {
            return $this->errorResponse('Lottery already has luckies.', 409);
        }

        $existing = EditionLotteryRule::query()
            ->whereHas('lotteryPrize', static fn ($q) => $q->where('lottery_id', $lotteryId))
            ->get()
            ->keyBy('id_lottery_rule');

        $requestedIds = array_map('intval', $validated['lottery_rule_ids']);

        $missing = $existing->keys()
            ->map(static fn ($id): int => (int) $id)
            ->diff($requestedIds)
            ->values()
            ->all();

        if ($missing !== []) {
            return $this->errorResponse(
                'lottery_rule_ids must include every rule of this lottery.',
                422,
                ['missing_lottery_rule_ids' => $missing]
            );
        }

        $unknown = array_values(array_diff(
            $requestedIds,
            $existing->keys()->map(static fn ($id): int => (int) $id)->all()
        ));
        if ($unknown !== []) {
            return $this->errorResponse(
                'One or more lottery rules do not belong to this lottery.',
                422,
                ['unknown_lottery_rule_ids' => $unknown]
            );
        }

        $reordered = DB::transaction(function () use ($requestedIds, $existing, $lotteryId) {
            foreach ($requestedIds as $index => $ruleId) {
                $rule = $existing->get($ruleId);
                $rule->orderliness = $index;
                $rule->save();
            }

            return EditionLotteryRule::query()
                ->with(['lotteryPrize.prize', 'lotteryPrize.sponsor', 'lotteryPrize.lottery', 'editionDivision'])
                ->withCount('luckies')
                ->whereHas('lotteryPrize', static fn ($q) => $q->where('lottery_id', $lotteryId))
                ->orderBy('orderliness')
                ->orderBy('id_lottery_rule')
                ->get();
        });

        return $this->successResponse(
            EditionLotteryRuleResource::collection($reordered),
            'Lottery rules reordered successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/edition-lottery-rules",
     *     summary="Create a lottery rule",
     *     tags={"🐧 Manager: Edition Lottery Rules"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"lottery_prize_id", "rule_prize_quantity"},
     *             @OA\Property(property="lottery_prize_id", type="integer"),
     *             @OA\Property(property="rule_prize_quantity", type="integer"),
     *             @OA\Property(property="orderliness", type="integer"),
     *             @OA\Property(property="edition_division_id", type="integer", nullable=true),
     *             @OA\Property(property="gender", type="string", enum={"MALE", "FEMALE"}, nullable=true),
     *             @OA\Property(property="has_social_status", type="string", enum={"STUDENT", "ADULT"}, nullable=true),
     *             @OA\Property(property="legal_age", type="string", enum={"MINOR", "MAJOR"}, nullable=true),
     *             @OA\Property(property="is_younger_than", type="integer", nullable=true),
     *             @OA\Property(property="is_older_than", type="integer", nullable=true),
     *             @OA\Property(property="has_tshirt_size", type="string", nullable=true),
     *             @OA\Property(property="is_guest", type="boolean", nullable=true),
     *             @OA\Property(property="is_padel_first_timer", type="boolean", nullable=true),
     *             @OA\Property(property="is_event_first_timer", type="boolean", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Created"),
     *     @OA\Response(response=409, description="Edition is finalized")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->ruleValidation(true));

        if ($response = $this->refuseIfEditionFinalizedForPrizeId((int) $validated['lottery_prize_id'])) {
            return $response;
        }

        $validated['orderliness'] = $validated['orderliness'] ?? 0;

        $rule = EditionLotteryRule::create($validated);
        $rule->load(['lotteryPrize.prize', 'lotteryPrize.sponsor', 'lotteryPrize.lottery', 'editionDivision']);
        $rule->loadCount('luckies');

        return $this->resourceResponse(
            new EditionLotteryRuleResource($rule),
            'Lottery rule created successfully',
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/manager/edition-lottery-rules/{lotteryRule}",
     *     summary="Update a lottery rule",
     *     tags={"🐧 Manager: Edition Lottery Rules"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="lotteryRule", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Updated"),
     *     @OA\Response(response=409, description="Edition is finalized")
     * )
     */
    public function update(Request $request, EditionLotteryRule $lotteryRule)
    {
        if ($response = $this->refuseIfEditionFinalizedForRule($lotteryRule)) {
            return $response;
        }

        $validated = $request->validate($this->ruleValidation(true));

        if (isset($validated['lottery_prize_id'])
            && ($response = $this->refuseIfEditionFinalizedForPrizeId((int) $validated['lottery_prize_id']))) {
            return $response;
        }

        $lotteryRule->update($validated);
        $lotteryRule->load(['lotteryPrize.prize', 'lotteryPrize.sponsor', 'lotteryPrize.lottery', 'editionDivision']);
        $lotteryRule->loadCount('luckies');

        return $this->resourceResponse(
            new EditionLotteryRuleResource($lotteryRule),
            'Lottery rule updated successfully'
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/manager/edition-lottery-rules/{lotteryRule}",
     *     summary="Partially update a lottery rule",
     *     tags={"🐧 Manager: Edition Lottery Rules"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="lotteryRule", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Updated"),
     *     @OA\Response(response=409, description="Edition is finalized")
     * )
     */
    public function patch(Request $request, EditionLotteryRule $lotteryRule)
    {
        if ($response = $this->refuseIfEditionFinalizedForRule($lotteryRule)) {
            return $response;
        }

        $validated = $request->validate($this->ruleValidation(false));

        if (isset($validated['lottery_prize_id'])
            && ($response = $this->refuseIfEditionFinalizedForPrizeId((int) $validated['lottery_prize_id']))) {
            return $response;
        }

        $lotteryRule->update($validated);
        $lotteryRule->load(['lotteryPrize.prize', 'lotteryPrize.sponsor', 'lotteryPrize.lottery', 'editionDivision']);
        $lotteryRule->loadCount('luckies');

        return $this->resourceResponse(
            new EditionLotteryRuleResource($lotteryRule),
            'Lottery rule partially updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/edition-lottery-rules/{lotteryRule}",
     *     summary="Delete a lottery rule",
     *     tags={"🐧 Manager: Edition Lottery Rules"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="lotteryRule", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted"),
     *     @OA\Response(response=409, description="Linked luckies or edition finalized")
     * )
     */
    public function destroy(EditionLotteryRule $lotteryRule)
    {
        if ($response = $this->refuseIfEditionFinalizedForRule($lotteryRule)) {
            return $response;
        }

        return $this->deleteResourceWithResponse(
            $lotteryRule,
            ['luckies' => 'luckies_count'],
            'lottery rule',
            'Lottery rule deleted successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/edition-lottery-rules/bulk",
     *     summary="Bulk delete lottery rules",
     *     tags={"🐧 Manager: Edition Lottery Rules"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Deleted"),
     *     @OA\Response(response=409, description="Edition is finalized")
     * )
     */
    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'lottery_rule_ids' => 'required|array|min:1',
            'lottery_rule_ids.*' => 'integer',
        ]);

        $rules = EditionLotteryRule::query()
            ->with('lotteryPrize.lottery.edition')
            ->whereIn('id_lottery_rule', $request->lottery_rule_ids)
            ->get();

        foreach ($rules as $rule) {
            if ($rule->lotteryPrize?->lottery?->edition?->finalized_at !== null) {
                return $this->errorResponse('Edition is finalized.', 409);
            }
        }

        return $this->deleteBulkResourcesWithResponse(
            $request->lottery_rule_ids,
            EditionLotteryRule::class,
            ['luckies' => 'luckies_count'],
            'lottery rule',
            'Lottery rules deleted successfully'
        );
    }

    /**
     * @return array<string, string>
     */
    private function ruleValidation(bool $requireCore): array
    {
        $required = $requireCore ? 'required' : 'sometimes';

        return [
            'lottery_prize_id' => $required . '|integer|exists:edition_lottery_prizes,id_lottery_prize',
            'rule_prize_quantity' => $required . '|integer|min:1',
            'orderliness' => 'nullable|integer|min:0',
            'edition_division_id' => 'nullable|integer|exists:edition_divisions,id_edition_division',
            'gender' => 'nullable|in:MALE,FEMALE',
            'has_social_status' => 'nullable|in:STUDENT,ADULT',
            'legal_age' => 'nullable|in:MINOR,MAJOR',
            'is_younger_than' => 'nullable|integer|min:0',
            'is_older_than' => 'nullable|integer|min:0',
            'has_tshirt_size' => 'nullable|in:XS,S,M,L,XL,XXL',
            'is_guest' => 'nullable|boolean',
            'is_padel_first_timer' => 'nullable|boolean',
            'is_event_first_timer' => 'nullable|boolean',
        ];
    }

    private function refuseIfEditionFinalizedForPrizeId(int $lotteryPrizeId): ?JsonResponse
    {
        $prize = EditionLotteryPrize::with('lottery.edition')->find($lotteryPrizeId);
        if ($prize?->lottery?->edition?->finalized_at !== null) {
            return $this->errorResponse('Edition is finalized.', 409);
        }

        return null;
    }

    private function refuseIfEditionFinalizedForRule(EditionLotteryRule $lotteryRule): ?JsonResponse
    {
        $lotteryRule->loadMissing('lotteryPrize.lottery.edition');
        if ($lotteryRule->lotteryPrize?->lottery?->edition?->finalized_at !== null) {
            return $this->errorResponse('Edition is finalized.', 409);
        }

        return null;
    }
}
