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
            'edition_id' => $this->edition_id,
            'player_id' => $this->player_id,
            'name' => $this->name,
            'phone' => $this->phone,
            'skill_division' => $this->skill_division,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'year_of_birth' => $this->year_of_birth,
            'being_student' => $this->being_student,
            'playtomic_id' => $this->playtomic_id,
            'rankedin_id' => $this->rankedin_id,
            'instagram_username' => $this->instagram_username,
            'facebook_username' => $this->facebook_username,
            'reviewed_at' => $this->reviewed_at,
            'review_status' => $this->review_status,
            'review_message' => $this->review_message,
            'resubmitted_at' => $this->resubmitted_at,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
            'player' => $this->whenLoaded('player', function () {
                return new PlayerResource($this->player);
            }),
            'edition' => $this->whenLoaded('edition', function () {
                return new EditionResource($this->edition);
            }),
        ];
    }
}
