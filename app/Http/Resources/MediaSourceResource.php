<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MediaSourceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_media_source' => $this->id_media_source,
            'code' => $this->code,
            'label' => $this->label,
            'sort_order' => $this->sort_order,
        ];
    }
}
