<?php

declare(strict_types=1);

namespace App\Services\Cache;

class EditionDivisionCacheInvalidator
{
    public function __construct(
        private readonly PadelizCache $cache,
    ) {
    }

    public function invalidateSchedule(int $editionDivisionId): void
    {
        $this->cache->flushTags(['division:' . $editionDivisionId]);
    }

    public function invalidateStandings(int $editionDivisionId): void
    {
        $this->cache->flushTags(['division:' . $editionDivisionId]);
    }

    public function invalidateEdition(int $editionId): void
    {
        $this->cache->flushTags(['edition:' . $editionId]);
    }

    public function invalidateLottery(int $lotteryId): void
    {
        $this->cache->flushTags(['lottery:' . $lotteryId]);
    }
}
