<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Resources\SponsorPrizeResource;
use App\Models\SponsorPrize;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Sponsor Prizes",
 *     description="(v1) Reusable LIVE overlays for a sponsor + catalog prize"
 * )
 */
class SponsorPrizeController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/manager/sponsor-prizes",
     *     summary="Get paginated list of sponsor prize overlays",
     *     tags={"🐧 Manager: Sponsor Prizes"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function index(Request $request)
    {
        $query = SponsorPrize::with(['sponsor', 'prize']);

        $this->applyAdvancedPagination(
            $query,
            $request,
            SponsorPrize::$searchableFields,
            SponsorPrize::$filterableFields,
            SponsorPrize::$sortableFields
        );

        $paginationParams = $this->getPaginationParams($request);

        if (! $request->has('sort_by')) {
            $query->orderBy('id_sponsor_prize', 'desc');
        }

        $rows = $query->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);

        return SponsorPrizeResource::collection($rows);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/sponsor-prizes/{sponsorPrize}",
     *     summary="Get sponsor prize overlay details",
     *     tags={"🐧 Manager: Sponsor Prizes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="sponsorPrize", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function show(SponsorPrize $sponsorPrize)
    {
        $sponsorPrize->load(['sponsor', 'prize']);

        return $this->resourceResponse(
            new SponsorPrizeResource($sponsorPrize),
            'Sponsor prize details retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/sponsor-prizes",
     *     summary="Create a sponsor prize overlay",
     *     tags={"🐧 Manager: Sponsor Prizes"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=201, description="Created")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->overlayValidation($request, null, true));

        $existing = SponsorPrize::query()
            ->where('sponsor_id', $validated['sponsor_id'])
            ->where('prize_id', $validated['prize_id'])
            ->first();

        if ($existing !== null) {
            return $this->errorResponse('This sponsor already has this prize overlay', 422);
        }

        $row = SponsorPrize::create($validated);
        $row->load(['sponsor', 'prize']);

        return $this->resourceResponse(
            new SponsorPrizeResource($row),
            'Sponsor prize created successfully',
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/manager/sponsor-prizes/{sponsorPrize}",
     *     summary="Update a sponsor prize overlay",
     *     tags={"🐧 Manager: Sponsor Prizes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="sponsorPrize", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Updated")
     * )
     */
    public function update(Request $request, SponsorPrize $sponsorPrize)
    {
        $validated = $request->validate($this->overlayValidation($request, $sponsorPrize, true));
        if ($response = $this->duplicateOverlayResponse($validated, $sponsorPrize)) {
            return $response;
        }

        $sponsorPrize->update($validated);
        $sponsorPrize->load(['sponsor', 'prize']);

        return $this->resourceResponse(
            new SponsorPrizeResource($sponsorPrize),
            'Sponsor prize updated successfully'
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/manager/sponsor-prizes/{sponsorPrize}",
     *     summary="Partially update a sponsor prize overlay",
     *     tags={"🐧 Manager: Sponsor Prizes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="sponsorPrize", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Updated")
     * )
     */
    public function patch(Request $request, SponsorPrize $sponsorPrize)
    {
        $validated = $request->validate($this->overlayValidation($request, $sponsorPrize, false));
        if ($response = $this->duplicateOverlayResponse($validated, $sponsorPrize)) {
            return $response;
        }

        $sponsorPrize->update($validated);
        $sponsorPrize->load(['sponsor', 'prize']);

        return $this->resourceResponse(
            new SponsorPrizeResource($sponsorPrize),
            'Sponsor prize partially updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/sponsor-prizes/{sponsorPrize}",
     *     summary="Delete a sponsor prize overlay",
     *     tags={"🐧 Manager: Sponsor Prizes"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="sponsorPrize", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted")
     * )
     */
    public function destroy(SponsorPrize $sponsorPrize)
    {
        return $this->deleteResourceWithResponse(
            $sponsorPrize,
            [],
            'sponsor prize',
            'Sponsor prize deleted successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/sponsor-prizes/bulk",
     *     summary="Bulk delete sponsor prize overlays",
     *     tags={"🐧 Manager: Sponsor Prizes"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Deleted")
     * )
     */
    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'sponsor_prize_ids' => 'required|array|min:1',
            'sponsor_prize_ids.*' => 'integer',
        ]);

        return $this->deleteBulkResourcesWithResponse(
            $request->sponsor_prize_ids,
            SponsorPrize::class,
            [],
            'sponsor prize',
            'Sponsor prizes deleted successfully'
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function duplicateOverlayResponse(array $validated, SponsorPrize $sponsorPrize): mixed
    {
        $sponsorId = $validated['sponsor_id'] ?? $sponsorPrize->sponsor_id;
        $prizeId = $validated['prize_id'] ?? $sponsorPrize->prize_id;

        $existing = SponsorPrize::query()
            ->where('sponsor_id', $sponsorId)
            ->where('prize_id', $prizeId)
            ->where('id_sponsor_prize', '!=', $sponsorPrize->id_sponsor_prize)
            ->first();

        if ($existing !== null) {
            return $this->errorResponse('This sponsor already has this prize overlay', 422);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function overlayValidation(Request $request, ?SponsorPrize $sponsorPrize, bool $requireCore): array
    {
        $required = $requireCore ? 'required' : 'sometimes';
        $uniquePair = Rule::unique('sponsor_prizes', 'prize_id')->where(
            fn ($q) => $q->where('sponsor_id', $request->input('sponsor_id', $sponsorPrize?->sponsor_id))
        );
        if ($sponsorPrize !== null) {
            $uniquePair = $uniquePair->ignore($sponsorPrize->id_sponsor_prize, 'id_sponsor_prize');
        }

        return [
            'sponsor_id' => $required . '|integer|exists:sponsors,id_sponsor',
            'prize_id' => [$required, 'integer', 'exists:prizes,id_prize', $uniquePair],
            'media_path' => 'nullable|string|max:512',
            'template' => 'nullable|in:IMAGE_LEFT,IMAGE_TOP,IMAGE_RIGHT,IMAGE_MIDDLE,IMAGE_BOTTOM',
            'box_1_text' => 'nullable|string|max:200',
            'box_1_bg_color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'box_1_text_color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'box_2_text' => 'nullable|string|max:200',
            'box_2_bg_color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'box_2_text_color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
        ];
    }
}
