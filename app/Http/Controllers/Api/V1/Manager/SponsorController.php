<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Resources\SponsorResource;
use App\Models\Sponsor;
use App\Traits\CachesManagerResourceReads;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Sponsors",
 *     description="(v1) Sponsor catalog (optional club, many players)"
 * )
 */
class SponsorController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    use CachesManagerResourceReads;

    /**
     * @OA\Get(
     *     path="/api/v1/manager/sponsors",
     *     summary="Get paginated list of sponsors",
     *     tags={"🐧 Manager: Sponsors"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function index(Request $request)
    {
        $payload = $this->cachedManagerIndex('sponsors', $request, [], function () use ($request) {
            $query = Sponsor::with(['club', 'players']);

            $this->applyAdvancedPagination(
                $query,
                $request,
                Sponsor::$searchableFields,
                Sponsor::$filterableFields,
                Sponsor::$sortableFields
            );

            $paginationParams = $this->getPaginationParams($request);

            if (! $request->has('sort_by')) {
                $query->orderBy('id_sponsor', 'desc');
            }

            $sponsors = $query->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);

            return SponsorResource::collection($sponsors)->response()->getData(true);
        });

        return response()->json($payload);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/sponsors/{sponsor}",
     *     summary="Get sponsor details",
     *     tags={"🐧 Manager: Sponsors"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="sponsor", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function show(Sponsor $sponsor)
    {
        $data = $this->cachedManagerShow('sponsors', (int) $sponsor->id_sponsor, function () use ($sponsor) {
            $sponsor->load(['club', 'players']);

            return (new SponsorResource($sponsor))->resolve();
        });

        return $this->successResponse($data, 'Sponsor details retrieved successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/sponsors",
     *     summary="Create a sponsor",
     *     tags={"🐧 Manager: Sponsors"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", maxLength=60),
     *             @OA\Property(property="club_id", type="integer", nullable=true),
     *             @OA\Property(property="player_ids", type="array", @OA\Items(type="integer"))
     *         )
     *     ),
     *     @OA\Response(response=201, description="Created")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->sponsorValidation($request, null, true));
        $playerIds = $validated['player_ids'] ?? [];
        unset($validated['player_ids']);

        $sponsor = Sponsor::create($validated);
        $sponsor->syncPlayerIds($playerIds);
        $sponsor->load(['club', 'players']);

        return $this->resourceResponse(
            new SponsorResource($sponsor),
            'Sponsor created successfully',
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/manager/sponsors/{sponsor}",
     *     summary="Update a sponsor",
     *     tags={"🐧 Manager: Sponsors"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="sponsor", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Updated")
     * )
     */
    public function update(Request $request, Sponsor $sponsor)
    {
        $validated = $request->validate($this->sponsorValidation($request, $sponsor, true));
        $playerIds = $validated['player_ids'] ?? [];
        unset($validated['player_ids']);

        $sponsor->update($validated);
        $sponsor->syncPlayerIds($playerIds);
        $sponsor->load(['club', 'players']);

        return $this->resourceResponse(
            new SponsorResource($sponsor),
            'Sponsor updated successfully'
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/manager/sponsors/{sponsor}",
     *     summary="Partially update a sponsor",
     *     tags={"🐧 Manager: Sponsors"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="sponsor", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Updated")
     * )
     */
    public function patch(Request $request, Sponsor $sponsor)
    {
        $validated = $request->validate($this->sponsorValidation($request, $sponsor, false));
        $playerIds = null;
        if (array_key_exists('player_ids', $validated)) {
            $playerIds = $validated['player_ids'] ?? [];
            unset($validated['player_ids']);
        }

        $sponsor->update($validated);
        if ($playerIds !== null) {
            $sponsor->syncPlayerIds($playerIds);
        }
        $sponsor->load(['club', 'players']);

        return $this->resourceResponse(
            new SponsorResource($sponsor),
            'Sponsor partially updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/sponsors/{sponsor}",
     *     summary="Delete a sponsor",
     *     tags={"🐧 Manager: Sponsors"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="sponsor", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted"),
     *     @OA\Response(response=409, description="Linked data")
     * )
     */
    public function destroy(Sponsor $sponsor)
    {
        return $this->deleteResourceWithResponse(
            $sponsor,
            [
                'lotteryPrizes' => 'lottery_prizes_count',
                'sponsorPrizes' => 'sponsor_prizes_count',
            ],
            'sponsor',
            'Sponsor deleted successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/sponsors/bulk",
     *     summary="Bulk delete sponsors",
     *     tags={"🐧 Manager: Sponsors"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Deleted")
     * )
     */
    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'sponsor_ids' => 'required|array|min:1',
            'sponsor_ids.*' => 'integer',
        ]);

        return $this->deleteBulkResourcesWithResponse(
            $request->sponsor_ids,
            Sponsor::class,
            [
                'lotteryPrizes' => 'lottery_prizes_count',
                'sponsorPrizes' => 'sponsor_prizes_count',
            ],
            'sponsor',
            'Sponsors deleted successfully'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function sponsorValidation(Request $request, ?Sponsor $sponsor, bool $requireCore): array
    {
        $required = $requireCore ? 'required' : 'sometimes';
        $uniqueName = Rule::unique('sponsors', 'name');
        $uniqueClub = Rule::unique('sponsors', 'club_id');
        if ($sponsor !== null) {
            $uniqueName = $uniqueName->ignore($sponsor->id_sponsor, 'id_sponsor');
            $uniqueClub = $uniqueClub->ignore($sponsor->id_sponsor, 'id_sponsor');
        }

        return [
            'name' => [$required, 'string', 'max:60', $uniqueName],
            'club_id' => ['nullable', 'integer', 'exists:clubs,id_club', $uniqueClub],
            'player_ids' => ($requireCore ? 'nullable' : 'sometimes') . '|array',
            'player_ids.*' => 'integer|exists:players,id_player',
        ];
    }
}
