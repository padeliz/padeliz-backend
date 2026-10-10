<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Enums\PlayerCustomFieldType;
use App\Enums\PlayerLevel\AssessmentReviewStatus;
use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Http\Requests\App\PlayerProfileImageUploadRequest;
use App\Http\Resources\PlayerLevelAssessmentResource;
use App\Http\Resources\PlayerLevelResource;
use App\Models\Player;
use App\Models\PlayerCustomField;
use App\Models\PlayerLevelAssessment;
use App\Models\PlayerProfileImage;
use App\Services\PlayerCustomField\PlayerCustomFieldSubmissionWriter;
use App\Services\PlayerLevel\AssessmentProfileIdNormalizer;
use App\Services\PlayerLevel\LevelAssessmentService;
use App\Services\PlayerLevel\PlayerLevelService;
use App\Services\PlayerProfileImage\PlayerProfileImagePrimaryService;
use App\Services\PlayerProfileImage\PlayerProfileImageUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="🐥 App: My Level Assessment",
 *     description="(v1) Player level assessment (my endpoints)"
 * )
 */
class MyLevelAssessmentController extends V1BaseController
{
    /**
     * Relations returned with every assessment payload.
     *
     * @var list<string>
     */
    private const WITH = ['submission.values.field', 'playerLevel', 'profileImages'];

    public function __construct(
        private readonly LevelAssessmentService $assessments,
        private readonly PlayerLevelService $playerLevels,
        private readonly PlayerCustomFieldSubmissionWriter $submissionWriter,
    ) {
        parent::__construct();
    }

    /**
     * @OA\Get(
     *     path="/api/v1/app/my/level-assessment/status",
     *     summary="Get my level assessment status summary",
     *     tags={"🐥 App: My Level Assessment"},
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
    public function status(Request $request): JsonResponse
    {
        $playerId = (int) $request->user()->id_player;

        $latest = PlayerLevelAssessment::where('player_id', $playerId)
            ->orderByDesc('inserted_at')
            ->orderByDesc('id_player_level_assessment')
            ->value('review_status');

        $counts = PlayerLevelAssessment::where('player_id', $playerId)
            ->select('review_status', DB::raw('COUNT(*) as cnt'))
            ->groupBy('review_status')
            ->pluck('cnt', 'review_status')
            ->all();

        $data = [
            'latest' => $latest instanceof AssessmentReviewStatus ? $latest->value : $latest,
            'overall' => [
                'approved' => (int) ($counts['approved'] ?? 0),
                'pending' => (int) ($counts['pending'] ?? 0),
                'discarded' => (int) ($counts['discarded'] ?? 0),
                'rejected' => (int) ($counts['rejected'] ?? 0),
            ],
        ];

        return $this->successResponse($data, 'Level assessment status retrieved successfully');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/app/my/level-assessment/latest",
     *     summary="Get my latest level assessment (any status)",
     *     tags={"🐥 App: My Level Assessment"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Latest level assessment"),
     *     @OA\Response(response=404, description="No level assessment found", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function latest(Request $request): JsonResponse
    {
        $assessment = PlayerLevelAssessment::where('player_id', $request->user()->id_player)
            ->orderByDesc('inserted_at')
            ->orderByDesc('id_player_level_assessment')
            ->first();

        if ($assessment === null) {
            return $this->notFoundResponse('No level assessment found');
        }

        return $this->assessmentResponse($assessment, 'Latest level assessment retrieved successfully');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/app/my/level-assessment/pending",
     *     summary="Get my pending level assessment",
     *     tags={"🐥 App: My Level Assessment"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Pending level assessment"),
     *     @OA\Response(response=404, description="No pending level assessment", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function showPending(Request $request): JsonResponse
    {
        $pending = $this->assessments->getPending((int) $request->user()->id_player);

        if ($pending === null) {
            return $this->notFoundResponse('No pending level assessment found');
        }

        return $this->assessmentResponse($pending, 'Pending level assessment retrieved successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/app/my/level-assessment/pending",
     *     summary="Create (or return) my pending level assessment",
     *     description="Optionally writes custom field answers into the pending assessment's submission. Rejected while the assessment is ready for review.",
     *     tags={"🐥 App: My Level Assessment"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="custom_fields",
     *                 type="object",
     *                 nullable=true,
     *                 description="Map of custom field code to answer. Scalars map to `value`; select fields accept an option id (or a list of ids for multi_select); an object with value / option_id / option_ids is passed through."
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Pending level assessment created"),
     *     @OA\Response(response=200, description="Existing pending level assessment returned or updated"),
     *     @OA\Response(response=422, description="Validation error or assessment locked for review", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function storePending(Request $request): JsonResponse
    {
        $customFields = $this->validatedCustomFields($request);

        /** @var Player $player */
        $player = $request->user();

        $existing = $this->assessments->getPending((int) $player->id_player);

        if ($existing !== null && $existing->ready_for_review) {
            return $this->lockedResponse();
        }

        $assessment = $existing ?? $this->assessments->ensurePending($player);
        $created = $existing === null;

        $this->writeCustomFields($assessment, $customFields, (int) $player->id_player);

        return $this->assessmentResponse(
            $assessment,
            $created ? 'Pending level assessment created successfully' : 'Pending level assessment retrieved successfully',
            $created ? 201 : 200
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/app/my/level-assessment/pending",
     *     summary="Partially update my pending level assessment answers",
     *     tags={"🐥 App: My Level Assessment"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="custom_fields", type="object", description="Map of custom field code to answer")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Pending level assessment updated"),
     *     @OA\Response(response=422, description="Validation error or assessment locked for review", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function patchPending(Request $request): JsonResponse
    {
        $customFields = $this->validatedCustomFields($request);

        /** @var Player $player */
        $player = $request->user();

        $assessment = $this->assessments->ensurePending($player);

        if ($assessment->ready_for_review) {
            return $this->lockedResponse();
        }

        $this->writeCustomFields($assessment, $customFields, (int) $player->id_player);

        return $this->assessmentResponse($assessment, 'Pending level assessment updated successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/app/my/level-assessment/pending/ready",
     *     summary="Mark my pending level assessment ready for review",
     *     tags={"🐥 App: My Level Assessment"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Marked ready for review"),
     *     @OA\Response(response=404, description="No pending level assessment", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function markReady(Request $request): JsonResponse
    {
        $pending = $this->assessments->getPending((int) $request->user()->id_player);

        if ($pending === null) {
            return $this->notFoundResponse('No pending level assessment found');
        }

        $this->assessments->markReady($pending);

        return $this->assessmentResponse($pending, 'Level assessment is ready for review');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/app/my/level-assessment/pending/reopen",
     *     summary="Pull my pending level assessment back out of the review queue",
     *     tags={"🐥 App: My Level Assessment"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Reopened for editing"),
     *     @OA\Response(response=404, description="No pending level assessment", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function reopen(Request $request): JsonResponse
    {
        $pending = $this->assessments->getPending((int) $request->user()->id_player);

        if ($pending === null) {
            return $this->notFoundResponse('No pending level assessment found');
        }

        $this->assessments->reopenPending($pending);

        return $this->assessmentResponse($pending, 'Level assessment reopened for editing');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/app/my/level-assessment/discard",
     *     summary="Discard my pending level assessment (if exists)",
     *     tags={"🐥 App: My Level Assessment"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Discarded or no-op", @OA\JsonContent(ref="#/components/schemas/ApiResponse"))
     * )
     */
    public function discard(Request $request): JsonResponse
    {
        $pending = $this->assessments->getPending((int) $request->user()->id_player);

        if ($pending === null) {
            return $this->successResponse(null, 'No pending level assessment to discard');
        }

        $this->assessments->discardPending($pending);

        return $this->successResponse(null, 'Pending level assessment discarded successfully');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/app/my/level-assessment/current-level",
     *     summary="Get my current player level",
     *     tags={"🐥 App: My Level Assessment"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Current level with band name"),
     *     @OA\Response(response=404, description="No level yet", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function currentLevel(Request $request): JsonResponse
    {
        $current = $this->playerLevels->currentLevel((int) $request->user()->id_player);

        if ($current === null) {
            return $this->notFoundResponse('No player level found');
        }

        return $this->resourceResponse(
            new PlayerLevelResource($current),
            'Current player level retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/app/my/level-assessment/pending/profile-image",
     *     summary="Upload a profile image linked to my pending level assessment",
     *     tags={"🐥 App: My Level Assessment"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(required={"image"}, @OA\Property(property="image", type="string", format="binary"))
     *         )
     *     ),
     *     @OA\Response(response=201, description="Image uploaded"),
     *     @OA\Response(response=422, description="Validation error, too many images, or locked for review", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function storeProfileImage(PlayerProfileImageUploadRequest $request): JsonResponse
    {
        /** @var Player $player */
        $player = $request->user();

        $assessment = $this->assessments->ensurePending($player);

        if ($assessment->ready_for_review) {
            return $this->lockedResponse();
        }

        try {
            app(PlayerProfileImageUploadService::class)->upload($player, $request->file('image'), [
                'uploaded_via' => PlayerProfileImage::UPLOADED_VIA_APP_LEVEL_ASSESSMENT,
                'uploaded_via_level_assessment_id' => (int) $assessment->id_player_level_assessment,
                'original_filename' => $request->file('image')->getClientOriginalName(),
                'is_pdf_primary' => false,
            ]);
        } catch (\RuntimeException $e) {
            if (str_contains($e->getMessage(), 'Maximum number of profile images')) {
                return $this->errorResponse($e->getMessage(), 422);
            }

            Log::error('MyLevelAssessmentController::storeProfileImage - Error uploading file', [
                'player_id' => $player->id_player,
                'assessment_id' => $assessment->id_player_level_assessment,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'The image failed to upload. Please try again with a different image.',
                422
            );
        } catch (\Throwable $e) {
            Log::error('MyLevelAssessmentController::storeProfileImage - Error uploading file', [
                'player_id' => $player->id_player,
                'assessment_id' => $assessment->id_player_level_assessment,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'The image failed to upload. Please try again with a different image.',
                422
            );
        }

        return $this->assessmentResponse($assessment, 'Image uploaded successfully', 201);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/app/my/level-assessment/pending/profile-image/{playerProfileImage}",
     *     summary="Delete a profile image linked to my pending level assessment",
     *     tags={"🐥 App: My Level Assessment"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="playerProfileImage", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Image deleted"),
     *     @OA\Response(response=404, description="Image or pending assessment not found", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=422, description="Assessment locked for review", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function destroyProfileImage(Request $request, PlayerProfileImage $playerProfileImage): JsonResponse
    {
        /** @var Player $player */
        $player = $request->user();

        $assessment = $this->assessments->getPending((int) $player->id_player);

        if (
            $assessment === null
            || (int) $playerProfileImage->player_id !== (int) $player->id_player
            || (int) $playerProfileImage->uploaded_via_level_assessment_id !== (int) $assessment->id_player_level_assessment
        ) {
            return $this->notFoundResponse('Profile image not found on the pending level assessment');
        }

        if ($assessment->ready_for_review) {
            return $this->lockedResponse();
        }

        $primaryService = app(PlayerProfileImagePrimaryService::class);
        $wasPrimary = $primaryService->wasResolvedPrimary($player, $playerProfileImage);
        $playerProfileImage->delete();

        if ($wasPrimary) {
            $player->load('profileImages');
            $primaryService->promoteSurvivorAfterDelete($player);
        }

        return $this->assessmentResponse($assessment, 'Image deleted successfully');
    }

    /**
     * Accepts `custom_fields` as a code => answer map and checks every code is a known, active field.
     *
     * @return array<string, mixed>
     */
    private function validatedCustomFields(Request $request): array
    {
        $validated = $request->validate([
            'custom_fields' => 'sometimes|nullable|array',
        ]);

        $map = $validated['custom_fields'] ?? [];
        if ($map === []) {
            return [];
        }

        $fields = PlayerCustomField::query()->whereIn('code', array_map('strval', array_keys($map)))->get();
        $known = $fields->keyBy('code');

        $errors = [];
        foreach (array_keys($map) as $code) {
            $field = $known->get((string) $code);
            if ($field === null) {
                $errors["custom_fields.{$code}"] = ['Unknown custom field.'];
            } elseif ($field->isArchived()) {
                $errors["custom_fields.{$code}"] = ['This custom field is archived.'];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return AssessmentProfileIdNormalizer::normalize($map);
    }

    /**
     * Writes answers into the assessment's own submission so editing the draft never spawns new ones.
     *
     * @param  array<string, mixed>  $customFields
     */
    private function writeCustomFields(PlayerLevelAssessment $assessment, array $customFields, int $playerId): void
    {
        if ($customFields === []) {
            return;
        }

        $assessment->loadMissing('submission');
        $submission = $assessment->submission;
        $fields = PlayerCustomField::query()
            ->whereIn('code', array_map('strval', array_keys($customFields)))
            ->get()
            ->keyBy('code');

        DB::transaction(function () use ($customFields, $fields, $submission, $playerId): void {
            foreach ($customFields as $code => $answer) {
                /** @var PlayerCustomField $field */
                $field = $fields->get((string) $code);

                try {
                    $this->submissionWriter->write(
                        $submission,
                        $field,
                        $this->toWriterInput($field, $answer),
                        $playerId
                    );
                } catch (ValidationException $e) {
                    $prefixed = [];
                    foreach ($e->errors() as $key => $messages) {
                        $prefixed["custom_fields.{$code}.{$key}"] = $messages;
                    }

                    throw ValidationException::withMessages($prefixed);
                }
            }
        });

        $assessment->touch();
    }

    /**
     * @return array<string, mixed>
     */
    private function toWriterInput(PlayerCustomField $field, mixed $answer): array
    {
        $passThroughKeys = ['value', 'option_id', 'option_ids'];

        if (is_array($answer) && ! array_is_list($answer) && array_intersect(array_keys($answer), $passThroughKeys) !== []) {
            return $answer;
        }

        return match ($field->type) {
            PlayerCustomFieldType::SingleSelect => ['option_id' => $answer],
            PlayerCustomFieldType::MultiSelect => ['option_ids' => is_array($answer) ? $answer : [$answer]],
            default => ['value' => $answer],
        };
    }

    private function assessmentResponse(PlayerLevelAssessment $assessment, string $message, int $code = 200): JsonResponse
    {
        $assessment->unsetRelations()->load(self::WITH);

        return $this->resourceResponse(new PlayerLevelAssessmentResource($assessment), $message, $code);
    }

    private function lockedResponse(): JsonResponse
    {
        return $this->validationErrorResponse(
            ['ready_for_review' => ['The assessment is ready for review. Reopen it before making changes.']],
            'Level assessment is locked while ready for review'
        );
    }
}
