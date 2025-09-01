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
     *             @OA\Property(property="being_student", type="boolean"),
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
            'name' => 'required|string|max:255',
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
     *     summary="Update a player",
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
     *         description="Player updated successfully"
     *     )
     * )
     */
    public function update(Request $request, Player $player)
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
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
            'Player updated successfully'
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
        $player->delete();

        return $this->successResponse(null, 'Player deleted successfully');
    }
}
