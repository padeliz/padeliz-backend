<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Http\Resources\PlayerEligibilityResource;
use App\Models\PlayerEligibility;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Eligibilities",
 *     description="(v1) Eligibility management and operations"
 * )
 */
class EligibilityController extends V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/manager/eligibility",
     *     summary="Get all eligibility records",
     *     tags={"🐧 Manager: Eligibilities"},
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
     *         description="Search term for name, first_name, last_name, phone, playtomic_id, rankedin_id, instagram_username, facebook_username, review_message fields",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="filters",
     *         in="query",
     *         description="Filter parameters",
     *         required=false,
     *         @OA\Schema(
     *             type="object",
     *             @OA\Property(property="edition_id", type="integer"),
     *             @OA\Property(property="player_id", type="integer"),
     *             @OA\Property(property="skill_division", type="number"),
     *             @OA\Property(property="year_of_birth", type="integer"),
     *             @OA\Property(property="being_student", type="boolean"),
     *             @OA\Property(property="review_status", type="string", enum={"pending", "approved", "rejected"})
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort parameters",
     *         required=false,
     *         @OA\Schema(
     *             type="object",
     *             @OA\Property(property="edition_id", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="player_id", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="name", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="first_name", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="last_name", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="skill_division", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="year_of_birth", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="being_student", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="review_status", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="reviewed_at", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="inserted_at", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="updated_at", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="resubmitted_at", type="string", enum={"asc", "desc"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of all eligibility records",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiPaginatedResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="data",
     *                         type="array",
     *                         @OA\Items(ref="#/components/schemas/PlayerEligibility")
     *                     )
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = PlayerEligibility::with(['player']);

        // Apply advanced pagination
        $this->applyAdvancedPagination(
            $query,
            $request,
            PlayerEligibility::$searchableFields,
            PlayerEligibility::$filterableFields,
            PlayerEligibility::$sortableFields
        );

        // Get pagination parameters
        $paginationParams = $this->getPaginationParams($request);

        // Apply default sorting if no sorting is specified
        if (! $request->has('sort_by')) {
            $query->orderBy('id_eligibility', 'desc');
        }

        $eligibilities = $query->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);

        return PlayerEligibilityResource::collection($eligibilities);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/eligibility/{eligibility}",
     *     summary="Get eligibility details",
     *     tags={"🐧 Manager: Eligibilities"},
     *     @OA\Parameter(
     *         name="eligibility",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/PlayerEligibility")
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function show(PlayerEligibility $eligibility)
    {
        $eligibility->load('player');

        return $this->resourceResponse(
            new PlayerEligibilityResource($eligibility),
            'Eligibility details retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/eligibility",
     *     summary="Create a new eligibility record",
     *     tags={"🐧 Manager: Eligibilities"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"player_id", "status"},
     *             @OA\Property(property="player_id", type="integer", description="Player ID"),
     *             @OA\Property(property="status", type="string", enum={"approved", "rejected", "pending"}, description="Eligibility status"),
     *             @OA\Property(property="notes", type="string", description="Additional notes")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Eligibility created successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/PlayerEligibility")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'player_id' => 'required|integer|exists:players,id_player',
            'edition_id' => 'required|integer|exists:editions,id_edition',
            'review_status' => 'required|in:approved,rejected,pending',
            'notes' => 'nullable|string',
        ]);

        // Check if player already has an eligibility record for this edition
        $existingEligibility = PlayerEligibility::where('player_id', $validated['player_id'])
            ->where('edition_id', $validated['edition_id'])
            ->first();

        if ($existingEligibility) {
            return $this->errorResponse(
                'Player already has an eligibility record for this edition',
                422
            );
        }

        // Map the fields to the correct database columns
        $eligibilityData = [
            'player_id' => $validated['player_id'],
            'edition_id' => $validated['edition_id'],
            'review_status' => $validated['review_status'],
            'review_message' => $validated['notes'] ?? null,
            'name' => 'Player Eligibility', // Default name
        ];

        $eligibility = PlayerEligibility::create($eligibilityData);
        $eligibility->load('player');

        return $this->resourceResponse(
            new PlayerEligibilityResource($eligibility),
            'Eligibility created successfully',
            201
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/manager/eligibility/{eligibility}",
     *     summary="Update eligibility status",
     *     tags={"🐧 Manager: Eligibilities"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="eligibility",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(property="status", type="string", enum={"approved", "rejected", "pending"}),
     *             @OA\Property(property="notes", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Eligibility updated successfully"
     *     )
     * )
     */
    public function update(Request $request, PlayerEligibility $eligibility)
    {
        $request->validate([
            'review_status' => 'required|in:approved,rejected,pending',
            'notes' => 'nullable|string',
        ]);

        $eligibility->update([
            'review_status' => $request->review_status,
            'review_message' => $request->notes,
        ]);

        return $this->resourceResponse(
            new PlayerEligibilityResource($eligibility),
            'Eligibility updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/eligibility/{eligibility}",
     *     summary="Delete an eligibility record",
     *     tags={"🐧 Manager: Eligibilities"},
     *     @OA\Parameter(
     *         name="eligibility",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Eligibility deleted successfully",
     *         @OA\JsonContent(ref="#/components/schemas/ApiResponse")
     *     )
     * )
     */
    public function destroy(PlayerEligibility $eligibility)
    {
        $eligibility->delete();

        return $this->successResponse(null, 'Eligibility deleted successfully');
    }
}
