<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SponsorResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_sponsor' => $this->id_sponsor,
            'name' => $this->name,
            'club_id' => $this->club_id,
            'player_user_ids' => $this->whenLoaded('players', function () {
                return $this->players->pluck('id_user')->values()->all();
            }),
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
            'club' => $this->whenLoaded('club', function () {
                return $this->club ? new ClubResource($this->club) : null;
            }),
            'players' => $this->whenLoaded('players', function () {
                return UserSummaryResource::collection($this->players);
            }),
            'sponsor_prizes' => $this->whenLoaded('sponsorPrizes', function () {
                return SponsorPrizeResource::collection($this->sponsorPrizes);
            }),
        ];
    }
}
