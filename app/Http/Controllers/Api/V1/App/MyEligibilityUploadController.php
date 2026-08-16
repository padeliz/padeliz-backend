<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Requests\App\EligibilityPortraitPhotoUploadRequest;
use App\Http\Requests\App\EligibilityStudentProofUploadRequest;
use App\Http\Resources\EligibilityResource;
use App\Models\Eligibility;
use App\Models\EligibilityPortraitPhoto;
use App\Models\EligibilityStudentProof;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Tag(
 *     name="🐥 App: My Eligibility",
 *     description="(v1) Player eligibility (my endpoints)"
 * )
 */
class MyEligibilityUploadController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Post(
     *     path="/api/v1/app/my/eligibility/pending/student-proof",
     *     summary="Upload a student proof file for pending eligibility",
     *     tags={"🐥 App: My Eligibility"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="file", type="string", format="binary", description="JPEG, PNG, GIF, WebP, or PDF (max 30 MB; server PHP upload_max_filesize must allow the file)")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="File uploaded",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(@OA\Property(property="data", ref="#/components/schemas/Eligibility"))
     *             }
     *         )
     *     ),
     *     @OA\Response(response=404, description="No pending eligibility", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=422, description="Validation, not a student, or max files", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function storeStudentProof(EligibilityStudentProofUploadRequest $request): JsonResponse
    {
        $eligibility = $this->resolvePendingEligibility($request);

        if ($response = $this->authorizeUploadMutation($request, $eligibility)) {
            return $response;
        }

        if ($eligibility->being_student !== true) {
            return $this->errorResponse(
                'Student proof uploads require being_student to be enabled on your pending eligibility.',
                422
            );
        }

        if ($eligibility->studentProofs()->count() >= EligibilityStudentProof::MAX_PER_ELIGIBILITY) {
            return $this->errorResponse(
                'Maximum number of student proof files (' . EligibilityStudentProof::MAX_PER_ELIGIBILITY . ') reached. Delete an existing file first.',
                422
            );
        }

        $disk = config('filesystems.user_uploads_disk', 'public');
        $storedPath = null;

        try {
            DB::beginTransaction();

            $proof = EligibilityStudentProof::create([
                'eligibility_id' => $eligibility->id_eligibility,
                'student_proof_path' => '',
                'sort_order' => $eligibility->studentProofs()->count(),
            ]);

            $storedPath = Storage::disk($disk)->putFile(
                'eligibility-student-proofs/' . $proof->getKey() . '/student-proof',
                $request->file('file'),
                ['visibility' => 'public']
            );

            if ($storedPath === false || $storedPath === null) {
                throw new \RuntimeException('Could not store the uploaded file.');
            }

            $proof->update(['student_proof_path' => $storedPath]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            if ($storedPath !== null && $storedPath !== false) {
                Storage::disk($disk)->delete($storedPath);
            }

            Log::error('MyEligibilityUploadController::storeStudentProof failed', [
                'player_id' => $request->user()->id_player,
                'eligibility_id' => $eligibility->id_eligibility,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'The file failed to upload. Please try again with a different file.',
                422
            );
        }

        $eligibility->load(['studentProofs', 'portraitPhotos']);

        return $this->resourceResponse(
            new EligibilityResource($eligibility),
            'Student proof uploaded successfully',
            201
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/app/my/eligibility/pending/student-proof/{eligibilityStudentProof}",
     *     summary="Delete a student proof file",
     *     tags={"🐥 App: My Eligibility"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="eligibilityStudentProof", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Deleted",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(@OA\Property(property="data", ref="#/components/schemas/Eligibility"))
     *             }
     *         )
     *     ),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function destroyStudentProof(Request $request, EligibilityStudentProof $eligibilityStudentProof): JsonResponse
    {
        $eligibility = $eligibilityStudentProof->eligibility;

        if ($response = $this->authorizeUploadMutation($request, $eligibility)) {
            return $response;
        }

        $eligibilityStudentProof->delete();
        $eligibility->load(['studentProofs', 'portraitPhotos']);

        return $this->resourceResponse(
            new EligibilityResource($eligibility),
            'Student proof deleted successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/app/my/eligibility/pending/portrait-photo",
     *     summary="Upload a portrait photo for pending eligibility",
     *     tags={"🐥 App: My Eligibility"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="file", type="string", format="binary", description="Image (JPEG, PNG, GIF, WebP) max 5 MB; server PHP upload_max_filesize must allow the file")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Photo uploaded",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(@OA\Property(property="data", ref="#/components/schemas/Eligibility"))
     *             }
     *         )
     *     ),
     *     @OA\Response(response=404, description="No pending eligibility", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=422, description="Validation or max photos", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function storePortraitPhoto(EligibilityPortraitPhotoUploadRequest $request): JsonResponse
    {
        $eligibility = $this->resolvePendingEligibility($request);

        if ($response = $this->authorizeUploadMutation($request, $eligibility)) {
            return $response;
        }

        if ($eligibility->portraitPhotos()->count() >= EligibilityPortraitPhoto::MAX_PER_ELIGIBILITY) {
            return $this->errorResponse(
                'Maximum number of portrait photos (' . EligibilityPortraitPhoto::MAX_PER_ELIGIBILITY . ') reached. Delete an existing photo first.',
                422
            );
        }

        $disk = config('filesystems.user_uploads_disk', 'public');
        $storedPath = null;

        try {
            DB::beginTransaction();

            $photo = EligibilityPortraitPhoto::create([
                'eligibility_id' => $eligibility->id_eligibility,
                'portrait_photo_path' => '',
                'sort_order' => $eligibility->portraitPhotos()->count(),
            ]);

            $storedPath = Storage::disk($disk)->putFile(
                'eligibility-portrait-photos/' . $photo->getKey() . '/portrait-photo',
                $request->file('file'),
                ['visibility' => 'public']
            );

            if ($storedPath === false || $storedPath === null) {
                throw new \RuntimeException('Could not store the uploaded file.');
            }

            $photo->update(['portrait_photo_path' => $storedPath]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            if ($storedPath !== null && $storedPath !== false) {
                Storage::disk($disk)->delete($storedPath);
            }

            Log::error('MyEligibilityUploadController::storePortraitPhoto failed', [
                'player_id' => $request->user()->id_player,
                'eligibility_id' => $eligibility->id_eligibility,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'The image failed to upload. Please try again with a different image.',
                422
            );
        }

        $eligibility->load(['studentProofs', 'portraitPhotos']);

        return $this->resourceResponse(
            new EligibilityResource($eligibility),
            'Portrait photo uploaded successfully',
            201
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/app/my/eligibility/pending/portrait-photo/{eligibilityPortraitPhoto}",
     *     summary="Delete a portrait photo",
     *     tags={"🐥 App: My Eligibility"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="eligibilityPortraitPhoto", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Deleted",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(@OA\Property(property="data", ref="#/components/schemas/Eligibility"))
     *             }
     *         )
     *     ),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function destroyPortraitPhoto(Request $request, EligibilityPortraitPhoto $eligibilityPortraitPhoto): JsonResponse
    {
        $eligibility = $eligibilityPortraitPhoto->eligibility;

        if ($response = $this->authorizeUploadMutation($request, $eligibility)) {
            return $response;
        }

        $eligibilityPortraitPhoto->delete();
        $eligibility->load(['studentProofs', 'portraitPhotos']);

        return $this->resourceResponse(
            new EligibilityResource($eligibility),
            'Portrait photo deleted successfully'
        );
    }

    private function resolvePendingEligibility(Request $request): Eligibility
    {
        return Eligibility::where('player_id', $request->user()->id_player)
            ->where('review_status', 'pending')
            ->firstOrFail();
    }

    private function authorizeUploadMutation(Request $request, Eligibility $eligibility): ?JsonResponse
    {
        if ((int) $request->user()->id_player !== (int) $eligibility->player_id) {
            return $this->forbiddenResponse('You can only modify your own eligibility documents.');
        }

        if ($eligibility->review_status !== 'pending') {
            return $this->forbiddenResponse('Documents can only be changed while eligibility is pending.');
        }

        return null;
    }
}
