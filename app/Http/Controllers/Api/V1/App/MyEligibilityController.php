<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Requests\App\PendingEligibilityRequest;
use App\Http\Resources\EligibilityResource;
use App\Models\Eligibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="🐥 App: My Eligibility",
 *     description="(v1) Player eligibility (my endpoints)"
 * )
 */
class MyEligibilityController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/app/my/eligibility/status",
     *     summary="Get my eligibility status summary",
     *     tags={"🐥 App: My Eligibility"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Status summary",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", type="object",
     *                         @OA\Property(property="latest", type="string", nullable=true, enum={"pending","approved","rejected","discarded"}),
     *                         @OA\Property(property="overall", type="object",
     *                             @OA\Property(property="approved", type="integer"),
     *                             @OA\Property(property="pending", type="integer"),
     *                             @OA\Property(property="discarded", type="integer"),
     *                             @OA\Property(property="rejected", type="integer")
     *                         )
     *                     )
     *                 )
     *             }
     *         )
     *     )
     * )
     */
    public function status(Request $request)
    {
        $playerId = $request->user()->id_player;

        $latest = Eligibility::where('player_id', $playerId)
            ->orderByDesc('inserted_at')
            ->orderByDesc('id_eligibility')
            ->value('review_status');

        $counts = Eligibility::where('player_id', $playerId)
            ->select('review_status', DB::raw('COUNT(*) as cnt'))
            ->groupBy('review_status')
            ->pluck('cnt', 'review_status')
            ->all();

        $data = [
            'latest' => $latest,
            'overall' => [
                'approved' => (int) ($counts['approved'] ?? 0),
                'pending' => (int) ($counts['pending'] ?? 0),
                'discarded' => (int) ($counts['discarded'] ?? 0),
                'rejected' => (int) ($counts['rejected'] ?? 0),
            ],
        ];

        return $this->successResponse($data, 'Eligibility status retrieved successfully');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/app/my/eligibility/approved",
     *     summary="Get my last approved eligibility",
     *     tags={"🐥 App: My Eligibility"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Last approved eligibility",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/Eligibility")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No approved eligibility found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function approved(Request $request)
    {
        $eligibility = Eligibility::where('player_id', $request->user()->id_player)
            ->where('review_status', 'approved')
            ->orderByDesc('reviewed_at')
            ->orderByDesc('id_eligibility')
            ->first();

        if (! $eligibility) {
            return $this->notFoundResponse('No approved eligibility found');
        }

        $eligibility->load(['studentProofs', 'portraitPhotos']);

        return $this->resourceResponse(
            new EligibilityResource($eligibility),
            'Approved eligibility retrieved successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/app/my/eligibility/latest",
     *     summary="Get my latest eligibility (any status)",
     *     tags={"🐥 App: My Eligibility"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Latest eligibility",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/Eligibility")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No eligibility found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function latest(Request $request)
    {
        $eligibility = Eligibility::where('player_id', $request->user()->id_player)
            ->orderByDesc('inserted_at')
            ->orderByDesc('id_eligibility')
            ->first();

        if (! $eligibility) {
            return $this->notFoundResponse('No eligibility found');
        }

        $eligibility->load(['studentProofs', 'portraitPhotos']);

        return $this->resourceResponse(
            new EligibilityResource($eligibility),
            'Latest eligibility retrieved successfully'
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/app/my/eligibility/pending",
     *     summary="Create or patch my pending eligibility",
     *     tags={"🐥 App: My Eligibility"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="duplicate_id",
     *         in="query",
     *         required=false,
     *         description="Copy data from an existing eligibility (must belong to the authenticated player). Only used when there is no current pending eligibility.",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="media_source", type="array", @OA\Items(type="integer"), nullable=true),
     *             @OA\Property(property="friends_source", type="array", @OA\Items(type="string"), nullable=true),
     *             @OA\Property(property="first_name", type="string", maxLength=50, nullable=true),
     *             @OA\Property(property="last_name", type="string", maxLength=50, nullable=true),
     *             @OA\Property(property="phone", type="string", maxLength=100, nullable=true),
     *             @OA\Property(property="year_of_birth", type="integer", format="year", nullable=true),
     *
     *             @OA\Property(property="event_ids", type="array", @OA\Items(type="integer"), nullable=true),
     *             @OA\Property(property="racket_experience", type="array", @OA\Items(type="string"), nullable=true),
     *             @OA\Property(property="middle_name", type="string", maxLength=50, nullable=true),
     *             @OA\Property(property="birth_month", type="integer", nullable=true),
     *             @OA\Property(property="birth_day", type="integer", nullable=true),
     *             @OA\Property(property="being_student", type="boolean", nullable=true),
     *             @OA\Property(property="playtomic_id", type="string", maxLength=10, nullable=true),
     *             @OA\Property(property="rankedin_id", type="string", maxLength=15, nullable=true),
     *             @OA\Property(property="instagram_username", type="string", maxLength=50, nullable=true),
     *             @OA\Property(property="facebook_username", type="string", maxLength=50, nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Pending eligibility upserted",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", ref="#/components/schemas/Eligibility")
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
    public function pending(PendingEligibilityRequest $request)
    {
        $playerId = $request->user()->id_player;
        $validated = $request->validated();
        $duplicateId = $validated['duplicate_id'] ?? null;
        unset($validated['duplicate_id']);
        $now = time();

        $pending = Eligibility::where('player_id', $playerId)
            ->where('review_status', 'pending')
            ->first();

        if ($pending) {
            if ($duplicateId !== null) {
                return $this->validationErrorResponse(['duplicate_id' => ['Cannot be used while a pending eligibility exists']]);
            }

            if (count($validated) > 0) {
                $pending->fill($validated);
                $pending->resubmitted_at = $now;
                $pending->updated_at = $now;
            }

            $pending->recomputeReadyForReview();
            $pending->save();
            $pending->load(['studentProofs', 'portraitPhotos']);

            return $this->resourceResponse(
                new EligibilityResource($pending),
                'Pending eligibility updated successfully'
            );
        }

        $copy = [];
        if ($duplicateId !== null) {
            $source = Eligibility::where('id_eligibility', $duplicateId)
                ->where('player_id', $playerId)
                ->first();

            if ($source) {
                $copy = $source->only([
                    'event_ids',
                    'media_source',
                    'friends_source',
                    'racket_experience',
                    'playtomic_id',
                    'rankedin_id',
                    'first_name',
                    'middle_name',
                    'last_name',
                    'phone',
                    'year_of_birth',
                    'birth_month',
                    'birth_day',
                    'being_student',
                    'instagram_username',
                    'facebook_username',
                ]);
            }
        }

        $eligibility = new Eligibility();
        $eligibility->fill(array_merge($copy, $validated));
        $eligibility->player_id = $playerId;
        $eligibility->submitted_at = $now;
        $eligibility->review_status = 'pending';
        $eligibility->inserted_at = $now;
        $eligibility->updated_at = null;
        $eligibility->reviewed_at = null;
        $eligibility->review_message = null;
        $eligibility->ready_for_review = 0;

        $eligibility->recomputeReadyForReview();
        $eligibility->save();
        $eligibility->load(['studentProofs', 'portraitPhotos']);

        return $this->resourceResponse(
            new EligibilityResource($eligibility),
            'Pending eligibility created successfully',
            201
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/app/my/eligibility/discard",
     *     summary="Discard my pending eligibility (if exists)",
     *     tags={"🐥 App: My Eligibility"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Discarded or no-op",
     *         @OA\JsonContent(ref="#/components/schemas/ApiResponse")
     *     )
     * )
     */
    public function discard(Request $request)
    {
        $playerId = $request->user()->id_player;
        $now = time();

        $pending = Eligibility::where('player_id', $playerId)
            ->where('review_status', 'pending')
            ->first();

        if (! $pending) {
            return $this->successResponse(null, 'No pending eligibility to discard');
        }

        $pending->load(['studentProofs', 'portraitPhotos']);
        $pending->studentProofs->each->delete();
        $pending->portraitPhotos->each->delete();

        $pending->review_status = 'discarded';
        $pending->resubmitted_at = $now;
        $pending->updated_at = $now;
        $pending->save();

        return $this->successResponse(null, 'Pending eligibility discarded successfully');
    }
}
