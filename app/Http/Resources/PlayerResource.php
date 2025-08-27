<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_player' => $this->id_player,
            'name' => $this->name,
            'phone' => $this->phone,
            'google_id' => $this->google_id,
            'google_name' => $this->google_name,
            'google_email' => $this->google_email,
            'google_avatar' => $this->google_avatar,
            'skill_division' => $this->skill_division,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'year_of_birth' => $this->year_of_birth,
            'being_student' => $this->being_student,
            'playtomic_id' => $this->playtomic_id,
            'rankedin_id' => $this->rankedin_id,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
            'participations' => EditionParticipationResource::collection($this->whenLoaded('participations')),
        ];
    }
}


