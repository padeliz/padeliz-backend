<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Http\Requests\App\PlayerStudentProofFileRequest;
use App\Http\Requests\App\PlayerStudentProofUpdateRequest;
use App\Http\Requests\App\PlayerStudentProofUploadRequest;
use App\Http\Resources\PlayerStudentProofResource;
use App\Models\User;
use App\Models\PlayerStudentProof;
use App\Services\StudentProof\StudentProofService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="🐥 App: My Student Proofs",
 *     description="(v1) Student proof submissions (one pending per player with 1-3 files; the student benefit needs an approved, unexpired proof)"
 * )
 */
class MyStudentProofController extends V1BaseController
{
    public function __construct(
        private readonly StudentProofService $proofs,
    ) {
        parent::__construct();
    }

    /**
     * @OA\Get(
     *     path="/api/v1/app/my/student-proofs",
     *     summary="List my student proofs (newest first) with the pending proof and active flag",
     *     tags={"🐥 App: My Student Proofs"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Student proofs")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $player */
        $player = $request->user();

        $proofs = PlayerStudentProof::query()
            ->where('player_user_id', $player->id_user)
            ->orderByDesc('inserted_at')
            ->orderByDesc('id_player_student_proof')
            ->with('files')
            ->get();

        $pending = $this->proofs->getPending((int) $player->id_user)?->load('files');

        return $this->successResponse([
            'has_active' => PlayerStudentProof::hasActiveFor($player),
            'pending' => $pending !== null ? (new PlayerStudentProofResource($pending))->resolve() : null,
            'items' => PlayerStudentProofResource::collection($proofs)->resolve(),
        ], 'Student proofs retrieved successfully');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/app/my/student-proofs/pending",
     *     summary="Get my pending student proof",
     *     tags={"🐥 App: My Student Proofs"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Pending student proof"),
     *     @OA\Response(response=404, description="No pending student proof", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function showPending(Request $request): JsonResponse
    {
        $pending = $this->proofs->getPending((int) $request->user()->id_user);

        if ($pending === null) {
            return $this->notFoundResponse('No pending student proof found');
        }

        return $this->proofResponse($pending, 'Pending student proof retrieved successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/app/my/student-proofs",
     *     summary="Create my pending student proof",
     *     description="Creates the pending proof from `student_until` and 1-3 `files[]`. Fails with 422 when a pending proof already exists (use PATCH pending and the pending/files endpoints instead).",
     *     tags={"🐥 App: My Student Proofs"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"student_until", "files"},
     *                 @OA\Property(property="student_until", type="string", format="date", example="2027-06-30"),
     *                 @OA\Property(property="files", type="array", minItems=1, maxItems=3, @OA\Items(type="string", format="binary"))
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Pending student proof created"),
     *     @OA\Response(response=422, description="Validation error or a pending proof already exists", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function store(PlayerStudentProofUploadRequest $request): JsonResponse
    {
        /** @var User $player */
        $player = $request->user();

        try {
            $proof = $this->proofs->createPending(
                $player,
                array_values($request->file('files', [])),
                (string) $request->validated('student_until'),
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('MyStudentProofController::store - Error storing student proof', [
                'player_user_id' => $player->id_user,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'The files failed to upload. Please try again with different files.',
                422
            );
        }

        return $this->proofResponse($proof, 'Pending student proof created successfully', 201);
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/app/my/student-proofs/pending",
     *     summary="Update the validity date of my pending student proof",
     *     description="Accepts `student_until` only; files are managed through the pending/files endpoints. Rejected while the proof is ready for review.",
     *     tags={"🐥 App: My Student Proofs"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(required={"student_until"}, @OA\Property(property="student_until", type="string", format="date", example="2027-06-30"))
     *     ),
     *     @OA\Response(response=200, description="Pending student proof updated"),
     *     @OA\Response(response=404, description="No pending student proof", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=422, description="Validation error or proof locked for review", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function updatePending(PlayerStudentProofUpdateRequest $request): JsonResponse
    {
        $pending = $this->proofs->getPending((int) $request->user()->id_user);

        if ($pending === null) {
            return $this->notFoundResponse('No pending student proof found');
        }

        $this->proofs->updateStudentUntil($pending, (string) $request->validated('student_until'));

        return $this->proofResponse($pending, 'Pending student proof updated successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/app/my/student-proofs/pending/files",
     *     summary="Append one file to my pending student proof (max 3)",
     *     tags={"🐥 App: My Student Proofs"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(required={"file"}, @OA\Property(property="file", type="string", format="binary"))
     *         )
     *     ),
     *     @OA\Response(response=201, description="File appended"),
     *     @OA\Response(response=404, description="No pending student proof", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=422, description="Validation error, file limit reached, or proof locked for review", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function addFile(PlayerStudentProofFileRequest $request): JsonResponse
    {
        $player = $request->user();
        $pending = $this->proofs->getPending((int) $player->id_user);

        if ($pending === null) {
            return $this->notFoundResponse('No pending student proof found');
        }

        try {
            $this->proofs->addFile($pending, $request->file('file'));
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('MyStudentProofController::addFile - Error storing student proof file', [
                'player_user_id' => $player->id_user,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'The file failed to upload. Please try again with a different file.',
                422
            );
        }

        return $this->proofResponse($pending, 'Student proof file added successfully', 201);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/app/my/student-proofs/pending/files/{file}",
     *     summary="Remove one file from my pending student proof",
     *     tags={"🐥 App: My Student Proofs"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="file", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="File removed"),
     *     @OA\Response(response=404, description="No pending student proof or file not found", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=422, description="Proof locked for review", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function removeFile(Request $request, int $file): JsonResponse
    {
        $pending = $this->proofs->getPending((int) $request->user()->id_user);

        if ($pending === null) {
            return $this->notFoundResponse('No pending student proof found');
        }

        $proofFile = $pending->files()->whereKey($file)->first();

        if ($proofFile === null) {
            return $this->notFoundResponse('Student proof file not found');
        }

        $this->proofs->removeFile($pending, $proofFile);

        return $this->proofResponse($pending, 'Student proof file removed successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/app/my/student-proofs/pending/ready",
     *     summary="Mark my pending student proof ready for review",
     *     tags={"🐥 App: My Student Proofs"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Marked ready for review"),
     *     @OA\Response(response=404, description="No pending student proof", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function markReady(Request $request): JsonResponse
    {
        $pending = $this->proofs->getPending((int) $request->user()->id_user);

        if ($pending === null) {
            return $this->notFoundResponse('No pending student proof found');
        }

        $this->proofs->markReady($pending);

        return $this->proofResponse($pending, 'Student proof is ready for review');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/app/my/student-proofs/pending/reopen",
     *     summary="Pull my pending student proof back out of the review queue",
     *     tags={"🐥 App: My Student Proofs"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Reopened for editing"),
     *     @OA\Response(response=404, description="No pending student proof", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function reopen(Request $request): JsonResponse
    {
        $pending = $this->proofs->getPending((int) $request->user()->id_user);

        if ($pending === null) {
            return $this->notFoundResponse('No pending student proof found');
        }

        $this->proofs->reopenPending($pending);

        return $this->proofResponse($pending, 'Student proof reopened for editing');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/app/my/student-proofs/discard",
     *     summary="Discard my pending student proof (if exists)",
     *     tags={"🐥 App: My Student Proofs"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Discarded or no-op", @OA\JsonContent(ref="#/components/schemas/ApiResponse"))
     * )
     */
    public function discard(Request $request): JsonResponse
    {
        $pending = $this->proofs->getPending((int) $request->user()->id_user);

        if ($pending === null) {
            return $this->successResponse(null, 'No pending student proof to discard');
        }

        $this->proofs->discardPending($pending);

        return $this->successResponse(null, 'Pending student proof discarded successfully');
    }

    private function proofResponse(PlayerStudentProof $proof, string $message, int $code = 200): JsonResponse
    {
        return $this->resourceResponse(
            new PlayerStudentProofResource($proof->refresh()->load('files')),
            $message,
            $code
        );
    }
}
