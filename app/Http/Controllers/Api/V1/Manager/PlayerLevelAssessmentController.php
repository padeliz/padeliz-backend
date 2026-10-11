<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Http\Resources\PlayerLevelAssessmentResource;
use App\Models\PlayerLevelAssessment;
use App\Services\PlayerLevel\LevelAssessmentService;
use App\Services\PlayerLevel\PlayerLevelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Review-only: players create assessments, managers approve or reject them.
 *
 * @OA\Tag(
 *     name="🐧 Manager: Player Level Assessments",
 *     description="(v1) Review player level assessments"
 * )
 */
class PlayerLevelAssessmentController extends V1BaseController
{
    private const SHOW_WITH = ['player', 'submission.values.field', 'playerLevel', 'profileImages'];

    public function __construct(
        private readonly LevelAssessmentService $assessments,
        private readonly PlayerLevelService $playerLevels,
    ) {
        parent::__construct();
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/player-level-assessments",
     *     summary="Get all player level assessments",
     *     tags={"🐧 Manager: Player Level Assessments"},
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
     *         name="q",
     *         in="query",
     *         description="Search term for review_message",
     *         required=false,
     *         @OA\Schema(type="string")
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
     *             @OA\Property(property="player_user_id", type="string", description="Filter by player ID"),
     *             @OA\Property(property="review_status", type="string", description="pending, approved, rejected or discarded"),
     *             @OA\Property(property="ready_for_review", type="string", description="Filter by ready flag (use 0 or 1)")
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
     *             @OA\Property(property="id_player_level_assessment", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="player_user_id", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="review_status", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="ready_for_review", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="submitted_at", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="resubmitted_at", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="reviewed_at", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="inserted_at", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="updated_at", type="string", enum={"asc", "desc"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of player level assessments",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiPaginatedResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/PlayerLevelAssessment"))
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=403, description="Access denied. Required user type: manager", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function index(Request $request)
    {
        $query = PlayerLevelAssessment::with(['player', 'playerLevel']);

        $this->applyAdvancedPagination(
            $query,
            $request,
            PlayerLevelAssessment::$searchableFields,
            PlayerLevelAssessment::$filterableFields,
            PlayerLevelAssessment::$sortableFields
        );

        $paginationParams = $this->getPaginationParams($request);

        if (! $request->has('sort_by')) {
            $query->orderBy('id_player_level_assessment', 'desc');
        }

        $assessments = $query->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);

        return PlayerLevelAssessmentResource::collection($assessments);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/player-level-assessments/{playerLevelAssessment}",
     *     summary="Get player level assessment details",
     *     tags={"🐧 Manager: Player Level Assessments"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="playerLevelAssessment", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(@OA\Property(property="data", ref="#/components/schemas/PlayerLevelAssessment"))
     *             }
     *         )
     *     ),
     *     @OA\Response(response=404, description="Assessment not found", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function show(PlayerLevelAssessment $playerLevelAssessment): JsonResponse
    {
        $playerLevelAssessment->load(self::SHOW_WITH);

        return $this->resourceResponse(
            new PlayerLevelAssessmentResource($playerLevelAssessment),
            'Level assessment details retrieved successfully'
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/manager/player-level-assessments/{playerLevelAssessment}/approve",
     *     summary="Approve a pending player level assessment and assign a level",
     *     tags={"🐧 Manager: Player Level Assessments"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="playerLevelAssessment", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"level"},
     *             @OA\Property(property="level", type="string", example="3.50", description="Exactly two decimals, inside a seeded level band"),
     *             @OA\Property(property="review_message", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=200, description="Assessment approved"),
     *     @OA\Response(response=404, description="Assessment not found", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=422, description="Validation error or assessment already reviewed", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function approve(Request $request, PlayerLevelAssessment $playerLevelAssessment): JsonResponse
    {
        $validated = $request->validate([
            'level' => 'required|numeric',
            'review_message' => 'nullable|string',
        ]);

        // Rejects anything that is not exactly two decimals or sits outside the seeded bands.
        $level = $this->playerLevels->normalizeLevel(
            is_string($request->input('level')) ? $request->input('level') : (float) $request->input('level')
        );

        $this->assessments->approve(
            $playerLevelAssessment,
            $level,
            $validated['review_message'] ?? null,
            (int) $request->user()->id_user
        );

        $playerLevelAssessment->unsetRelations()->load(self::SHOW_WITH);

        return $this->resourceResponse(
            new PlayerLevelAssessmentResource($playerLevelAssessment),
            'Level assessment approved successfully'
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/manager/player-level-assessments/{playerLevelAssessment}/reject",
     *     summary="Reject a pending player level assessment",
     *     description="The player's existing levels are kept untouched.",
     *     tags={"🐧 Manager: Player Level Assessments"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="playerLevelAssessment", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(@OA\Property(property="review_message", type="string", nullable=true))
     *     ),
     *     @OA\Response(response=200, description="Assessment rejected"),
     *     @OA\Response(response=404, description="Assessment not found", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=422, description="Validation error or assessment already reviewed", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function reject(Request $request, PlayerLevelAssessment $playerLevelAssessment): JsonResponse
    {
        $validated = $request->validate([
            'review_message' => 'nullable|string',
        ]);

        $this->assessments->reject(
            $playerLevelAssessment,
            $validated['review_message'] ?? null,
            (int) $request->user()->id_user
        );

        $playerLevelAssessment->unsetRelations()->load(self::SHOW_WITH);

        return $this->resourceResponse(
            new PlayerLevelAssessmentResource($playerLevelAssessment),
            'Level assessment rejected successfully'
        );
    }
}
