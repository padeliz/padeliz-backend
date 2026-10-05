<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Resources\EventLotteryResource;
use App\Http\Resources\EventLotteryRuleResource;
use App\Models\EventDivision;
use App\Models\EventLottery;
use App\Services\Lottery\LotteryRuleGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="🐧 Manager: PadelEvent Lotteries",
 *     description="(v1) PadelEvent lottery management and operations"
 * )
 */
class EventLotteryController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/manager/event-lotteries",
     *     summary="Get paginated list of lotteries",
     *     tags={"🐧 Manager: PadelEvent Lotteries"},
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
     *             @OA\Property(property="event_id", type="string", description="Filter by edition ID"),
     *             @OA\Property(property="name", type="string", description="Filter by name"),
     *             @OA\Property(property="display_order", type="string", description="Filter by display order")
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
     *             @OA\Property(property="id_lottery", type="string", enum={"asc", "desc"}, description="Sort by lottery ID"),
     *             @OA\Property(property="event_id", type="string", enum={"asc", "desc"}, description="Sort by edition ID"),
     *             @OA\Property(property="name", type="string", enum={"asc", "desc"}, description="Sort by name"),
     *             @OA\Property(property="display_order", type="string", enum={"asc", "desc"}, description="Sort by display order"),
     *             @OA\Property(property="inserted_at", type="string", enum={"asc", "desc"}, description="Sort by creation date"),
     *             @OA\Property(property="updated_at", type="string", enum={"asc", "desc"}, description="Sort by update date")
     *         )
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
     *                         @OA\Items(ref="#/components/schemas/EventLottery")
     *                     )
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied. Required user type: manager",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = EventLottery::with(['event', 'lotteryPrizes.prize', 'lotteryPrizes.sponsor'])
            ->withExists(['lotteryRules as has_luckies' => fn ($q) => $q->whereHas('luckies')]);

        // Apply advanced pagination
        $this->applyAdvancedPagination(
            $query,
            $request,
            EventLottery::$searchableFields,
            EventLottery::$filterableFields,
            EventLottery::$sortableFields
        );

        // Get pagination parameters
        $paginationParams = $this->getPaginationParams($request);

        // Apply default sorting if no sorting is specified
        if (! $request->has('sort_by')) {
            $query->orderBy('id_lottery', 'desc');
        }

        $lotteries = $query->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);

        return EventLotteryResource::collection($lotteries);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/event-lotteries/{lottery}",
     *     summary="Get lottery details",
     *     tags={"🐧 Manager: PadelEvent Lotteries"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="lottery",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lottery details",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/EventLottery")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied. Required user type: manager",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Lottery not found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function show(EventLottery $lottery)
    {
        $lottery->load([
            'event',
            'lotteryPrizes.prize',
            'lotteryPrizes.sponsor',
        ]);
        $lottery->loadExists(['lotteryRules as has_luckies' => fn ($q) => $q->whereHas('luckies')]);

        return $this->resourceResponse(
            new EventLotteryResource($lottery),
            'Lottery details retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/event-lotteries/{lottery}/rules/generate",
     *     summary="Generate or recreate balanced lottery rules",
     *     tags={"🐧 Manager: PadelEvent Lotteries"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="lottery", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Rules generated"),
     *     @OA\Response(response=409, description="Blocked: finalized, has luckies, no divisions, or no prize lines")
     * )
     */
    public function generateRules(EventLottery $lottery, LotteryRuleGeneratorService $generator)
    {
        $lottery->loadMissing('event');
        if ($lottery->event?->finalized_at !== null) {
            return $this->errorResponse('PadelEvent is finalized.', 409);
        }

        if ($lottery->lotteryRules()->whereHas('luckies')->exists()) {
            return $this->errorResponse('Lottery already has luckies.', 409);
        }

        $prizeLines = $lottery->lotteryPrizes()->orderBy('display_order')->orderBy('id_lottery_prize')->get();
        if ($prizeLines->isEmpty()) {
            return $this->errorResponse('Lottery has no prize lines.', 409);
        }

        $divisionIds = EventDivision::query()
            ->where('event_id', $lottery->event_id)
            ->orderBy('skill_order')
            ->orderBy('id_event_division')
            ->pluck('id_event_division')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        if ($divisionIds === []) {
            return $this->errorResponse('PadelEvent has no divisions.', 409);
        }

        $rules = $generator->regenerate($lottery, $prizeLines, $divisionIds);

        return $this->successResponse(
            EventLotteryRuleResource::collection($rules),
            'Lottery rules generated successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/event-lotteries",
     *     summary="Create a new lottery",
     *     tags={"🐧 Manager: PadelEvent Lotteries"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"event_id", "name"},
     *             @OA\Property(property="event_id", type="integer", description="PadelEvent ID"),
     *             @OA\Property(property="name", type="string", maxLength=60, description="Lottery name"),
     *             @OA\Property(property="display_order", type="integer", description="Display order")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Lottery created successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/EventLottery")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied. Required user type: manager",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_id' => 'required|integer|exists:events,id_event',
            'name' => [
                'required',
                'string',
                'max:60',
                Rule::unique('event_lotteries', 'name')->where(
                    fn ($q) => $q->where('event_id', $request->input('event_id'))
                ),
            ],
            'display_order' => 'nullable|integer',
            'prize_id' => 'prohibited',
            'prize_quantity' => 'prohibited',
            'rules' => 'prohibited',
        ]);

        unset($validated['prize_id'], $validated['prize_quantity'], $validated['rules']);
        $validated['display_order'] = $validated['display_order'] ?? 0;

        $lottery = EventLottery::create($validated);
        $lottery->load(['event', 'lotteryPrizes.prize', 'lotteryPrizes.sponsor']);

        return $this->resourceResponse(
            new EventLotteryResource($lottery),
            'Lottery created successfully',
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/manager/event-lotteries/{lottery}",
     *     summary="Update lottery details (complete replacement)",
     *     tags={"🐧 Manager: PadelEvent Lotteries"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="lottery",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="event_id", type="integer", description="PadelEvent ID"),
     *             @OA\Property(property="name", type="string", maxLength=60, description="Lottery name"),
     *             @OA\Property(property="display_order", type="integer", description="Display order")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lottery updated successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/EventLottery")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied. Required user type: manager",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Lottery not found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function update(Request $request, EventLottery $lottery)
    {
        $validated = $request->validate($this->lotteryValidation($request, $lottery, true));

        $lottery->update($validated);
        $lottery->load(['event', 'lotteryPrizes.prize', 'lotteryPrizes.sponsor']);

        return $this->resourceResponse(
            new EventLotteryResource($lottery),
            'Lottery updated successfully'
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/manager/event-lotteries/{lottery}",
     *     summary="Partially update lottery details",
     *     tags={"🐧 Manager: PadelEvent Lotteries"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="lottery",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="event_id", type="integer", description="PadelEvent ID"),
     *             @OA\Property(property="name", type="string", maxLength=60, description="Lottery name"),
     *             @OA\Property(property="display_order", type="integer", description="Display order")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lottery partially updated successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/EventLottery")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied. Required user type: manager",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Lottery not found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function patch(Request $request, EventLottery $lottery)
    {
        $validated = $request->validate($this->lotteryValidation($request, $lottery, false));

        $lottery->update($validated);
        $lottery->load(['event', 'lotteryPrizes.prize', 'lotteryPrizes.sponsor']);

        return $this->resourceResponse(
            new EventLotteryResource($lottery),
            'Lottery partially updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/event-lotteries/{lottery}",
     *     summary="Delete a lottery",
     *     tags={"🐧 Manager: PadelEvent Lotteries"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="lottery",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lottery deleted successfully",
     *         @OA\JsonContent(ref="#/components/schemas/ApiResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied. Required user type: manager",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Lottery not found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Lottery cannot be deleted due to linked data",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiErrorResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="linked_data",
     *                         type="object",
     *                         description="Information about linked data preventing deletion",
     *                         @OA\Property(property="lottery_prizes_count", type="integer", example=2)
     *                     )
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function destroy(EventLottery $lottery)
    {
        $linkedDataRelations = [
            'lotteryPrizes' => 'lottery_prizes_count',
        ];

        return $this->deleteResourceWithResponse(
            $lottery,
            $linkedDataRelations,
            'lottery',
            'Lottery deleted successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/event-lotteries/bulk",
     *     summary="Bulk delete lotteries",
     *     tags={"🐧 Manager: PadelEvent Lotteries"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"lottery_ids"},
     *             @OA\Property(
     *                 property="lottery_ids",
     *                 type="array",
     *                 description="Array of lottery IDs to delete",
     *                 @OA\Items(type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="All lotteries deleted successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="data",
     *                         type="object",
     *                         @OA\Property(property="successful", type="array", @OA\Items(type="object")),
     *                         @OA\Property(property="failed", type="array", @OA\Items(type="object")),
     *                         @OA\Property(property="total_requested", type="integer"),
     *                         @OA\Property(property="total_successful", type="integer"),
     *                         @OA\Property(property="total_failed", type="integer")
     *                     )
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=207,
     *         description="Some lotteries failed to delete (Multi-Status)",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiErrorResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="errors",
     *                         type="object",
     *                         @OA\Property(property="successful", type="array", @OA\Items(type="object")),
     *                         @OA\Property(property="failed", type="array", @OA\Items(type="object")),
     *                         @OA\Property(property="total_requested", type="integer"),
     *                         @OA\Property(property="total_successful", type="integer"),
     *                         @OA\Property(property="total_failed", type="integer")
     *                     )
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="No lotteries provided for deletion",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied. Required user type: manager",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="All lotteries failed to delete due to linked data",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiErrorResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="errors",
     *                         type="object",
     *                         @OA\Property(property="successful", type="array", @OA\Items(type="object")),
     *                         @OA\Property(property="total_requested", type="integer"),
     *                         @OA\Property(property="total_successful", type="integer"),
     *                         @OA\Property(property="total_failed", type="integer")
     *                     )
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'lottery_ids' => 'required|array|min:1',
            'lottery_ids.*' => 'integer',
        ]);

        $linkedDataRelations = [
            'lotteryPrizes' => 'lottery_prizes_count',
        ];

        return $this->deleteBulkResourcesWithResponse(
            $request->lottery_ids,
            EventLottery::class,
            $linkedDataRelations,
            'lottery',
            'Lotteries deleted successfully'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function lotteryValidation(Request $request, EventLottery $lottery, bool $requireCore): array
    {
        $required = $requireCore ? 'required' : 'sometimes';
        $editionId = $request->input('event_id', $lottery->event_id);

        return [
            'event_id' => $required . '|integer|exists:events,id_event',
            'name' => [
                $required,
                'string',
                'max:60',
                Rule::unique('event_lotteries', 'name')
                    ->where(fn ($q) => $q->where('event_id', $editionId))
                    ->ignore($lottery->id_lottery, 'id_lottery'),
            ],
            'display_order' => 'nullable|integer',
            'prize_id' => 'prohibited',
            'prize_quantity' => 'prohibited',
            'rules' => 'prohibited',
        ];
    }
}
