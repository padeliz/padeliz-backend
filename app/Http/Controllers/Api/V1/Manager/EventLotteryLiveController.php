<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\Controller;
use App\Models\EventLottery;
use App\Models\EventLotteryLucky;
use App\Services\Cache\ScheduleReadCache;
use App\Services\Lottery\LotteryDrawService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * @OA\Tag(
 *     name="🐧 Manager: PadelEvent Lottery Live",
 *     description="(v1) Live lottery draw / announce / accept / reject / prizes board"
 * )
 */
class EventLotteryLiveController extends Controller
{
    public function __construct(
        private readonly LotteryDrawService $lotteryDrawService,
        private readonly ScheduleReadCache $scheduleReadCache,
    ) {
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/event-lotteries/{lottery}/live",
     *     summary="Current lottery live state for the edition",
     *     tags={"🐧 Manager: PadelEvent Lottery Live"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Live state")
     * )
     */
    public function live(EventLottery $lottery): JsonResponse
    {
        try {
            $state = $this->lotteryDrawService->liveState($lottery);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $state]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/event-lotteries/{lottery}/draw",
     *     summary="Ensure / create the next pending draw when appropriate",
     *     tags={"🐧 Manager: PadelEvent Lottery Live"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Pending lucky")
     * )
     */
    public function draw(EventLottery $lottery): JsonResponse
    {
        if ($response = $this->finalizeLockResponse($lottery)) {
            return $response;
        }

        try {
            $lucky = $this->lotteryDrawService->drawNext($lottery);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->lotteryDrawService->liveState($lottery)]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/event-lotteries/{lottery}/announce",
     *     summary="Announce the current lucky",
     *     tags={"🐧 Manager: PadelEvent Lottery Live"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(required={"id_lucky"}, @OA\Property(property="id_lucky", type="integer"))
     *     ),
     *     @OA\Response(response=200, description="Announced")
     * )
     */
    public function announce(Request $request, EventLottery $lottery): JsonResponse
    {
        if ($response = $this->finalizeLockResponse($lottery)) {
            return $response;
        }

        $validated = $request->validate([
            'id_lucky' => ['required', 'integer', 'exists:event_lottery_luckies,id_lucky'],
        ]);

        $lucky = $this->findLuckyForLotteryEdition((int) $validated['id_lucky'], $lottery);
        if ($lucky === null) {
            return response()->json(['message' => 'Lucky does not belong to this lottery.'], 404);
        }

        $this->lotteryDrawService->announce($lucky);

        return response()->json(['data' => $this->lotteryDrawService->liveState($lottery)]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/event-lotteries/{lottery}/accept",
     *     summary="Accept current lucky and advance",
     *     tags={"🐧 Manager: PadelEvent Lottery Live"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(required={"id_lucky"}, @OA\Property(property="id_lucky", type="integer"))
     *     ),
     *     @OA\Response(response=200, description="Accepted")
     * )
     */
    public function accept(Request $request, EventLottery $lottery): JsonResponse
    {
        if ($response = $this->finalizeLockResponse($lottery)) {
            return $response;
        }

        $validated = $request->validate([
            'id_lucky' => ['required', 'integer', 'exists:event_lottery_luckies,id_lucky'],
        ]);

        $lucky = $this->findLuckyForLotteryEdition((int) $validated['id_lucky'], $lottery);
        if ($lucky === null) {
            return response()->json(['message' => 'Lucky does not belong to this lottery.'], 404);
        }

        $event = $lottery->event()->firstOrFail();
        $this->lotteryDrawService->accept($lucky, $event);

        return response()->json(['data' => $this->lotteryDrawService->liveState($lottery)]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/event-lotteries/{lottery}/reject",
     *     summary="Reject current lucky and redraw (+10s)",
     *     tags={"🐧 Manager: PadelEvent Lottery Live"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(required={"id_lucky"}, @OA\Property(property="id_lucky", type="integer"))
     *     ),
     *     @OA\Response(response=200, description="Rejected")
     * )
     */
    public function reject(Request $request, EventLottery $lottery): JsonResponse
    {
        if ($response = $this->finalizeLockResponse($lottery)) {
            return $response;
        }

        $validated = $request->validate([
            'id_lucky' => ['required', 'integer', 'exists:event_lottery_luckies,id_lucky'],
        ]);

        $lucky = $this->findLuckyForLotteryEdition((int) $validated['id_lucky'], $lottery);
        if ($lucky === null) {
            return response()->json(['message' => 'Lucky does not belong to this lottery.'], 404);
        }

        $event = $lottery->event()->firstOrFail();
        $this->lotteryDrawService->reject($lucky, $event);

        return response()->json(['data' => $this->lotteryDrawService->liveState($lottery)]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/event-lotteries/{lottery}/prizes-board",
     *     summary="Prizes board payload (image + mp4 aware)",
     *     tags={"🐧 Manager: PadelEvent Lottery Live"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Prizes board intervals")
     * )
     */
    public function prizesBoard(EventLottery $lottery): JsonResponse
    {
        $data = $this->scheduleReadCache->rememberPrizesBoard(
            (int) $lottery->id_lottery,
            fn () => $this->lotteryDrawService->prizesBoard($lottery),
        );

        return response()->json(['data' => $data]);
    }

    private function findLuckyForLotteryEdition(int $luckyId, EventLottery $lottery): ?EventLotteryLucky
    {
        return EventLotteryLucky::query()
            ->whereKey($luckyId)
            ->whereHas('lotteryRule.lotteryPrize', function ($q) use ($lottery): void {
                $q->where('lottery_id', $lottery->id_lottery);
            })
            ->first();
    }

    private function finalizeLockResponse(EventLottery $lottery): ?JsonResponse
    {
        $lottery->loadMissing('event');
        if ($lottery->event?->finalized_at !== null) {
            return response()->json(['message' => 'PadelEvent is finalized.'], 409);
        }

        return null;
    }
}
