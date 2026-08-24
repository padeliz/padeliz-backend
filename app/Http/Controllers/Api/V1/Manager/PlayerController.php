<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Http\Resources\PlayerResource;
use App\Models\Player;
use App\Models\PlayerPhone;
use App\Services\Pdf\PdfCachedImageGenerator;
use App\Traits\CachesManagerResourceReads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Players",
 *     description="(v1) Player management and operations"
 * )
 */
class PlayerController extends V1BaseController
{
    use CachesManagerResourceReads;
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
     *         description="Search term for first_name, last_name, playtomic_id, rankedin_id, email, and phone",
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
     *             @OA\Property(property="skill_division", type="string", description="Filter by skill division"),
     *             @OA\Property(property="year_of_birth", type="string", description="Filter by year of birth"),
     *             @OA\Property(property="being_student", type="string", description="Filter by student status (use 0 or 1)"),
     *             @OA\Property(property="user_types", type="string", description="Filter by user types")
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
     *             @OA\Property(property="id_player", type="string", enum={"asc", "desc"}, description="Sort by player ID"),
     *             @OA\Property(property="skill_division", type="string", enum={"asc", "desc"}, description="Sort by skill division"),
     *             @OA\Property(property="year_of_birth", type="string", enum={"asc", "desc"}, description="Sort by year of birth"),
     *             @OA\Property(property="being_student", type="string", enum={"asc", "desc"}, description="Sort by student status"),
     *             @OA\Property(property="inserted_at", type="string", enum={"asc", "desc"}, description="Sort by creation date"),
     *             @OA\Property(property="updated_at", type="string", enum={"asc", "desc"}, description="Sort by update date")
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
        $payload = $this->cachedManagerIndex('players', $request, [], function () use ($request) {
            $query = Player::with(['participations']);

            $this->applyAdvancedPagination(
                $query,
                $request,
                Player::$searchableFields,
                Player::$filterableFields,
                Player::$sortableFields,
                function ($q, string $searchTerm): void {
                    $q->orWhereHas('emails', function ($emailQuery) use ($searchTerm) {
                        $emailQuery->where('email', 'LIKE', "%{$searchTerm}%");
                    });
                    $q->orWhereHas('phones', function ($phoneQuery) use ($searchTerm) {
                        $phoneQuery->where('phone', 'LIKE', "%{$searchTerm}%");
                    });
                }
            );

            $paginationParams = $this->getPaginationParams($request);

            if (! $request->has('sort_by')) {
                $query->orderBy('id_player', 'desc');
            }

            $players = $query->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);

            return PlayerResource::collection($players)->response()->getData(true);
        });

        return response()->json($payload);
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
        $data = $this->cachedManagerShow('players', (int) $player->id_player, function () use ($player) {
            $player->load(['participations']);

            return (new PlayerResource($player))->resolve();
        });

        return $this->successResponse($data, 'Player details retrieved successfully');
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
     *             required={"first_name"},
     *             @OA\Property(property="first_name", type="string", maxLength=50),
     *             @OA\Property(property="middle_name", type="string", maxLength=50, nullable=true),
     *             @OA\Property(property="last_name", type="string", maxLength=50, nullable=true),
     *             @OA\Property(property="phone", type="string", description="Optional primary phone contact"),
     *             @OA\Property(property="skill_division", type="number"),
     *             @OA\Property(property="user_types", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Player created successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/Player")
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
            'first_name' => 'required|string|max:50',
            'middle_name' => 'nullable|string|max:50',
            'last_name' => 'nullable|string|max:50',
            'phone' => ['nullable', 'string', 'max:30', Rule::unique('player_phones', 'phone')],
            'skill_division' => 'nullable|numeric|min:0|max:9.9',
            'user_types' => 'nullable|array',
            'user_types.*' => 'string|in:player,manager,admin',
        ]);

        $phone = $validated['phone'] ?? null;
        unset($validated['phone']);
        $validated['created_via'] = 'manager';
        $validated['created_via_format'] = null;
        $validated['created_via_import_id'] = null;

        $player = Player::create($validated);

        if ($phone) {
            PlayerPhone::create([
                'player_id' => $player->id_player,
                'phone' => $phone,
                'purposes' => ['contact'],
                'is_primary' => true,
            ]);
        }

        $player->load(['emails', 'phones']);

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
     *             @OA\Property(property="first_name", type="string", maxLength=50, description="Player first name"),
     *             @OA\Property(property="middle_name", type="string", maxLength=50, nullable=true, description="Player middle name"),
     *             @OA\Property(property="last_name", type="string", maxLength=50, nullable=true, description="Player last name"),
     *             @OA\Property(property="phone", type="string", maxLength=30, description="Optional primary phone contact"),
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
     *         description="Player not found",
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
    public function update(Request $request, Player $player)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:50',
            'middle_name' => 'nullable|string|max:50',
            'last_name' => 'nullable|string|max:50',
            'phone' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('player_phones', 'phone')->where(fn ($q) => $q->where('player_id', '!=', $player->id_player)),
            ],
            'skill_division' => 'nullable|numeric|min:0|max:9.9',
            'year_of_birth' => 'nullable|integer|min:1900|max:9999',
            'being_student' => 'boolean',
            'user_types' => 'nullable|array',
            'user_types.*' => 'string|in:player,manager,admin',
        ]);

        $phone = $validated['phone'] ?? null;
        unset($validated['phone']);

        $player->update($validated);
        $this->syncPrimaryPhone($player, $phone);
        $player->load(['emails', 'phones']);

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
     *             @OA\Property(property="first_name", type="string", maxLength=50, description="Player first name"),
     *             @OA\Property(property="middle_name", type="string", maxLength=50, nullable=true, description="Player middle name"),
     *             @OA\Property(property="last_name", type="string", maxLength=50, nullable=true, description="Player last name"),
     *             @OA\Property(property="phone", type="string", maxLength=30, description="Optional primary phone contact"),
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
     *         description="Player not found",
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
    public function patch(Request $request, Player $player)
    {
        $validated = $request->validate([
            'first_name' => 'sometimes|required|string|max:50',
            'middle_name' => 'nullable|string|max:50',
            'last_name' => 'nullable|string|max:50',
            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:30',
                Rule::unique('player_phones', 'phone')->where(fn ($q) => $q->where('player_id', '!=', $player->id_player)),
            ],
            'skill_division' => 'nullable|numeric|min:0|max:9.9',
            'user_types' => 'nullable|array',
            'user_types.*' => 'string|in:player,manager,admin',
        ]);

        $phone = array_key_exists('phone', $validated) ? $validated['phone'] : null;
        $hasPhone = array_key_exists('phone', $validated);
        unset($validated['phone']);

        $player->update($validated);

        if ($hasPhone) {
            $this->syncPrimaryPhone($player, $phone);
        }

        $player->load(['emails', 'phones']);

        return $this->resourceResponse(
            new PlayerResource($player),
            'Player partially updated successfully'
        );
    }

    private function syncPrimaryPhone(Player $player, ?string $phone): void
    {
        if ($phone === null || $phone === '') {
            return;
        }

        $playerPhone = $player->phones()->where('is_primary', true)->first()
            ?? $player->phones()->first();

        if ($playerPhone) {
            $playerPhone->update(['phone' => $phone, 'is_primary' => true]);

            return;
        }

        PlayerPhone::create([
            'player_id' => $player->id_player,
            'phone' => $phone,
            'purposes' => ['contact'],
            'is_primary' => true,
        ]);
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
     *         description="Player deleted successfully",
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
     *         description="Player not found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Player cannot be deleted due to linked data",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiErrorResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="linked_data",
     *                         type="object",
     *                         description="Information about linked data preventing deletion",
     *                         @OA\Property(property="participations_count", type="integer", example=5)
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
    public function destroy(Player $player)
    {
        return $this->deleteResourceWithResponse(
            $player,
            ['sponsors' => 'sponsors_count'],
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
     *         description="All players failed to delete due to linked data",
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
            'player_ids' => 'required|array|min:1',
            'player_ids.*' => 'integer',
        ]);

        return $this->deleteBulkResourcesWithResponse(
            $request->player_ids,
            Player::class,
            ['sponsors' => 'sponsors_count'],
            'player',
            'Players deleted successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/players/{player}/pdf-avatar",
     *     summary="Generate or refresh cached PDF circular avatar for a player",
     *     description="Players without a usable profile photo get an initials placeholder avatar instead of an error.",
     *     tags={"🐧 Manager: Players"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="player", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"is_collecting_points"},
     *             @OA\Property(property="is_collecting_points", type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=200, description="PDF avatar URL"),
     *     @OA\Response(response=422, description="Validation error or avatar generation failure")
     * )
     */
    public function generatePdfAvatar(Request $request, Player $player): JsonResponse
    {
        $validated = $request->validate([
            'is_collecting_points' => ['required', 'boolean'],
        ]);

        $collecting = (bool) $validated['is_collecting_points'];

        try {
            $url = (new PdfCachedImageGenerator())->generatePlayerAvatarUrl($player, $collecting);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Failed to generate PDF avatar.',
                'player_ids' => [(int) $player->id_player],
                'code' => 'pdf_avatar_failed',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'pdf_avatar_url' => $url,
            ],
        ]);
    }
}
