<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EditionDivisionCourtResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_edition_division_court' => $this->id_edition_division_court,
            'edition_division_id' => $this->edition_division_id,
            'court_id' => $this->court_id,
            'display_order' => $this->display_order,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
            'court' => $this->whenLoaded('court', function () {
                return new CourtResource($this->court);
            }),
            'edition_division' => $this->whenLoaded('editionDivision', function () {
                return new EditionDivisionResource($this->editionDivision);
            }),
        ];
    }
}
