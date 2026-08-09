<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PlayerProfileImage;
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
            'skill_division' => $this->skill_division,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'year_of_birth' => $this->year_of_birth,
            'being_student' => $this->being_student,
            'playtomic_id' => $this->playtomic_id,
            'rankedin_id' => $this->rankedin_id,
            'user_types' => $this->user_types,
            'instagram_username' => $this->instagram_username,
            'facebook_username' => $this->facebook_username,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
            'emails' => $this->whenLoaded('emails', function () {
                return $this->emails->map(static fn ($email) => [
                    'id_player_email' => $email->id_player_email,
                    'email' => $email->email,
                    'purposes' => $email->purposes,
                    'is_primary' => $email->is_primary,
                    'verified_at' => $email->verified_at,
                ])->values()->all();
            }),
            'phones' => $this->whenLoaded('phones', function () {
                return $this->phones->map(static fn ($phone) => [
                    'id_player_phone' => $phone->id_player_phone,
                    'phone' => $phone->phone,
                    'purposes' => $phone->purposes,
                    'is_primary' => $phone->is_primary,
                    'verified_at' => $phone->verified_at,
                ])->values()->all();
            }),
            'uploaded_profile_images' => $this->profileImages
                ->map(static fn (PlayerProfileImage $img) => [
                    'id' => $img->id,
                    'url' => $img->url,
                ])
                ->values()
                ->all(),
            'participations' => $this->whenLoaded('participations', function () {
                return EditionParticipationResource::collection($this->participations);
            }),
        ];
    }
}
