<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrizeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_prize' => $this->id_prize,
            'name' => $this->name,
            'image' => $this->image,
        ];
    }
}
