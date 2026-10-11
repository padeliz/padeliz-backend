<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="UserImportResource",
 *     @OA\Property(property="id_user_import", type="integer", format="int64"),
 *     @OA\Property(property="imported_by_manager_user_id", type="integer", format="int64", nullable=true),
 *     @OA\Property(property="format", type="string"),
 *     @OA\Property(property="original_filename", type="string"),
 *     @OA\Property(property="included_count", type="integer"),
 *     @OA\Property(property="created_count", type="integer"),
 *     @OA\Property(property="attached_count", type="integer"),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true)
 * )
 */
class UserImportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_user_import' => $this->id_user_import,
            'imported_by_manager_user_id' => $this->imported_by_manager_user_id,
            'format' => $this->format,
            'original_filename' => $this->original_filename,
            'included_count' => $this->included_count,
            'created_count' => $this->created_count,
            'attached_count' => $this->attached_count,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
            'importedByManager' => $this->whenLoaded('importedByManager', function () {
                return $this->importedByManager
                    ? new UserSummaryResource($this->importedByManager)
                    : null;
            }),
            'rows' => $this->whenLoaded('rows', function () {
                return UserImportRowResource::collection($this->rows);
            }),
        ];
    }
}
