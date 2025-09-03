<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_location' => $this->id_location,
            'club_id' => $this->club_id,
            'name' => $this->name,
            'address' => $this->address,
            'total_indoors_courts' => $this->total_indoors_courts,
            'total_outdoor_courts' => $this->total_outdoor_courts,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
            'club' => $this->whenLoaded('club', function () {
                return new ClubResource($this->club);
            }),
        ];
    }
}
