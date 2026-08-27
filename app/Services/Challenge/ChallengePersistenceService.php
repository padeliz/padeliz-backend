<?php

declare(strict_types=1);

namespace App\Services\Challenge;

use App\Enums\Challenge\ChallengeSessionStatus;
use App\Enums\Challenge\ChallengeSessionType;
use App\Models\Challenge\ChallengeSession;
use Illuminate\Support\Facades\DB;

final class ChallengePersistenceService
{
    /**
     * Full-row upsert by id. Inserts when the row is missing (e.g. deleted locally resurrected).
     *
     * @param array<string, mixed> $payload
     * @return array{session: ChallengeSession, state_sequence: int}
     */
    public function sync(int $sessionId, array $payload): array
    {
        return DB::transaction(function () use ($sessionId, $payload) {
            $bodyId = (int) $payload['id_challenge_session'];
            if ($bodyId !== $sessionId) {
                throw new ChallengeStateException(
                    'id_challenge_session must match the path id.',
                    422,
                );
            }

            $type = ChallengeSessionType::tryFrom((string) $payload['type']);
            if ($type === null) {
                throw new ChallengeStateException('Invalid challenge type.', 422);
            }

            $status = ChallengeSessionStatus::tryFrom((string) $payload['status']);
            if ($status === null) {
                throw new ChallengeStateException('Invalid status.', 422);
            }

            $title = trim((string) $payload['title']);
            if ($title === '') {
                throw new ChallengeStateException('Title is required.', 422);
            }
            $titleLength = mb_strlen($title);
            if ($titleLength < 20 || $titleLength > 50) {
                throw new ChallengeStateException('Title must be between 20 and 50 characters.', 422);
            }

            $bottomText = trim((string) $payload['bottom_text']);
            if ($bottomText === '') {
                throw new ChallengeStateException('Bottom text is required.', 422);
            }
            $bottomTextLength = mb_strlen($bottomText);
            if ($bottomTextLength < 20 || $bottomTextLength > 45) {
                throw new ChallengeStateException('Bottom text must be between 20 and 45 characters.', 422);
            }

            $insertedAt = (int) $payload['inserted_at'];
            $startedAt = (int) $payload['started_at'];
            $stateSequence = (int) $payload['state_sequence'];
            /** @var array<string, mixed> $stateJson */
            $stateJson = $payload['state_json'];
            $completedAt = array_key_exists('completed_at', $payload) && $payload['completed_at'] !== null
                ? (int) $payload['completed_at']
                : null;

            if ($status === ChallengeSessionStatus::Active) {
                $completedAt = null;
            } elseif ($status === ChallengeSessionStatus::Completed && $completedAt === null) {
                $completedAt = time();
            }

            /** @var ChallengeSession|null $locked */
            $locked = ChallengeSession::query()
                ->whereKey($sessionId)
                ->lockForUpdate()
                ->first();

            if ($locked !== null) {
                if ($locked->type !== $type) {
                    throw new ChallengeStateException('Challenge type cannot be changed.', 422);
                }

                if (
                    $status === ChallengeSessionStatus::Active
                    && $locked->status !== ChallengeSessionStatus::Active
                    && $this->hasOtherActiveSession($sessionId, $type)
                ) {
                    throw new ChallengeStateException('An active session of this type already exists.');
                }

                $locked->status = $status;
                $locked->title = $title;
                $locked->bottom_text = $bottomText;
                $locked->started_at = $startedAt;
                $locked->completed_at = $completedAt;
                $locked->state_json = $stateJson;
                $locked->state_sequence = $stateSequence;
                $locked->inserted_at = $insertedAt;
                $locked->updated_at = time();
                $locked->save();

                return [
                    'session' => $locked->fresh() ?? $locked,
                    'state_sequence' => (int) $locked->state_sequence,
                ];
            }

            if (
                $status === ChallengeSessionStatus::Active
                && $this->hasOtherActiveSession($sessionId, $type)
            ) {
                throw new ChallengeStateException('An active session of this type already exists.');
            }

            $created = new ChallengeSession();
            $created->timestamps = false;
            $created->id_challenge_session = $sessionId;
            $created->type = $type;
            $created->status = $status;
            $created->title = $title;
            $created->bottom_text = $bottomText;
            $created->started_at = $startedAt;
            $created->completed_at = $completedAt;
            $created->state_json = $stateJson;
            $created->state_sequence = $stateSequence;
            $created->inserted_at = $insertedAt;
            $created->updated_at = time();
            $created->save();

            return [
                'session' => $created->fresh() ?? $created,
                'state_sequence' => (int) $created->state_sequence,
            ];
        });
    }

    private function hasOtherActiveSession(int $sessionId, ChallengeSessionType $type): bool
    {
        return ChallengeSession::query()
            ->where('type', $type)
            ->where('status', ChallengeSessionStatus::Active)
            ->where('id_challenge_session', '!=', $sessionId)
            ->exists();
    }
}
