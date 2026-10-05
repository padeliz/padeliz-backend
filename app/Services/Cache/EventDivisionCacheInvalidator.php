<?php

declare(strict_types=1);

namespace App\Services\Cache;

class EventDivisionCacheInvalidator
{
    public function __construct(
        private readonly PadelizCache $cache,
    ) {
    }

    public function invalidateSchedule(int $eventDivisionId): void
    {
        $this->cache->flushTags(['division:' . $eventDivisionId]);
    }

    public function invalidateStandings(int $eventDivisionId): void
    {
        $this->cache->flushTags(['division:' . $eventDivisionId]);
    }

    public function invalidateEdition(int $editionId): void
    {
        $this->cache->flushTags(['event:' . $editionId]);
    }

    public function invalidateLottery(int $lotteryId): void
    {
        $this->cache->flushTags(['lottery:' . $lotteryId]);
    }
}
