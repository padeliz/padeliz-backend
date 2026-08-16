<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Requests\Manager\ManagerEligibilityPortraitPhotoUploadRequest;
use App\Http\Requests\Manager\ManagerEligibilityStudentProofUploadRequest;
use App\Http\Resources\EligibilityResource;
use App\Models\Eligibility;
use App\Models\EligibilityPortraitPhoto;
use App\Models\EligibilityStudentProof;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Eligibility Uploads",
 *     description="(v1) Manager eligibility document uploads (blocked when approved)"
 * )
 */
class EligibilityUploadController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Post(
     *     path="/api/v1/manager/eligibilities/{playerEligibility}/student-proofs",
     *     summary="Upload student proof",
     *     tags={"🐧 Manager: Eligibility Uploads"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="playerEligibility", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="multipart/form-data", @OA\Schema(@OA\Property(property="file", type="string", format="binary")))),
     *     @OA\Response(response=201, description="Uploaded")
     * )
     */
    public function storeStudentProof(
        ManagerEligibilityStudentProofUploadRequest $request,
        Eligibility $playerEligibility
    ): JsonResponse {
        if ($response = $this->guardNotApproved($playerEligibility)) {
            return $response;
        }

        if ($playerEligibility->being_student !== true) {
            return $this->errorResponse(
                'Student proof uploads require being_student to be enabled on the eligibility.',
                422
            );
        }

        if ($playerEligibility->studentProofs()->count() >= EligibilityStudentProof::MAX_PER_ELIGIBILITY) {
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
                'eligibility_id' => $playerEligibility->id_eligibility,
                'student_proof_path' => '',
                'sort_order' => $playerEligibility->studentProofs()->count(),
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
            Log::error('Manager EligibilityUploadController::storeStudentProof failed', [
                'eligibility_id' => $playerEligibility->id_eligibility,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'The file failed to upload. Please try again with a different file.',
                422
            );
        }

        $playerEligibility->load(['player', 'studentProofs', 'portraitPhotos']);

        return $this->resourceResponse(
            new EligibilityResource($playerEligibility),
            'Student proof uploaded successfully',
            201
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/eligibilities/{playerEligibility}/student-proofs/{eligibilityStudentProof}",
     *     summary="Delete student proof",
     *     tags={"🐧 Manager: Eligibility Uploads"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="playerEligibility", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="eligibilityStudentProof", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted")
     * )
     */
    public function destroyStudentProof(
        Eligibility $playerEligibility,
        EligibilityStudentProof $eligibilityStudentProof
    ): JsonResponse {
        if ($response = $this->guardNotApproved($playerEligibility)) {
            return $response;
        }

        if ((int) $eligibilityStudentProof->eligibility_id !== (int) $playerEligibility->id_eligibility) {
            return $this->errorResponse('Student proof does not belong to this eligibility.', 404);
        }

        $eligibilityStudentProof->delete();
        $playerEligibility->load(['player', 'studentProofs', 'portraitPhotos']);

        return $this->resourceResponse(
            new EligibilityResource($playerEligibility),
            'Student proof deleted successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/eligibilities/{playerEligibility}/portrait-photos",
     *     summary="Upload portrait photo",
     *     tags={"🐧 Manager: Eligibility Uploads"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="playerEligibility", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\MediaType(mediaType="multipart/form-data", @OA\Schema(@OA\Property(property="file", type="string", format="binary")))),
     *     @OA\Response(response=201, description="Uploaded")
     * )
     */
    public function storePortraitPhoto(
        ManagerEligibilityPortraitPhotoUploadRequest $request,
        Eligibility $playerEligibility
    ): JsonResponse {
        if ($response = $this->guardNotApproved($playerEligibility)) {
            return $response;
        }

        if ($playerEligibility->portraitPhotos()->count() >= EligibilityPortraitPhoto::MAX_PER_ELIGIBILITY) {
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
                'eligibility_id' => $playerEligibility->id_eligibility,
                'portrait_photo_path' => '',
                'sort_order' => $playerEligibility->portraitPhotos()->count(),
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
            Log::error('Manager EligibilityUploadController::storePortraitPhoto failed', [
                'eligibility_id' => $playerEligibility->id_eligibility,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'The image failed to upload. Please try again with a different image.',
                422
            );
        }

        $playerEligibility->load(['player', 'studentProofs', 'portraitPhotos']);

        return $this->resourceResponse(
            new EligibilityResource($playerEligibility),
            'Portrait photo uploaded successfully',
            201
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/eligibilities/{playerEligibility}/portrait-photos/{eligibilityPortraitPhoto}",
     *     summary="Delete portrait photo",
     *     tags={"🐧 Manager: Eligibility Uploads"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="playerEligibility", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="eligibilityPortraitPhoto", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted")
     * )
     */
    public function destroyPortraitPhoto(
        Eligibility $playerEligibility,
        EligibilityPortraitPhoto $eligibilityPortraitPhoto
    ): JsonResponse {
        if ($response = $this->guardNotApproved($playerEligibility)) {
            return $response;
        }

        if ((int) $eligibilityPortraitPhoto->eligibility_id !== (int) $playerEligibility->id_eligibility) {
            return $this->errorResponse('Portrait photo does not belong to this eligibility.', 404);
        }

        $eligibilityPortraitPhoto->delete();
        $playerEligibility->load(['player', 'studentProofs', 'portraitPhotos']);

        return $this->resourceResponse(
            new EligibilityResource($playerEligibility),
            'Portrait photo deleted successfully'
        );
    }

    private function guardNotApproved(Eligibility $eligibility): ?JsonResponse
    {
        if ($eligibility->review_status === 'approved') {
            return $this->forbiddenResponse(
                'Documents cannot be changed after eligibility is approved.'
            );
        }

        return null;
    }
}
