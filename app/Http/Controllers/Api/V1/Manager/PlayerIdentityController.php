<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Http\Resources\PlayerIdentityManagerResource;
use App\Models\PlayerIdentity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Identities are created by OAuth / SMS sign-in flows (players link them from the App),
 * so there is intentionally no `store` endpoint here.
 *
 * @OA\Tag(
 *     name="🐧 Manager: Player Identities",
 *     description="(v1) Linked sign-in identities (list / view / edit / delete; no create)"
 * )
 */
class PlayerIdentityController extends V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/manager/player-identities",
     *     summary="Get paginated list of player identities",
     *     tags={"🐧 Manager: Player Identities"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(ref="#/components/parameters/page"),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", default=15, maximum=100)),
     *     @OA\Parameter(name="q", in="query", description="Search term for display_name, provider_subject", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="filters[player_id]", in="query", description="Filter by player ID", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="filters[provider]", in="query", description="Filter by provider", required=false, @OA\Schema(type="string", enum={"google", "apple", "sms", "dev"})),
     *     @OA\Parameter(name="filters[is_avatar_primary]", in="query", description="Filter by avatar-primary flag (0 or 1)", required=false, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiPaginatedResponse"),
     *                 @OA\Schema(@OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/PlayerIdentity")))
     *             }
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=403, description="Access denied. Required user type: manager", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function index(Request $request)
    {
        $query = PlayerIdentity::query();

        $this->applyAdvancedPagination(
            $query,
            $request,
            PlayerIdentity::$searchableFields,
            PlayerIdentity::$filterableFields,
            PlayerIdentity::$sortableFields
        );

        $paginationParams = $this->getPaginationParams($request);

        if (! $request->has('sort_by')) {
            $query->orderBy('id_identity', 'desc');
        }

        $identities = $query->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);

        return PlayerIdentityManagerResource::collection($identities);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/player-identities/{playerIdentity}",
     *     summary="Get player identity details",
     *     tags={"🐧 Manager: Player Identities"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="playerIdentity", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(@OA\Property(property="data", ref="#/components/schemas/PlayerIdentity"))
     *             }
     *         )
     *     ),
     *     @OA\Response(response=404, description="Identity not found", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function show(PlayerIdentity $playerIdentity): JsonResponse
    {
        return $this->resourceResponse(
            new PlayerIdentityManagerResource($playerIdentity),
            'Player identity retrieved successfully'
        );
    }

    /**
     * Only profile-level fields are editable; the provider link itself (player, provider, subject) is immutable.
     *
     * @OA\Put(
     *     path="/api/v1/manager/player-identities/{playerIdentity}",
     *     summary="Update player identity",
     *     tags={"🐧 Manager: Player Identities"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="playerIdentity", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="email_id", type="integer", nullable=true, description="Must belong to the same player"),
     *             @OA\Property(property="phone_id", type="integer", nullable=true, description="Must belong to the same player"),
     *             @OA\Property(property="display_name", type="string", maxLength=255, nullable=true),
     *             @OA\Property(property="avatar_url", type="string", maxLength=512, nullable=true),
     *             @OA\Property(property="is_avatar_primary", type="boolean")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Player identity updated successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(@OA\Property(property="data", ref="#/components/schemas/PlayerIdentity"))
     *             }
     *         )
     *     ),
     *     @OA\Response(response=404, description="Identity not found", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function update(Request $request, PlayerIdentity $playerIdentity): JsonResponse
    {
        $validated = $request->validate([
            'email_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('player_emails', 'id_player_email')->where('player_id', $playerIdentity->player_id),
            ],
            'phone_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('player_phones', 'id_player_phone')->where('player_id', $playerIdentity->player_id),
            ],
            'display_name' => 'sometimes|nullable|string|max:255',
            'avatar_url' => 'sometimes|nullable|string|max:512',
            'is_avatar_primary' => 'sometimes|boolean',
        ]);

        DB::transaction(function () use ($playerIdentity, $validated): void {
            $playerIdentity->update($validated);

            // A player has at most one avatar-primary identity: the one saved last as primary wins.
            if ($playerIdentity->is_avatar_primary) {
                PlayerIdentity::query()
                    ->where('player_id', $playerIdentity->player_id)
                    ->where('id_identity', '!=', $playerIdentity->id_identity)
                    ->where('is_avatar_primary', true)
                    ->update(['is_avatar_primary' => false]);
            }
        });

        return $this->resourceResponse(
            new PlayerIdentityManagerResource($playerIdentity->refresh()),
            'Player identity updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/player-identities/{playerIdentity}",
     *     summary="Delete player identity",
     *     tags={"🐧 Manager: Player Identities"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="playerIdentity", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Player identity deleted successfully", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
     *     @OA\Response(response=404, description="Identity not found", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function destroy(PlayerIdentity $playerIdentity): JsonResponse
    {
        return $this->deleteResourceWithResponse(
            $playerIdentity,
            [],
            'player identity',
            'Player identity deleted successfully'
        );
    }
}
