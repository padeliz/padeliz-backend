<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PlayerProfileImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayerLevelAssessmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_player_level_assessment' => $this->id_player_level_assessment,
            'player_user_id' => $this->player_user_id,
            'player_custom_field_submission_id' => $this->player_custom_field_submission_id,
            'submitted_at' => $this->submitted_at,
            'resubmitted_at' => $this->resubmitted_at,
            'ready_for_review' => (bool) $this->ready_for_review,
            'reviewed_at' => $this->reviewed_at,
            'review_status' => $this->review_status?->value,
            'review_message' => $this->review_message,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
            'player' => $this->whenLoaded('player', function () {
                return new UserSummaryResource($this->player);
            }),
            'submission' => $this->whenLoaded('submission', function () {
                return [
                    'id_player_custom_field_submission' => $this->submission->id_player_custom_field_submission,
                    'player_user_id' => $this->submission->player_user_id,
                    'submitted_via' => $this->submission->submitted_via,
                    'submitted_by_player_user_id' => $this->submission->submitted_by_player_user_id,
                    'inserted_at' => $this->submission->inserted_at,
                    'values' => $this->submission->relationLoaded('values')
                        ? PlayerCustomFieldValueResource::collection($this->submission->values)
                        : null,
                ];
            }),
            'player_level' => $this->whenLoaded('playerLevel', function () {
                return $this->playerLevel !== null
                    ? new PlayerLevelResource($this->playerLevel)
                    : null;
            }),
            'profile_images' => $this->whenLoaded('profileImages', function () {
                return $this->profileImages
                    ->map(static fn (PlayerProfileImage $image) => $image->toApiArray())
                    ->values()
                    ->all();
            }),
        ];
    }
}
