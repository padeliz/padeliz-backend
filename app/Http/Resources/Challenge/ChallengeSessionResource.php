<?php

declare(strict_types=1);

namespace App\Http\Resources\Challenge;

use App\Models\Challenge\ChallengeSession;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ChallengeSession */
class ChallengeSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id_challenge_session' => $this->id_challenge_session,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'title' => $this->title,
            'bottom_text' => $this->bottom_text,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'state_sequence' => (int) $this->state_sequence,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
