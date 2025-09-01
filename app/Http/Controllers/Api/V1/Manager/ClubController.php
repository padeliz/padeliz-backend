<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Resources\ClubResource;
use App\Http\Resources\LocationResource;
use App\Http\Resources\PrizeFromClubResource;
use App\Models\Club;
use App\Models\Location;
use App\Models\PrizeFromClub;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Clubs",
 *     description="(v1) Club management and operations"
 * )
 */
class ClubController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/manager/clubs",
     *     summary="Get paginated list of clubs",
     *     tags={"🐧 Manager: Clubs"},
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
     *         description="Search term for name and city fields",
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
     *             @OA\Property(property="city", type="string")
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort parameters",
     *         required=false,
     *         @OA\Schema(
     *             type="object",
     *             @OA\Property(property="name", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="city", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="inserted_at", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="updated_at", type="string", enum={"asc", "desc"})
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
     *                         @OA\Items(ref="#/components/schemas/Club")
     *                     )
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = Club::with('locations');

        // Apply advanced pagination
        $this->applyAdvancedPagination(
            $query,
            $request,
            Club::$searchableFields,
            Club::$filterableFields,
            Club::$sortableFields
        );

        // Get pagination parameters
        $paginationParams = $this->getPaginationParams($request);

        // Apply default sorting if no sorting is specified
        if (! $request->has('sort_by')) {
            $query->orderBy('id_club', 'desc');
        }

        $clubs = $query->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);

        return ClubResource::collection($clubs);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/clubs/{club}",
     *     summary="Get club details",
     *     tags={"🐧 Manager: Clubs"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="club",
     *         in="path",
     *         required=true,
     *         description="Club ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/Club")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Club not found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function show(Club $club)
    {
        $club->load('locations');

        return $this->resourceResponse(
            new ClubResource($club),
            'Club details retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/clubs",
     *     summary="Create a new club",
     *     tags={"🐧 Manager: Clubs"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "city"},
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="city", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Club created successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/Club")
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
        $request->validate([
            'name' => 'required|string|max:255',
            'city' => 'required|string|max:255',
        ]);

        $club = Club::create($request->all());

        return $this->resourceResponse(
            new ClubResource($club),
            'Club created successfully',
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/manager/clubs/{club}",
     *     summary="Update a club",
     *     tags={"🐧 Manager: Clubs"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="club",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Club updated successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/Club")
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
    public function update(Request $request, Club $club)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'city' => 'sometimes|required|string|max:255',
        ]);

        $club->update($request->all());

        return $this->resourceResponse(
            new ClubResource($club),
            'Club updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/clubs/{club}",
     *     summary="Delete a club",
     *     tags={"🐧 Manager: Clubs"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="club",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Club deleted successfully",
     *         @OA\JsonContent(ref="#/components/schemas/ApiResponse")
     *     )
     * )
     */
    public function destroy(Club $club)
    {
        $club->delete();

        return $this->successResponse(
            null,
            'Club deleted successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/clubs/{club}/locations",
     *     summary="Get club locations",
     *     tags={"🐧 Manager: Clubs"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="club",
     *         in="path",
     *         required=true,
     *         description="Club ID",
     *         @OA\Schema(type="integer")
     *     ),
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
     *         description="Search term for name and address fields",
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
     *             @OA\Property(property="total_indoors_courts", type="integer"),
     *             @OA\Property(property="total_outdoor_courts", type="integer")
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort parameters",
     *         required=false,
     *         @OA\Schema(
     *             type="object",
     *             @OA\Property(property="name", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="address", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="total_indoors_courts", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="total_outdoor_courts", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="inserted_at", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="updated_at", type="string", enum={"asc", "desc"})
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
     *                         @OA\Items(ref="#/components/schemas/Location")
     *                     )
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function locations(Request $request, Club $club)
    {
        $query = $club->locations()->getQuery();

        // Apply advanced pagination
        $this->applyAdvancedPagination(
            $query,
            $request,
            Location::$searchableFields,
            Location::$filterableFields,
            Location::$sortableFields
        );

        // Get pagination parameters
        $paginationParams = $this->getPaginationParams($request);

        // Apply default sorting if no sorting is specified
        if (! $request->has('sort_by')) {
            $query->orderBy('id_location', 'desc');
        }

        $locations = $query->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);

        return LocationResource::collection($locations);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/clubs/{club}/prizes",
     *     summary="Get paginated list of club prizes",
     *     tags={"🐧 Manager: Clubs"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="club",
     *         in="path",
     *         required=true,
     *         description="Club ID",
     *         @OA\Schema(type="integer")
     *     ),
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
     *         description="Search term for box_1_text, box_2_text fields",
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
     *             @OA\Property(property="prize_id", type="integer"),
     *             @OA\Property(property="box_1_bg_color", type="string"),
     *             @OA\Property(property="box_1_text_color", type="string"),
     *             @OA\Property(property="box_2_bg_color", type="string"),
     *             @OA\Property(property="box_2_text_color", type="string")
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort parameters",
     *         required=false,
     *         @OA\Schema(
     *             type="object",
     *             @OA\Property(property="prize_id", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="inserted_at", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="updated_at", type="string", enum={"asc", "desc"})
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
     *                         @OA\Items(ref="#/components/schemas/PrizeFromClub")
     *                     )
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function prizes(Request $request, Club $club)
    {
        $query = $club->prizes()->with('prize')->getQuery();

        // Apply advanced pagination
        $this->applyAdvancedPagination(
            $query,
            $request,
            PrizeFromClub::$searchableFields,
            PrizeFromClub::$filterableFields,
            PrizeFromClub::$sortableFields
        );

        // Get pagination parameters
        $paginationParams = $this->getPaginationParams($request);

        // Apply default sorting if no sorting is specified
        if (! $request->has('sort_by')) {
            $query->orderBy('id_club_prize', 'desc');
        }

        $prizes = $query->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);

        return PrizeFromClubResource::collection($prizes);
    }
}
