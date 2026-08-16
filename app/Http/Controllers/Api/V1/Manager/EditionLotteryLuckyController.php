<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Resources\EditionLotteryLuckyResource;
use App\Models\EditionLotteryLucky;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Edition Lottery Luckies",
 *     description="(v1) Edition lottery lucky management and operations"
 * )
 */
class EditionLotteryLuckyController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/manager/edition-lottery-luckies",
     *     summary="Get all lottery luckies",
     *     tags={"🐧 Manager: Edition Lottery Luckies"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(ref="#/components/parameters/page"),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15, maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="filters",
     *         in="query",
     *         description="Filter parameters",
     *         required=false,
     *         style="deepObject",
     *         explode=true,
     *         @OA\Schema(
     *             type="object",
     *             additionalProperties=false,
     *             @OA\Property(property="lottery_rule_id", type="string", description="Filter by lottery rule ID"),
     *             @OA\Property(property="drawn_participation_id", type="string", description="Filter by drawn participation ID")
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort parameters",
     *         required=false,
     *         style="deepObject",
     *         explode=true,
     *         @OA\Schema(
     *             type="object",
     *             additionalProperties=false,
     *             @OA\Property(property="id_lucky", type="string", enum={"asc", "desc"}, description="Sort by lucky ID"),
     *             @OA\Property(property="lottery_rule_id", type="string", enum={"asc", "desc"}, description="Sort by lottery rule ID"),
     *             @OA\Property(property="drawn_participation_id", type="string", enum={"asc", "desc"}, description="Sort by drawn participation ID"),
     *             @OA\Property(property="drawn_at", type="string", enum={"asc", "desc"}, description="Sort by drawn at"),
     *             @OA\Property(property="available_at", type="string", enum={"asc", "desc"}, description="Sort by available at"),
     *             @OA\Property(property="announced_at", type="string", enum={"asc", "desc"}, description="Sort by announced at"),
     *             @OA\Property(property="accepted_at", type="string", enum={"asc", "desc"}, description="Sort by accepted at"),
     *             @OA\Property(property="rejected_at", type="string", enum={"asc", "desc"}, description="Sort by rejected at"),
     *             @OA\Property(property="inserted_at", type="string", enum={"asc", "desc"}, description="Sort by creation date"),
     *             @OA\Property(property="updated_at", type="string", enum={"asc", "desc"}, description="Sort by update date")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of lottery luckies",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiPaginatedResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="data",
     *                         type="array",
     *                         @OA\Items(ref="#/components/schemas/EditionLotteryLucky")
     *                     )
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=403, description="Access denied. Required user type: manager", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=500, description="Internal server error", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function index(Request $request)
    {
        $query = EditionLotteryLucky::with(['drawnParticipation.player', 'lotteryRule.lottery.prize']);

        $this->applyAdvancedPagination(
            $query,
            $request,
            EditionLotteryLucky::$searchableFields,
            EditionLotteryLucky::$filterableFields,
            EditionLotteryLucky::$sortableFields
        );

        $paginationParams = $this->getPaginationParams($request);

        if (! $request->has('sort_by')) {
            $query->orderBy('id_lucky', 'desc');
        }

        $luckies = $query->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);

        return EditionLotteryLuckyResource::collection($luckies);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/edition-lottery-luckies/{lucky}",
     *     summary="Get lottery lucky details",
     *     tags={"🐧 Manager: Edition Lottery Luckies"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="lucky", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Lucky details",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(@OA\Property(property="data", ref="#/components/schemas/EditionLotteryLucky"))
     *             }
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=403, description="Access denied. Required user type: manager", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=404, description="Lucky not found", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=500, description="Internal server error", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function show(EditionLotteryLucky $lucky)
    {
        $lucky->load(['drawnParticipation.player', 'lotteryRule.lottery.prize']);

        return $this->resourceResponse(
            new EditionLotteryLuckyResource($lucky),
            'Lucky details retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/edition-lottery-luckies",
     *     summary="Create a new lottery lucky",
     *     tags={"🐧 Manager: Edition Lottery Luckies"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"drawn_participation_id"},
     *             @OA\Property(property="lottery_rule_id", type="integer", description="Lottery rule ID", nullable=true),
     *             @OA\Property(property="drawn_participation_id", type="integer", description="Drawn participation ID"),
     *             @OA\Property(property="drawn_at", type="integer", description="Drawn timestamp"),
     *             @OA\Property(property="available_at", type="integer", description="Available timestamp"),
     *             @OA\Property(property="announced_at", type="integer", description="Announced timestamp"),
     *             @OA\Property(property="accepted_at", type="integer", description="Accepted timestamp"),
     *             @OA\Property(property="rejected_at", type="integer", description="Rejected timestamp")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Lucky created successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(@OA\Property(property="data", ref="#/components/schemas/EditionLotteryLucky"))
     *             }
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=403, description="Access denied. Required user type: manager", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=500, description="Internal server error", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'lottery_rule_id' => 'nullable|integer|exists:edition_lottery_rules,id_lottery_rule',
            'drawn_participation_id' => 'required|integer|exists:edition_participations,id_participation',
            'drawn_at' => 'integer',
            'available_at' => 'integer',
            'announced_at' => 'nullable|integer',
            'accepted_at' => 'nullable|integer',
            'rejected_at' => 'nullable|integer',
        ]);

        $validated['drawn_at'] = $validated['drawn_at'] ?? time();
        $validated['available_at'] = $validated['available_at'] ?? time();

        if (! empty($validated['lottery_rule_id'])) {
            $existing = EditionLotteryLucky::where('lottery_rule_id', $validated['lottery_rule_id'])
                ->where('drawn_participation_id', $validated['drawn_participation_id'])
                ->first();

            if ($existing) {
                return $this->errorResponse(
                    'This participation is already a lucky for this lottery rule',
                    422
                );
            }
        }

        $lucky = EditionLotteryLucky::create($validated);
        $lucky->load(['drawnParticipation.player', 'lotteryRule.lottery.prize']);

        return $this->resourceResponse(
            new EditionLotteryLuckyResource($lucky),
            'Lucky created successfully',
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/manager/edition-lottery-luckies/{lucky}",
     *     summary="Update lottery lucky details (complete replacement)",
     *     tags={"🐧 Manager: Edition Lottery Luckies"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="lucky", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="lottery_rule_id", type="integer", description="Lottery rule ID", nullable=true),
     *             @OA\Property(property="drawn_participation_id", type="integer", description="Drawn participation ID"),
     *             @OA\Property(property="drawn_at", type="integer", description="Drawn timestamp"),
     *             @OA\Property(property="available_at", type="integer", description="Available timestamp"),
     *             @OA\Property(property="announced_at", type="integer", description="Announced timestamp"),
     *             @OA\Property(property="accepted_at", type="integer", description="Accepted timestamp"),
     *             @OA\Property(property="rejected_at", type="integer", description="Rejected timestamp")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lucky updated successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(@OA\Property(property="data", ref="#/components/schemas/EditionLotteryLucky"))
     *             }
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=403, description="Access denied. Required user type: manager", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=404, description="Lucky not found", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=500, description="Internal server error", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function update(Request $request, EditionLotteryLucky $lucky)
    {
        $validated = $request->validate([
            'lottery_rule_id' => 'nullable|integer|exists:edition_lottery_rules,id_lottery_rule',
            'drawn_participation_id' => 'required|integer|exists:edition_participations,id_participation',
            'drawn_at' => 'required|integer',
            'available_at' => 'required|integer',
            'announced_at' => 'nullable|integer',
            'accepted_at' => 'nullable|integer',
            'rejected_at' => 'nullable|integer',
        ]);

        if (! empty($validated['lottery_rule_id'])) {
            $existing = EditionLotteryLucky::where('id_lucky', '!=', $lucky->id_lucky)
                ->where('lottery_rule_id', $validated['lottery_rule_id'])
                ->where('drawn_participation_id', $validated['drawn_participation_id'])
                ->first();

            if ($existing) {
                return $this->errorResponse(
                    'This participation is already a lucky for this lottery rule',
                    422
                );
            }
        }

        $lucky->update($validated);
        $lucky->load(['drawnParticipation.player', 'lotteryRule.lottery.prize']);

        return $this->resourceResponse(
            new EditionLotteryLuckyResource($lucky),
            'Lucky updated successfully'
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/manager/edition-lottery-luckies/{lucky}",
     *     summary="Partially update lottery lucky details",
     *     tags={"🐧 Manager: Edition Lottery Luckies"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="lucky", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="lottery_rule_id", type="integer", description="Lottery rule ID", nullable=true),
     *             @OA\Property(property="drawn_participation_id", type="integer", description="Drawn participation ID"),
     *             @OA\Property(property="drawn_at", type="integer", description="Drawn timestamp"),
     *             @OA\Property(property="available_at", type="integer", description="Available timestamp"),
     *             @OA\Property(property="announced_at", type="integer", description="Announced timestamp"),
     *             @OA\Property(property="accepted_at", type="integer", description="Accepted timestamp"),
     *             @OA\Property(property="rejected_at", type="integer", description="Rejected timestamp")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lucky partially updated successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(@OA\Property(property="data", ref="#/components/schemas/EditionLotteryLucky"))
     *             }
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=403, description="Access denied. Required user type: manager", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=404, description="Lucky not found", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=500, description="Internal server error", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function patch(Request $request, EditionLotteryLucky $lucky)
    {
        $validated = $request->validate([
            'lottery_rule_id' => 'nullable|integer|exists:edition_lottery_rules,id_lottery_rule',
            'drawn_participation_id' => 'sometimes|integer|exists:edition_participations,id_participation',
            'drawn_at' => 'integer',
            'available_at' => 'integer',
            'announced_at' => 'nullable|integer',
            'accepted_at' => 'nullable|integer',
            'rejected_at' => 'nullable|integer',
        ]);

        $ruleId = array_key_exists('lottery_rule_id', $validated)
            ? $validated['lottery_rule_id']
            : $lucky->lottery_rule_id;
        $participationId = $validated['drawn_participation_id'] ?? $lucky->drawn_participation_id;

        if ($ruleId) {
            $existing = EditionLotteryLucky::where('id_lucky', '!=', $lucky->id_lucky)
                ->where('lottery_rule_id', $ruleId)
                ->where('drawn_participation_id', $participationId)
                ->first();

            if ($existing) {
                return $this->errorResponse(
                    'This participation is already a lucky for this lottery rule',
                    422
                );
            }
        }

        $lucky->update($validated);
        $lucky->load(['drawnParticipation.player', 'lotteryRule.lottery.prize']);

        return $this->resourceResponse(
            new EditionLotteryLuckyResource($lucky),
            'Lucky partially updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/edition-lottery-luckies/{lucky}",
     *     summary="Delete a lottery lucky",
     *     tags={"🐧 Manager: Edition Lottery Luckies"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="lucky", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Lucky deleted successfully", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=403, description="Access denied. Required user type: manager", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=404, description="Lucky not found", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=500, description="Internal server error", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function destroy(EditionLotteryLucky $lucky)
    {
        return $this->deleteResourceWithResponse(
            $lucky,
            [],
            'lucky',
            'Lucky deleted successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/edition-lottery-luckies/bulk",
     *     summary="Bulk delete lottery luckies",
     *     tags={"🐧 Manager: Edition Lottery Luckies"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"lucky_ids"},
     *             @OA\Property(
     *                 property="lucky_ids",
     *                 type="array",
     *                 description="Array of lucky IDs to delete",
     *                 @OA\Items(type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="All luckies deleted successfully", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
     *     @OA\Response(response=207, description="Some luckies failed to delete (Multi-Status)", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=400, description="No luckies provided for deletion", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=403, description="Access denied. Required user type: manager", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=500, description="Internal server error", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'lucky_ids' => 'required|array|min:1',
            'lucky_ids.*' => 'integer',
        ]);

        return $this->deleteBulkResourcesWithResponse(
            $request->lucky_ids,
            EditionLotteryLucky::class,
            [],
            'lucky',
            'Luckies deleted successfully'
        );
    }
}
