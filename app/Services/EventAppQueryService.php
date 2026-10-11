<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Registration\RegistrationPaymentStatus;
use App\Models\EventLotteryLucky;
use App\Models\PadelEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class EventAppQueryService
{
    public const SORT_ASC = 'asc';

    public const SORT_DESC = 'desc';

    /**
     * Explicit list ordering chosen by the App timing tab:
     * Past → event_starts_at DESC; Ongoing/Upcoming → ASC (default).
     *
     * @param  Builder<PadelEvent>  $query
     */
    public function applySort(Builder $query, string $sort): void
    {
        $direction = $sort === self::SORT_DESC ? 'desc' : 'asc';

        $query->orderBy('event_starts_at', $direction)
            ->orderBy('id_event', $direction);
    }

    /**
     * @param  array{
     *     date_from: string,
     *     date_to: string,
     *     participated?: bool,
     *     registered?: bool,
     *     unpaid?: bool,
     *     lottery_winner?: bool,
     *     club_ids?: list<int>
     * }  $filters
     */
    public function requiresPlayerAuth(array $filters): bool
    {
        return ($filters['participated'] ?? false)
            || ($filters['registered'] ?? false)
            || ($filters['unpaid'] ?? false)
            || ($filters['lottery_winner'] ?? false);
    }

    /**
     * @param  array{
     *     date_from: string,
     *     date_to: string,
     *     participated?: bool,
     *     registered?: bool,
     *     unpaid?: bool,
     *     lottery_winner?: bool,
     *     club_ids?: list<int>,
     *     sort?: string
     * }  $filters
     * @return Builder<PadelEvent>
     */
    public function baseQuery(array $filters): Builder
    {
        $query = PadelEvent::query()
            ->with(['location.club', 'divisions.division', 'organizer', 'coverImages'])
            ->withCount('participations');

        EventCalendarTiming::applyOverlapsDateRange(
            $query,
            $filters['date_from'],
            $filters['date_to']
        );

        $this->applySort($query, $filters['sort'] ?? self::SORT_ASC);

        if (! empty($filters['club_ids'])) {
            $clubIds = array_map('intval', $filters['club_ids']);
            $query->whereHas('location', function ($q) use ($clubIds) {
                $q->whereIn('club_id', $clubIds);
            });
        }

        return $query;
    }

    /**
     * @param  Builder<PadelEvent>  $query
     * @param  array{
     *     participated?: bool,
     *     registered?: bool,
     *     unpaid?: bool,
     *     lottery_winner?: bool
     * }  $filters
     * @return Builder<PadelEvent>
     */
    public function applyPlayerFilters(Builder $query, array $filters, User $player): Builder
    {
        $playerId = (int) $player->id_user;

        if ($filters['participated'] ?? false) {
            $query->whereHas('participations', function ($q) use ($playerId) {
                $q->where('player_user_id', $playerId);
            });
        }

        if ($filters['registered'] ?? false) {
            $query->whereHas('registrations', function ($q) use ($playerId) {
                $q->active()->where('player_user_id', $playerId);
            });
        }

        if ($filters['unpaid'] ?? false) {
            $query->whereHas('registrations', function ($q) use ($playerId) {
                $q->active()
                    ->where('player_user_id', $playerId)
                    ->where(function ($inner) {
                        $inner->where('payment_status', RegistrationPaymentStatus::Unpaid->value)
                            ->orWhere('payment_status', RegistrationPaymentStatus::AwaitingProofReview->value)
                            ->orWhere(function ($overdue) {
                                $overdue->whereNotNull('payment_due_at')
                                    ->where('payment_due_at', '<=', time())
                                    ->where('payment_status', '!=', RegistrationPaymentStatus::Paid->value);
                            });
                    });
            });
        }

        if ($filters['lottery_winner'] ?? false) {
            $query->whereHas('participations', function ($q) use ($playerId) {
                $q->where('player_user_id', $playerId)
                    ->whereIn('id_participation', EventLotteryLucky::query()
                        ->whereNotNull('accepted_at')
                        ->select('drawn_participation_id'));
            });
        }

        return $query;
    }
}
