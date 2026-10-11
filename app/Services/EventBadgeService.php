<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PlayerLevel\AssessmentReviewStatus;
use App\Enums\Registration\RegistrationPaymentStatus;
use App\Models\EventLotteryLucky;
use App\Models\EventRegistration;
use App\Models\PadelEvent;
use App\Models\User;
use App\Models\PlayerLevelAssessment;
use Illuminate\Support\Collection;

/**
 * Resolves ordered edition card badges (player-relative + edition-relative).
 *
 * @phpstan-type Badge array{key: string, label: string}
 */
final class EventBadgeService
{
    public const KEY_PENDING_PAYMENT = 'pending_payment';

    public const KEY_REGISTRATION_PENDING = 'registration_pending';

    public const KEY_LEVEL_ASSESSMENT_PENDING = 'level_assessment_pending';

    public const KEY_REGISTERED = 'registered';

    public const KEY_PARTICIPATED = 'participated';

    public const KEY_LOTTERY_WINNER = 'lottery_winner';

    public const KEY_NEW = 'new';

    public const KEY_FEW_SPOTS = 'few_spots_remained';

    /**
     * @param  Collection<int, PadelEvent>  $editions
     * @return array<int, list<Badge>>
     */
    public function forEvents(Collection $editions, ?User $player, ?int $now = null): array
    {
        $now = $now ?? time();

        $editionIds = $editions->pluck('id_event')->map(fn ($id) => (int) $id)->all();
        if ($editionIds === []) {
            return [];
        }

        $regsByEdition = [];
        $levelAssessmentPending = false;
        $lotteryEditionIds = [];

        if ($player !== null) {
            $regsByEdition = $this->loadActiveRegistrationsByEdition((int) $player->id_user, $editionIds);
            $levelAssessmentPending = $this->hasPendingLevelAssessmentInReview((int) $player->id_user);
            $lotteryEditionIds = $this->loadAcceptedLotteryEditionIds((int) $player->id_user, $editionIds);
        }

        $spotsByEdition = $this->loadSpotsByEdition($editions);

        $result = [];
        foreach ($editions as $event) {
            $id = (int) $event->id_event;
            $timing = EventCalendarTiming::classify(
                (int) $event->event_starts_at,
                (int) $event->event_ends_at,
                $now
            );

            $badges = [];

            if ($player !== null) {
                $pathBadge = $this->registrationPathBadge(
                    $regsByEdition[$id] ?? [],
                    $timing,
                    $levelAssessmentPending,
                    $now
                );
                if ($pathBadge !== null) {
                    $badges[] = $pathBadge;
                }

                if (isset($lotteryEditionIds[$id])) {
                    $badges[] = $this->badge(self::KEY_LOTTERY_WINNER, 'Lottery Winner');
                }
            }

            if ($timing === 'upcoming' && $this->registrationWindowOpen($event->registration_allowed_until, $now)) {
                if ($this->isNewEdition((int) ($event->inserted_at ?? 0), $now)) {
                    $badges[] = $this->badge(self::KEY_NEW, 'New');
                }

                $spots = $spotsByEdition[$id] ?? ['total' => 0, 'filled' => 0];
                if ($this->hasFewSpotsRemaining($spots['total'], $spots['filled'])) {
                    $badges[] = $this->badge(self::KEY_FEW_SPOTS, 'Few Spots Remained');
                }
            }

            $result[$id] = $badges;
        }

        return $result;
    }

    /**
     * @param  list<EventRegistration>  $regs
     * @return null|Badge
     */
    private function registrationPathBadge(array $regs, string $timing, bool $levelAssessmentPending, int $now): ?array
    {
        $active = array_values(array_filter(
            $regs,
            static fn (EventRegistration $r) => $r->lifecycle_status?->value === 'active'
                || (string) $r->lifecycle_status === 'active'
        ));

        if ($active !== []) {
            foreach ($active as $reg) {
                $status = $reg->payment_status instanceof RegistrationPaymentStatus
                    ? $reg->payment_status
                    : RegistrationPaymentStatus::tryFrom((string) $reg->payment_status);

                if ($status === RegistrationPaymentStatus::Unpaid && $reg->amountDue() > 0) {
                    return $this->badge(self::KEY_PENDING_PAYMENT, 'Pending Payment');
                }
                if ($reg->isPaymentOverdue($now)) {
                    return $this->badge(self::KEY_PENDING_PAYMENT, 'Pending Payment');
                }
            }

            foreach ($active as $reg) {
                $status = $reg->payment_status instanceof RegistrationPaymentStatus
                    ? $reg->payment_status
                    : RegistrationPaymentStatus::tryFrom((string) $reg->payment_status);
                if ($status === RegistrationPaymentStatus::AwaitingProofReview) {
                    if ($timing === 'past') {
                        return null;
                    }

                    return $this->badge(self::KEY_REGISTRATION_PENDING, 'Registration Pending');
                }
            }

            foreach ($active as $reg) {
                $status = $reg->payment_status instanceof RegistrationPaymentStatus
                    ? $reg->payment_status
                    : RegistrationPaymentStatus::tryFrom((string) $reg->payment_status);
                if ($status === RegistrationPaymentStatus::Paid) {
                    if ($timing === 'past') {
                        return $this->badge(self::KEY_PARTICIPATED, 'Participated');
                    }

                    return $this->badge(self::KEY_REGISTERED, 'Registered');
                }
            }
        }

        if ($levelAssessmentPending && $timing !== 'past') {
            return $this->badge(self::KEY_LEVEL_ASSESSMENT_PENDING, 'Level Assessment Pending');
        }

        return null;
    }

    /**
     * @param  list<int>  $editionIds
     * @return array<int, list<EventRegistration>>
     */
    private function loadActiveRegistrationsByEdition(int $playerId, array $editionIds): array
    {
        $rows = EventRegistration::query()
            ->active()
            ->where('player_user_id', $playerId)
            ->whereIn('event_id', $editionIds)
            ->get();

        $byEdition = [];
        foreach ($rows as $row) {
            $byEdition[(int) $row->event_id][] = $row;
        }

        return $byEdition;
    }

    /**
     * A pending assessment already handed over for review applies to the player, not to any single edition.
     */
    private function hasPendingLevelAssessmentInReview(int $playerId): bool
    {
        return PlayerLevelAssessment::query()
            ->where('player_user_id', $playerId)
            ->where('review_status', AssessmentReviewStatus::Pending->value)
            ->where('ready_for_review', true)
            ->exists();
    }

    /**
     * @param  list<int>  $editionIds
     * @return array<int, true>
     */
    private function loadAcceptedLotteryEditionIds(int $playerId, array $editionIds): array
    {
        $ids = EventLotteryLucky::query()
            ->whereNotNull('accepted_at')
            ->whereHas('drawnParticipation', function ($q) use ($playerId, $editionIds) {
                $q->where('player_user_id', $playerId)->whereIn('event_id', $editionIds);
            })
            ->with('drawnParticipation:id_participation,event_id')
            ->get()
            ->map(fn (EventLotteryLucky $lucky) => (int) $lucky->drawnParticipation?->event_id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->all();

        return array_fill_keys($ids, true);
    }

    /**
     * @param  Collection<int, PadelEvent>  $editions
     * @return array<int, array{total: int, filled: int}>
     */
    private function loadSpotsByEdition(Collection $editions): array
    {
        $result = [];
        foreach ($editions as $event) {
            $id = (int) $event->id_event;
            $divisions = $event->relationLoaded('divisions')
                ? $event->divisions
                : $event->divisions()->get();

            $total = 0;
            foreach ($divisions as $division) {
                $total += (int) ($division->players_limit ?? 0);
            }

            $filled = $event->relationLoaded('participations')
                ? $event->participations->count()
                : (int) $event->participations()->count();

            if (isset($event->participations_count)) {
                $filled = (int) $event->participations_count;
            }

            $result[$id] = ['total' => $total, 'filled' => $filled];
        }

        return $result;
    }

    private function registrationWindowOpen(mixed $registrationAllowedUntil, int $now): bool
    {
        if ($registrationAllowedUntil === null || $registrationAllowedUntil === '') {
            return false;
        }

        return (int) $registrationAllowedUntil > $now;
    }

    private function isNewEdition(int $insertedAt, int $now): bool
    {
        if ($insertedAt <= 0) {
            return false;
        }

        return ($now - $insertedAt) <= 48 * 3600;
    }

    private function hasFewSpotsRemaining(int $spotsTotal, int $spotsFilled): bool
    {
        if ($spotsTotal <= 0) {
            return false;
        }

        $remaining = max(0, $spotsTotal - $spotsFilled);

        return ($remaining / $spotsTotal) <= 0.25;
    }

    /**
     * @return Badge
     */
    private function badge(string $key, string $label): array
    {
        return ['key' => $key, 'label' => $label];
    }
}
