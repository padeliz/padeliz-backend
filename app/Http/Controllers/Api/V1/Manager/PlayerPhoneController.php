<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Http\Resources\UserPhoneResource;
use App\Models\UserPhone;
use App\Support\PlayerPhoneNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Player Phones",
 *     description="(v1) Player phone numbers management"
 * )
 */
class PlayerPhoneController extends V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/manager/user-phones",
     *     summary="Get paginated list of player phones",
     *     tags={"🐧 Manager: Player Phones"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(ref="#/components/parameters/page"),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", default=15, maximum=100)),
     *     @OA\Parameter(name="q", in="query", description="Search term for phone", required=false, @OA\Schema(type="string")),
     *     @OA\Parameter(name="filters[user_id]", in="query", description="Filter by player ID", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="filters[is_primary]", in="query", description="Filter by primary flag (0 or 1)", required=false, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiPaginatedResponse"),
     *                 @OA\Schema(@OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/UserPhone")))
     *             }
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=403, description="Access denied. Required user type: manager", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function index(Request $request)
    {
        $query = UserPhone::query();

        $this->applyAdvancedPagination(
            $query,
            $request,
            UserPhone::$searchableFields,
            UserPhone::$filterableFields,
            UserPhone::$sortableFields
        );

        $paginationParams = $this->getPaginationParams($request);

        if (! $request->has('sort_by')) {
            $query->orderBy('id_user_phone', 'desc');
        }

        $phones = $query->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);

        return UserPhoneResource::collection($phones);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/user-phones/{playerPhone}",
     *     summary="Get player phone details",
     *     tags={"🐧 Manager: Player Phones"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="playerPhone", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(@OA\Property(property="data", ref="#/components/schemas/UserPhone"))
     *             }
     *         )
     *     ),
     *     @OA\Response(response=404, description="Phone not found", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function show(UserPhone $playerPhone): JsonResponse
    {
        return $this->resourceResponse(
            new UserPhoneResource($playerPhone),
            'Player phone retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/user-phones",
     *     summary="Create player phone",
     *     tags={"🐧 Manager: Player Phones"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"user_id", "phone", "purposes"},
     *             @OA\Property(property="user_id", type="integer"),
     *             @OA\Property(property="phone", type="string", maxLength=30, description="Normalized to +digits"),
     *             @OA\Property(property="purposes", type="array", @OA\Items(type="string", enum={"contact", "login"})),
     *             @OA\Property(property="is_primary", type="boolean", default=false),
     *             @OA\Property(property="verified_at", type="integer", format="timestamp", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Player phone created successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(@OA\Property(property="data", ref="#/components/schemas/UserPhone"))
     *             }
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $this->normalizePhoneInput($request);

        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id_user',
            'player_id' => 'prohibited',
            'player_user_id' => 'prohibited',
            'phone' => 'required|string|max:30|unique:user_phones,phone',
            'purposes' => 'required|array|min:1',
            'purposes.*' => 'string|distinct|in:contact,login',
            'is_primary' => 'sometimes|boolean',
            'verified_at' => 'sometimes|nullable|integer|min:0',
        ]);

        $validated['is_primary'] = $validated['is_primary'] ?? false;

        $phone = DB::transaction(function () use ($validated): UserPhone {
            $phone = UserPhone::create($validated);
            $this->demoteOtherPrimaries($phone);

            return $phone;
        });

        return $this->resourceResponse(
            new UserPhoneResource($phone->refresh()),
            'Player phone created successfully',
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/manager/user-phones/{playerPhone}",
     *     summary="Update player phone",
     *     tags={"🐧 Manager: Player Phones"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="playerPhone", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="user_id", type="integer"),
     *             @OA\Property(property="phone", type="string", maxLength=30),
     *             @OA\Property(property="purposes", type="array", @OA\Items(type="string", enum={"contact", "login"})),
     *             @OA\Property(property="is_primary", type="boolean"),
     *             @OA\Property(property="verified_at", type="integer", format="timestamp", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Player phone updated successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(@OA\Property(property="data", ref="#/components/schemas/UserPhone"))
     *             }
     *         )
     *     ),
     *     @OA\Response(response=404, description="Phone not found", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function update(Request $request, UserPhone $playerPhone): JsonResponse
    {
        $this->normalizePhoneInput($request);

        $validated = $request->validate([
            'user_id' => 'sometimes|required|integer|exists:users,id_user',
            'phone' => [
                'sometimes',
                'required',
                'string',
                'max:30',
                Rule::unique('user_phones', 'phone')->ignore($playerPhone->id_user_phone, 'id_user_phone'),
            ],
            'purposes' => 'sometimes|required|array|min:1',
            'purposes.*' => 'string|distinct|in:contact,login',
            'is_primary' => 'sometimes|boolean',
            'verified_at' => 'sometimes|nullable|integer|min:0',
        ]);

        DB::transaction(function () use ($playerPhone, $validated): void {
            $playerPhone->update($validated);
            $this->demoteOtherPrimaries($playerPhone);
        });

        return $this->resourceResponse(
            new UserPhoneResource($playerPhone->refresh()),
            'Player phone updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/user-phones/{playerPhone}",
     *     summary="Delete player phone",
     *     tags={"🐧 Manager: Player Phones"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="playerPhone", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Player phone deleted successfully", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
     *     @OA\Response(response=404, description="Phone not found", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function destroy(UserPhone $playerPhone): JsonResponse
    {
        return $this->deleteResourceWithResponse(
            $playerPhone,
            ['identities' => 'identities_count'],
            'player phone',
            'Player phone deleted successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/user-phones/bulk",
     *     summary="Bulk delete player phones",
     *     tags={"🐧 Manager: Player Phones"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"player_phone_ids"},
     *             @OA\Property(property="player_phone_ids", type="array", @OA\Items(type="integer"))
     *         )
     *     ),
     *     @OA\Response(response=200, description="Player phones deleted successfully", @OA\JsonContent(ref="#/components/schemas/ApiResponse")),
     *     @OA\Response(response=207, description="Some phones failed to delete", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $request->validate([
            'player_phone_ids' => 'required|array|min:1',
            'player_phone_ids.*' => 'integer',
        ]);

        return $this->deleteBulkResourcesWithResponse(
            $request->player_phone_ids,
            UserPhone::class,
            ['identities' => 'identities_count'],
            'player phone',
            'Player phones deleted successfully'
        );
    }

    /**
     * Store phones in the same canonical `+digits` form the imports and App use.
     */
    private function normalizePhoneInput(Request $request): void
    {
        $phone = $request->input('phone');

        if (is_string($phone)) {
            $request->merge(['phone' => PlayerPhoneNormalizer::normalize($phone)]);
        }
    }

    /**
     * A player has at most one primary phone: the one saved last as primary wins.
     */
    private function demoteOtherPrimaries(UserPhone $phone): void
    {
        if (! $phone->is_primary) {
            return;
        }

        UserPhone::query()
            ->where('user_id', $phone->user_id)
            ->where('id_user_phone', '!=', $phone->id_user_phone)
            ->where('is_primary', true)
            ->update(['is_primary' => false]);
    }
}
