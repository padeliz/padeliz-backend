<?php

declare(strict_types=1);

namespace App\Observers;

use App\Services\Cache\ModelCacheInvalidator;
use Illuminate\Database\Eloquent\Model;

class InvalidatesResourceCacheObserver
{
    public bool $afterCommit = true;

    public function __construct(
        private readonly ModelCacheInvalidator $invalidator,
    ) {
    }

    public function saved(Model $model): void
    {
        $this->invalidator->invalidate($model);
    }

    public function deleted(Model $model): void
    {
        $this->invalidator->invalidate($model);
    }
}
