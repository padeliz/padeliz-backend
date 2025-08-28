<?php

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
            'locations' => $this->whenLoaded('locations', function () {
                return LocationResource::collection($this->locations);
            }),
        ];
    }
}
