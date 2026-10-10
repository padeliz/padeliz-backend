<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Http\Resources\PlayerEmailResource;
use App\Models\PlayerEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Player Emails",
 *     description="(v1) Player email addresses management"
 * )
 */
class PlayerEmailController extends V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/manager/player-emails",
     *     summary="Get paginated list of player emails",
     *     tags={"🐧 Manager: Player Emails"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(ref="#/components/parameters/page"),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", default=15, maximum=100)),
     *     @OA\Parameter(name="q", in="query", description="Search term for email", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="filters[player_id]", in="query", description="Filter by player ID", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="filters[is_primary]", in="query", description="Filter by primary flag (0 or 1)", required=false, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiPaginatedResponse"),
     *                 @OA\Schema(@OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/PlayerEmail")))
     *             }
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=403, description="Access denied. Required user type: manager", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function index(Request $request)
    {
        $query = PlayerEmail::query();

        $this->applyAdvancedPagination(
            $query,
            $request,
            PlayerEmail::$searchableFields,
            PlayerEmail::$filterableFields,
            PlayerEmail::$sortableFields
        );

        $paginationParams = $this->getPaginationParams($request);

        if (! $request->has('sort_by')) {
            $query->orderBy('id_player_email', 'desc');
        }

        $emails = $query->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);

        return PlayerEmailResource::collection($emails);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/player-emails/{playerEmail}",
     *     summary="Get player email details",
     *     tags={"🐧 Manager: Player Emails"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="playerEmail", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(@OA\Property(property="data", ref="#/components/schemas/PlayerEmail"))
     *             }
     *         )
     *     ),
     *     @OA\Response(response=404, description="Email not found", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function show(PlayerEmail $playerEmail): JsonResponse
    {
        return $this->resourceResponse(
            new PlayerEmailResource($playerEmail),
            'Player email retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/player-emails",
     *     summary="Create player email",
     *     tags={"🐧 Manager: Player Emails"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"player_id", "email", "purposes"},
     *             @OA\Property(property="player_id", type="integer"),
     *             @OA\Property(property="email", type="string", format="email", maxLength=255),
     *             @OA\Property(property="purposes", type="array", @OA\Items(type="string", enum={"contact", "login"})),
     *             @OA\Property(property="is_primary", type="boolean", default=false),
     *             @OA\Property(property="verified_at", type="integer", format="timestamp", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Player email created successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(@OA\Property(property="data", ref="#/components/schemas/PlayerEmail"))
     *             }
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'player_id' => 'required|integer|exists:players,id_player',
            'email' => 'required|string|email|max:255|unique:player_emails,email',
            'purposes' => 'required|array|min:1',
            'purposes.*' => 'string|distinct|in:contact,login',
            'is_primary' => 'sometimes|boolean',
            'verified_at' => 'sometimes|nullable|integer|min:0',
        ]);

        $validated['is_primary'] = $validated['is_primary'] ?? false;

        $email = DB::transaction(function () use ($validated): PlayerEmail {
            $email = PlayerEmail::create($validated);
            $this->demoteOtherPrimaries($email);

            return $email;
        });

        return $this->resourceResponse(
            new PlayerEmailResource($email->refresh()),
            'Player email created successfully',
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/manager/player-emails/{playerEmail}",
     *     summary="Update player email",
     *     tags={"🐧 Manager: Player Emails"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="playerEmail", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="player_id", type="integer"),
     *             @OA\Property(property="email", type="string", format="email", maxLength=255),
     *             @OA\Property(property="purposes", type="array", @OA\Items(type="string", enum={"contact", "login"})),
     *             @OA\Property(property="is_primary", type="boolean"),
     *             @OA\Property(property="verified_at", type="integer", format="timestamp", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Player email updated successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(@OA\Property(property="data", ref="#/components/schemas/PlayerEmail"))
     *             }
     *         )
     *     ),
     *     @OA\Response(response=404, description="Email not found", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function update(Request $request, PlayerEmail $playerEmail): JsonResponse
    {
        $validated = $request->validate([
            'player_id' => 'sometimes|required|integer|exists:players,id_player',
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('player_emails', 'email')->ignore($playerEmail->id_player_email, 'id_player_email'),
            ],
            'purposes' => 'sometimes|required|array|min:1',
            'purposes.*' => 'string|distinct|in:contact,login',
            'is_primary' => 'sometimes|boolean',
            'verified_at' => 'sometimes|nullable|integer|min:0',
        ]);

        DB::transaction(function () use ($playerEmail, $validated): void {
            $playerEmail->update($validated);
            $this->demoteOtherPrimaries($playerEmail);
        });

        return $this->resourceResponse(
            new PlayerEmailResource($playerEmail->refresh()),
            'Player email updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/player-emails/{playerEmail}",
     *     summary="Delete player email",
     *     tags={"🐧 Manager: Player Emails"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="playerEmail", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Player email deleted successfully", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
     *     @OA\Response(response=404, description="Email not found", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function destroy(PlayerEmail $playerEmail): JsonResponse
    {
        return $this->deleteResourceWithResponse(
            $playerEmail,
            ['identities' => 'identities_count'],
            'player email',
            'Player email deleted successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/player-emails/bulk",
     *     summary="Bulk delete player emails",
     *     tags={"🐧 Manager: Player Emails"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"player_email_ids"},
     *             @OA\Property(property="player_email_ids", type="array", @OA\Items(type="integer"))
     *         )
     *     ),
     *     @OA\Response(response=200, description="Player emails deleted successfully", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
     *     @OA\Response(response=207, description="Some emails failed to delete", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $request->validate([
            'player_email_ids' => 'required|array|min:1',
            'player_email_ids.*' => 'integer',
        ]);

        return $this->deleteBulkResourcesWithResponse(
            $request->player_email_ids,
            PlayerEmail::class,
            ['identities' => 'identities_count'],
            'player email',
            'Player emails deleted successfully'
        );
    }

    /**
     * A player has at most one primary email: the one saved last as primary wins.
     */
    private function demoteOtherPrimaries(PlayerEmail $email): void
    {
        if (! $email->is_primary) {
            return;
        }

        PlayerEmail::query()
            ->where('player_id', $email->player_id)
            ->where('id_player_email', '!=', $email->id_player_email)
            ->where('is_primary', true)
            ->update(['is_primary' => false]);
    }
}
