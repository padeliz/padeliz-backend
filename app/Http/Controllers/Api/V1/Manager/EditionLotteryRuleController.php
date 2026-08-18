<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Resources\EditionLotteryRuleResource;
use App\Models\EditionLotteryRule;
use Illuminate\Http\Request;

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

        $query = EditionLotteryRule::with($with);

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

        return $this->resourceResponse(
            new EditionLotteryRuleResource($lotteryRule),
            'Lottery rule details retrieved successfully'
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
     *     @OA\Response(response=201, description="Created")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->ruleValidation(true));

        $validated['orderliness'] = $validated['orderliness'] ?? 0;

        $rule = EditionLotteryRule::create($validated);
        $rule->load(['lotteryPrize.prize', 'lotteryPrize.sponsor', 'lotteryPrize.lottery', 'editionDivision']);

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
     *     @OA\Response(response=200, description="Updated")
     * )
     */
    public function update(Request $request, EditionLotteryRule $lotteryRule)
    {
        $validated = $request->validate($this->ruleValidation(true));
        $lotteryRule->update($validated);
        $lotteryRule->load(['lotteryPrize.prize', 'lotteryPrize.sponsor', 'lotteryPrize.lottery', 'editionDivision']);

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
     *     @OA\Response(response=200, description="Updated")
     * )
     */
    public function patch(Request $request, EditionLotteryRule $lotteryRule)
    {
        $validated = $request->validate($this->ruleValidation(false));
        $lotteryRule->update($validated);
        $lotteryRule->load(['lotteryPrize.prize', 'lotteryPrize.sponsor', 'lotteryPrize.lottery', 'editionDivision']);

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
     *     @OA\Response(response=409, description="Linked luckies")
     * )
     */
    public function destroy(EditionLotteryRule $lotteryRule)
    {
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
     *     @OA\Response(response=200, description="Deleted")
     * )
     */
    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'lottery_rule_ids' => 'required|array|min:1',
            'lottery_rule_ids.*' => 'integer',
        ]);

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
}
