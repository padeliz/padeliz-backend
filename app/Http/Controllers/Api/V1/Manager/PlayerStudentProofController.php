<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Http\Resources\PlayerStudentProofResource;
use App\Models\PlayerStudentProof;
use App\Services\StudentProof\StudentProofService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Player Student Proofs",
 *     description="(v1) Review-only student proofs; players create them"
 * )
 */
class PlayerStudentProofController extends V1BaseController
{
    public function __construct(
        private readonly StudentProofService $proofs,
    ) {
        parent::__construct();
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/player-student-proofs",
     *     summary="List student proofs",
     *     tags={"🐧 Manager: Player Student Proofs"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="filters[review_status]", in="query", @OA\Schema(type="string", enum={"pending","approved","rejected","discarded"})),
     *     @OA\Parameter(name="filters[ready_for_review]", in="query", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="filters[player_id]", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Paginated student proofs")
     * )
     */
    public function index(Request $request)
    {
        $query = PlayerStudentProof::with(['player', 'files']);

        $this->applyAdvancedPagination(
            $query,
            $request,
            PlayerStudentProof::$searchableFields,
            PlayerStudentProof::$filterableFields,
            PlayerStudentProof::$sortableFields
        );

        $paginationParams = $this->getPaginationParams($request);

        if (! $request->has('sort_by')) {
            $query->orderBy('id_player_student_proof', 'desc');
        }

        $proofs = $query->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);

        return PlayerStudentProofResource::collection($proofs);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/player-student-proofs/{playerStudentProof}",
     *     summary="Get a student proof",
     *     tags={"🐧 Manager: Player Student Proofs"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="playerStudentProof", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Student proof"),
     *     @OA\Response(response=404, description="Not found", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function show(PlayerStudentProof $playerStudentProof): JsonResponse
    {
        $playerStudentProof->load(['player', 'files']);

        return $this->resourceResponse(
            new PlayerStudentProofResource($playerStudentProof),
            'Student proof retrieved successfully'
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/manager/player-student-proofs/{playerStudentProof}/approve",
     *     summary="Approve a pending student proof",
     *     tags={"🐧 Manager: Player Student Proofs"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="playerStudentProof", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="review_message", type="string", nullable=true),
     *             @OA\Property(property="student_until", type="string", format="date", nullable=true, description="Optional correction of the validity date")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Approved"),
     *     @OA\Response(response=422, description="Already reviewed", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function approve(Request $request, PlayerStudentProof $playerStudentProof): JsonResponse
    {
        $validated = $request->validate([
            'review_message' => 'nullable|string',
            'student_until' => 'nullable|date_format:Y-m-d',
        ]);

        $this->proofs->approve(
            $playerStudentProof,
            $validated['review_message'] ?? null,
            $validated['student_until'] ?? null,
        );

        $playerStudentProof->unsetRelations()->load(['player', 'files']);

        return $this->resourceResponse(
            new PlayerStudentProofResource($playerStudentProof),
            'Student proof approved successfully'
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/manager/player-student-proofs/{playerStudentProof}/reject",
     *     summary="Reject a pending student proof",
     *     tags={"🐧 Manager: Player Student Proofs"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="playerStudentProof", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(@OA\Property(property="review_message", type="string", nullable=true))
     *     ),
     *     @OA\Response(response=200, description="Rejected"),
     *     @OA\Response(response=422, description="Already reviewed", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function reject(Request $request, PlayerStudentProof $playerStudentProof): JsonResponse
    {
        $validated = $request->validate([
            'review_message' => 'nullable|string',
        ]);

        $this->proofs->reject($playerStudentProof, $validated['review_message'] ?? null);

        $playerStudentProof->unsetRelations()->load(['player', 'files']);

        return $this->resourceResponse(
            new PlayerStudentProofResource($playerStudentProof),
            'Student proof rejected successfully'
        );
    }
}
