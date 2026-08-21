<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClubResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_club' => $this->id_club,
            'name' => $this->name,
            'city' => $this->city,
            'logo_path' => $this->logo_path,
            'url' => $this->url,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
            'locations' => $this->whenLoaded('locations', function () {
                return LocationResource::collection($this->locations);
            }),
            'sponsors' => $this->whenLoaded('sponsors', function () {
                return SponsorResource::collection($this->sponsors);
            }),
        ];
    }
}
