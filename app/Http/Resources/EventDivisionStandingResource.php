<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventDivisionStandingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_standing' => $this->id_standing,
            'event_division_id' => $this->event_division_id,
            'participation_id' => $this->participation_id,
            'points_total' => $this->points_total,
            'wins' => $this->wins,
            'rank' => $this->rank,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
