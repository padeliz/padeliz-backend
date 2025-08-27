<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DivisionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_division' => $this->id_division,
            'name' => $this->name,
            'description' => $this->description,
            'color' => $this->color,
            'is_students_only' => $this->is_students_only,
        ];
    }
}
