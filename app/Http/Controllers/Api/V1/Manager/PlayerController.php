<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Http\Resources\PlayerResource;
use App\Models\Player;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Players",
 *     description="(v1) Player management and operations"
 * )
 */
class PlayerController extends V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/manager/players",
     *     summary="Get all players",
     *     tags={"🐧 Manager: Players"},
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
     *         description="Search term for name, first_name, last_name, phone, email, playtomic_id, rankedin_id fields",
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
     *             @OA\Property(property="skill_division", type="number"),
     *             @OA\Property(property="year_of_birth", type="integer"),
     *             @OA\Property(property="being_student", type="integer", enum={0, 1}, description="Being student (use 0 or 1, not true/false)"),
     *             @OA\Property(property="user_types", type="array", @OA\Items(type="string"))
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
     *             @OA\Property(property="first_name", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="last_name", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="skill_division", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="year_of_birth", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="being_student", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="inserted_at", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="updated_at", type="string", enum={"asc", "desc"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of all players",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiPaginatedResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="data",
     *                         type="array",
     *                         @OA\Items(ref="#/components/schemas/Player")
     *                     )
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = Player::with(['participations']);

        // Apply advanced pagination
        $this->applyAdvancedPagination(
            $query,
            $request,
            Player::$searchableFields,
            Player::$filterableFields,
            Player::$sortableFields
        );

        // Get pagination parameters
        $paginationParams = $this->getPaginationParams($request);

        // Apply default sorting if no sorting is specified
        if (! $request->has('sort_by')) {
            $query->orderBy('id_player', 'desc');
        }

        $players = $query->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);

        return PlayerResource::collection($players);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/players/{player}",
     *     summary="Get a specific player",
     *     tags={"🐧 Manager: Players"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="player",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Player details retrieved successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="data",
     *                         ref="#/components/schemas/Player"
     *                     )
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Player not found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function show(Player $player)
    {
        $player->load(['participations']);

        return $this->resourceResponse(
            new PlayerResource($player),
            'Player details retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/players",
     *     summary="Create a new player",
     *     tags={"🐧 Manager: Players"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "phone"},
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="phone", type="string"),
     *             @OA\Property(property="first_name", type="string"),
     *             @OA\Property(property="last_name", type="string"),
     *             @OA\Property(property="skill_division", type="number"),
     *             @OA\Property(property="user_types", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Player created successfully"
     *     )
     * )
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:250',
            'phone' => 'required|string|max:255',
            'first_name' => 'nullable|string|max:50',
            'last_name' => 'nullable|string|max:50',
            'skill_division' => 'nullable|numeric|min:0|max:9.9',
            'user_types' => 'nullable|array',
            'user_types.*' => 'string|in:player,manager,admin',
        ]);

        $player = Player::create($request->all());

        return $this->resourceResponse(
            new PlayerResource($player),
            'Player created successfully',
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/manager/players/{player}",
     *     summary="Update a player (complete replacement)",
     *     tags={"🐧 Manager: Players"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="player",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", maxLength=250, description="Player name"),
     *             @OA\Property(property="phone", type="string", maxLength=255, description="Player phone"),
     *             @OA\Property(property="first_name", type="string", maxLength=50, description="Player first name"),
     *             @OA\Property(property="last_name", type="string", maxLength=50, description="Player last name"),
     *             @OA\Property(property="skill_division", type="number", minimum=0, maximum=9.9, description="Player skill division"),
     *             @OA\Property(property="user_types", type="array", description="User types", @OA\Items(type="string", enum={"player", "manager", "admin"}))
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Player updated successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/Player")
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function update(Request $request, Player $player)
    {
        $request->validate([
            'name' => 'required|string|max:250',
            'phone' => 'required|string|max:255',
            'first_name' => 'nullable|string|max:50',
            'last_name' => 'nullable|string|max:50',
            'skill_division' => 'nullable|numeric|min:0|max:9.9',
            'user_types' => 'nullable|array',
            'user_types.*' => 'string|in:player,manager,admin',
        ]);

        $player->update($request->all());

        return $this->resourceResponse(
            new PlayerResource($player),
            'Player updated successfully'
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/manager/players/{player}",
     *     summary="Partially update a player",
     *     tags={"🐧 Manager: Players"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="player",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", maxLength=250, description="Player name"),
     *             @OA\Property(property="phone", type="string", maxLength=255, description="Player phone"),
     *             @OA\Property(property="first_name", type="string", maxLength=50, description="Player first name"),
     *             @OA\Property(property="last_name", type="string", maxLength=50, description="Player last name"),
     *             @OA\Property(property="skill_division", type="number", minimum=0, maximum=9.9, description="Player skill division"),
     *             @OA\Property(property="user_types", type="array", description="User types", @OA\Items(type="string", enum={"player", "manager", "admin"}))
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Player partially updated successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/Player")
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function patch(Request $request, Player $player)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:250',
            'phone' => 'sometimes|required|string|max:255',
            'first_name' => 'nullable|string|max:50',
            'last_name' => 'nullable|string|max:50',
            'skill_division' => 'nullable|numeric|min:0|max:9.9',
            'user_types' => 'nullable|array',
            'user_types.*' => 'string|in:player,manager,admin',
        ]);

        $player->update($request->all());

        return $this->resourceResponse(
            new PlayerResource($player),
            'Player partially updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/players/{player}",
     *     summary="Delete a player",
     *     tags={"🐧 Manager: Players"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="player",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Player deleted successfully"
     *     )
     * )
     */
    public function destroy(Player $player)
    {
        return $this->deleteResourceWithResponse(
            $player,
            [],
            'player',
            'Player deleted successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/players/bulk",
     *     summary="Bulk delete players",
     *     tags={"🐧 Manager: Players"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"player_ids"},
     *             @OA\Property(
     *                 property="player_ids",
     *                 type="array",
     *                 description="Array of player IDs to delete",
     *                 @OA\Items(type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="All players deleted successfully",
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
     *         description="Some players failed to delete (Multi-Status)",
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
     *         description="No players provided for deletion",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'player_ids' => 'required|array|min:1',
            'player_ids.*' => 'integer',
        ]);

        return $this->deleteBulkResourcesWithResponse(
            $request->player_ids,
            Player::class,
            [],
            'player',
            'Players deleted successfully'
        );
    }
}
