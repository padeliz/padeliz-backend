<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_location' => $this->id_location,
            'name' => $this->name,
            'address' => $this->address,
            'city' => $this->city,
            'club_id' => $this->club_id,
            'total_indoors_courts' => $this->total_indoors_courts,
            'total_outdoor_courts' => $this->total_outdoor_courts,
        ];
    }
}
