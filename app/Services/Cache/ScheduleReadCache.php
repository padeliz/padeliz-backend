<?php

declare(strict_types=1);

namespace App\Services\Cache;

use App\Models\EditionDivision;
use Closure;

class ScheduleReadCache
{
    public function __construct(
        private readonly PadelizCache $cache,
    ) {
    }

    public function rememberScheduleShow(
        int $divisionId,
        bool $board,
        bool $scores,
        Closure $resolver,
    ): mixed {
        $policy = config('cache_policies.schedule_show');
        $division = EditionDivision::query()->with('edition')->find($divisionId);
        $finalized = $division?->edition?->finalized_at !== null;
        $ttl = $finalized ? (int) $policy['ttl_finalized'] : (int) $policy['ttl_live'];
        $tags = ['division:'.$divisionId];
        $key = sprintf('schedule:%d:board:%d:scores:%d', $divisionId, (int) $board, (int) $scores);

        return $this->cache->remember($key, $ttl, $tags, $resolver);
    }

    public function rememberStandings(int $divisionId, Closure $resolver): mixed
    {
        $policy = config('cache_policies.standings');
        $tags = ['division:'.$divisionId];
        $key = 'standings:'.$divisionId;

        return $this->cache->remember($key, (int) $policy['ttl'], $tags, $resolver);
    }

    public function rememberPrizesBoard(int $lotteryId, Closure $resolver): mixed
    {
        $policy = config('cache_policies.lottery_prizes_board');
        $tags = ['lottery:'.$lotteryId];
        $key = 'lottery:prizes-board:'.$lotteryId;

        return $this->cache->remember($key, (int) $policy['ttl'], $tags, $resolver);
    }
}
