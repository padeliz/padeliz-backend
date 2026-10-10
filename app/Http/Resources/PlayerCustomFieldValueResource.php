<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\PlayerCustomField\PlayerCustomFieldValuePresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayerCustomFieldValueResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_player_custom_field_value' => $this->id_player_custom_field_value,
            'player_id' => $this->player_id,
            'player_custom_field_id' => $this->player_custom_field_id,
            'submission_id' => $this->submission_id,
            'value_text' => $this->value_text,
            'value_integer' => $this->value_integer,
            'value_boolean' => $this->value_boolean,
            'value_date' => $this->value_date,
            'value_json' => $this->value_json,
            'value' => PlayerCustomFieldValuePresenter::resolve($this->resource),
            'submitted_by_player_id' => $this->submitted_by_player_id,
            'submitted_via' => $this->submitted_via,
            'created_via_import_id' => $this->created_via_import_id,
            'review_status' => $this->review_status instanceof \BackedEnum
                ? $this->review_status->value
                : $this->review_status,
            'reviewed_at' => $this->reviewed_at,
            'reviewed_by_manager_id' => $this->reviewed_by_manager_id,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
            'field' => $this->whenLoaded('field', function () {
                return new PlayerCustomFieldResource($this->field);
            }),
            'submission' => $this->whenLoaded('submission', function () {
                return $this->submission?->only([
                    'id_player_custom_field_submission',
                    'player_id',
                    'submitted_via',
                    'submitted_by_player_id',
                    'inserted_at',
                ]);
            }),
            'selected_options' => $this->whenLoaded('selectedOptions', function () {
                return PlayerCustomFieldValueOptionResource::collection($this->selectedOptions);
            }),
        ];
    }
}
