<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Registration\RegistrationPaymentStatus;
use App\Models\PadelEvent;
use App\Models\EventLotteryLucky;
use App\Models\EventRegistration;
use App\Models\Player;
use Illuminate\Database\Eloquent\Builder;

final class EventAppQueryService
{
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
     *     club_ids?: list<int>
     * }  $filters
     * @return Builder<PadelEvent>
     */
    public function baseQuery(array $filters): Builder
    {
        $query = PadelEvent::query()
            ->with(['location.club', 'divisions.division', 'organizer'])
            ->withCount('participations')
            ->whereDate('date', '>=', $filters['date_from'])
            ->whereDate('date', '<=', $filters['date_to'])
            ->orderBy('date', 'asc');

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
    public function applyPlayerFilters(Builder $query, array $filters, Player $player): Builder
    {
        $playerId = (int) $player->id_player;

        if ($filters['participated'] ?? false) {
            $query->whereHas('participations', function ($q) use ($playerId) {
                $q->where('player_id', $playerId);
            });
        }

        if ($filters['registered'] ?? false) {
            $query->whereHas('registrations', function ($q) use ($playerId) {
                $q->active()->where('player_id', $playerId);
            });
        }

        if ($filters['unpaid'] ?? false) {
            $query->whereHas('registrations', function ($q) use ($playerId) {
                $q->active()
                    ->where('player_id', $playerId)
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
                $q->where('player_id', $playerId)
                    ->whereIn('id_participation', EventLotteryLucky::query()
                        ->whereNotNull('accepted_at')
                        ->select('drawn_participation_id'));
            });
        }

        return $query;
    }
}
