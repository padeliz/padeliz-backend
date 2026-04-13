<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayerEligibilityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_eligibility' => $this->id_eligibility,
            'player_id' => $this->player_id,
            'edition_ids' => $this->edition_ids,
            'media_source' => $this->media_source,
            'friends_source' => $this->friends_source,
            'racket_experience' => $this->racket_experience,
            'phone' => $this->phone,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'birth_year' => $this->birth_year,
            'birth_month' => $this->birth_month,
            'birth_day' => $this->birth_day,
            'being_student' => $this->being_student,
            'playtomic_id' => $this->playtomic_id,
            'rankedin_id' => $this->rankedin_id,
            'instagram_username' => $this->instagram_username,
            'facebook_username' => $this->facebook_username,
            'submitted_at' => $this->submitted_at,
            'reviewed_at' => $this->reviewed_at,
            'ready_for_review' => $this->ready_for_review,
            'review_status' => $this->review_status,
            'review_message' => $this->review_message,
            'resubmitted_at' => $this->resubmitted_at,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
            'player' => $this->whenLoaded('player', function () {
                return new PlayerResource($this->player);
            }),
        ];
    }
}
