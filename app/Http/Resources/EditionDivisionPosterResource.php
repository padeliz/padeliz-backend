<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class EditionDivisionPosterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $disk = config('filesystems.user_uploads_disk', 'public');

        return [
            'id_edition_division_poster' => $this->id_edition_division_poster,
            'edition_division_id' => $this->edition_division_id,
            'poster_kind' => $this->poster_kind,
            'filename' => $this->filename,
            'url' => $this->filename
                ? Storage::disk($disk)->url($this->filename)
                : null,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
