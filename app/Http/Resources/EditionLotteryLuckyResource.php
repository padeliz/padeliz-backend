<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CachedPdfPlayerImage;
use App\Services\Pdf\PdfHtmlHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EditionLotteryLuckyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $participation = null;
        if ($this->relationLoaded('drawnParticipation')) {
            $participation = $this->drawnParticipation;
        } elseif ($this->relationLoaded('participation')) {
            $participation = $this->participation;
        }

        $player = $participation?->player;
        $name = $player !== null ? (string) $player->name : '';
        $collecting = (bool) ($participation?->is_collecting_points ?? false);
        $pdfAvatarUrl = null;
        if ($player !== null) {
            $cached = CachedPdfPlayerImage::query()
                ->where('player_id', (int) $player->id_player)
                ->where('player_is_collecting_points', $collecting)
                ->first();
            $pdfAvatarUrl = $cached?->url;
        }

        return [
            'id_lucky' => $this->id_lucky,
            'lottery_rule_id' => $this->lottery_rule_id,
            'drawn_participation_id' => $this->drawn_participation_id,
            'drawn_at' => $this->drawn_at,
            'available_at' => $this->available_at,
            'announced_at' => $this->announced_at,
            'accepted_at' => $this->accepted_at,
            'rejected_at' => $this->rejected_at,
            'inserted_at' => $this->inserted_at,
            'updated_at' => $this->updated_at,
            'short_name' => $name !== '' ? PdfHtmlHelper::shortenPlayerName($name) : null,
            'pdf_avatar_url' => $pdfAvatarUrl,
            'lottery_rule' => $this->whenLoaded('lotteryRule', function () {
                return new EditionLotteryRuleResource($this->lotteryRule);
            }),
            'drawn_participation' => $this->when(
                $this->relationLoaded('drawnParticipation') || $this->relationLoaded('participation'),
                function () use ($participation) {
                    return new EditionParticipationResource($participation);
                }
            ),
        ];
    }
}
