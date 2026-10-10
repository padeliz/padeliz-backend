<?php

declare(strict_types=1);

namespace App\Services\Push;

use App\Models\PlayerDeviceToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExpoPushService
{
    public function sendToPlayer(int $playerId, string $title, string $body, array $data = []): void
    {
        $tokens = PlayerDeviceToken::query()
            ->where('player_id', $playerId)
            ->pluck('token')
            ->filter()
            ->values()
            ->all();

        if ($tokens === []) {
            return;
        }

        $messages = array_map(static fn (string $token): array => [
            'to' => $token,
            'sound' => 'default',
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ], $tokens);

        try {
            Http::withHeaders(['Content-Type' => 'application/json'])
                ->post('https://exp.host/--/api/v2/push/send', $messages);
        } catch (\Throwable $e) {
            Log::warning('Expo push send failed', [
                'player_id' => $playerId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function notifyLevelAssessmentStatus(int $playerId, string $reviewStatus): void
    {
        $this->sendToPlayer(
            $playerId,
            'Level assessment update',
            'Your level assessment status is now: ' . $reviewStatus,
            ['type' => 'level_assessment', 'review_status' => $reviewStatus]
        );
    }

    public function notifyRegistrationStatus(int $playerId, string $event, array $data = []): void
    {
        $this->sendToPlayer(
            $playerId,
            'Registration update',
            'Your registration status changed: ' . $event,
            array_merge(['type' => 'registration', 'event' => $event], $data)
        );
    }
}
