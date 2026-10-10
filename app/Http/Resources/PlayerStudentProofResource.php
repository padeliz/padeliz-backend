<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlayerStudentProofResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $studentUntil = $this->student_until;

        return [
            'id_player_student_proof' => $this->id_player_student_proof,
            'player_id' => $this->player_id,
            'student_until' => $studentUntil instanceof \DateTimeInterface
                ? $studentUntil->format('Y-m-d')
                : $studentUntil,
            'sort_order' => $this->sort_order,
            'ready_for_review' => (bool) $this->ready_for_review,
            'review_status' => $this->review_status?->value,
            'review_message' => $this->review_message,
            'submitted_at' => $this->submitted_at,
            'reviewed_at' => $this->reviewed_at,
            'files' => $this->whenLoaded('files', fn () => PlayerStudentProofFileResource::collection($this->files)->resolve()),
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
            'player' => $this->whenLoaded('player', fn () => new PlayerResource($this->player)),
        ];
    }
}
