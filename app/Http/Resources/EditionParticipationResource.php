<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EditionParticipationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_participation' => $this->id_participation,
            'edition_id' => $this->edition_id,
            'player_id' => $this->player_id,
            'edition_division_id' => $this->edition_division_id,
            'sort_order' => $this->sort_order,
            'is_collecting_points' => $this->is_collecting_points,
            'absence_reason' => $this->absence_reason,
            'ban_reason' => $this->ban_reason,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
            'pdf_avatar_url' => array_key_exists('pdf_avatar_url', $this->resource->getAttributes())
                ? $this->pdf_avatar_url
                : null,
            'edition' => $this->whenLoaded('edition', function () {
                return new EditionResource($this->edition);
            }),
            'player' => $this->whenLoaded('player', function () {
                return new PlayerResource($this->player);
            }),
            'edition_division' => $this->whenLoaded('editionDivision', function () {
                return new EditionDivisionResource($this->editionDivision);
            }),
            'player_history' => $this->when(
                array_key_exists('player_history', $this->resource->getAttributes()),
                fn () => $this->player_history
            ),
            'flags' => $this->when(
                array_key_exists('flags', $this->resource->getAttributes()),
                fn () => $this->flags
            ),
            'custom_field_values' => $this->when(
                array_key_exists('custom_field_values', $this->resource->getAttributes()),
                fn () => $this->custom_field_values
            ),
        ];
    }
}
