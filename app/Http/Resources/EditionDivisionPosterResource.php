<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EditionDivisionPosterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_edition_division_poster' => $this->id_edition_division_poster,
            'edition_division_id' => $this->edition_division_id,
            'edition_division_court_id' => $this->edition_division_court_id,
            'poster_kind' => $this->poster_kind,
            'generated_path' => $this->generated_path,
            'printed_path' => $this->printed_path,
            'generated_url' => $this->generated_url,
            'printed_url' => $this->printed_url,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
