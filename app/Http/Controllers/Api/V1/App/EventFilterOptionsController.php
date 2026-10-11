<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Enums\Registration\RegistrationPaymentStatus;
use App\Http\Requests\App\EventDateRangeRequest;
use App\Models\Club;
use App\Models\EventLotteryLucky;
use App\Models\EventRegistration;
use App\Models\PadelEvent;
use App\Models\User;
use App\Services\EventCalendarTiming;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="🐥 App: Events",
 *     description="(v1) Player editions (public + optional auth filters)"
 * )
 */
class EventFilterOptionsController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/app/events/filter-options",
     *     summary="Filter chip metadata for an editions date range",
     *     tags={"🐥 App: Events"},
     *     security={},
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function index(EventDateRangeRequest $request)
    {
        $validated = $request->validated();
        $dateFrom = $validated['date_from'];
        $dateTo = $validated['date_to'];

        $inRange = static function ($q) use ($dateFrom, $dateTo): void {
            EventCalendarTiming::applyOverlapsDateRange($q, $dateFrom, $dateTo);
        };

        $clubIds = PadelEvent::query()
            ->tap($inRange)
            ->whereHas('location', fn ($q) => $q->whereNotNull('club_id'))
            ->with('location:id_location,club_id')
            ->get()
            ->pluck('location.club_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $clubs = Club::query()
            ->whereIn('id_club', $clubIds)
            ->orderBy('name')
            ->get(['id_club', 'name'])
            ->map(fn (Club $club) => [
                'id_club' => (int) $club->id_club,
                'name' => (string) $club->name,
            ])
            ->values()
            ->all();

        $player = Auth::guard('sanctum')->user();
        $unpaidCount = 0;
        $hasLotteryWinner = false;

        if ($player instanceof User) {
            $playerId = (int) $player->id_user;

            $unpaidCount = EventRegistration::query()
                ->active()
                ->where('player_user_id', $playerId)
                ->whereHas('event', $inRange)
                ->where(function ($inner) {
                    $inner->where('payment_status', RegistrationPaymentStatus::Unpaid->value)
                        ->orWhere('payment_status', RegistrationPaymentStatus::AwaitingProofReview->value)
                        ->orWhere(function ($overdue) {
                            $overdue->whereNotNull('payment_due_at')
                                ->where('payment_due_at', '<=', time())
                                ->where('payment_status', '!=', RegistrationPaymentStatus::Paid->value);
                        });
                })
                ->count();

            $hasLotteryWinner = EventLotteryLucky::query()
                ->whereNotNull('accepted_at')
                ->whereHas('drawnParticipation', function ($q) use ($playerId, $inRange) {
                    $q->where('player_user_id', $playerId)
                        ->whereHas('event', $inRange);
                })
                ->exists();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'clubs' => $clubs,
                'unpaid_count' => $unpaidCount,
                'has_lottery_winner' => $hasLotteryWinner,
            ],
        ]);
    }
}
