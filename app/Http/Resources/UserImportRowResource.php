<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="UserImportRowResource",
 *     @OA\Property(property="id_user_import_row", type="integer", format="int64"),
 *     @OA\Property(property="user_import_id", type="integer", format="int64"),
 *     @OA\Property(property="row_index", type="integer"),
 *     @OA\Property(property="first_name", type="string", nullable=true),
 *     @OA\Property(property="middle_name", type="string", nullable=true),
 *     @OA\Property(property="last_name", type="string", nullable=true),
 *     @OA\Property(property="email", type="string", nullable=true),
 *     @OA\Property(property="phone", type="string", nullable=true),
 *     @OA\Property(property="user_id", type="integer", format="int64", nullable=true),
 *     @OA\Property(property="action", type="string"),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true)
 * )
 */
class UserImportRowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_user_import_row' => $this->id_user_import_row,
            'user_import_id' => $this->user_import_id,
            'row_index' => $this->row_index,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'user_id' => $this->user_id,
            'action' => $this->action,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
