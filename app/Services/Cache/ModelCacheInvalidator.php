<?php

declare(strict_types=1);

namespace App\Services\Cache;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ModelCacheInvalidator
{
    public function __construct(
        private readonly PadelizCache $cache,
        private readonly MetadataCacheInvalidator $metadataInvalidator,
        private readonly EventDivisionCacheInvalidator $eventDivisionInvalidator,
    ) {
    }

    public function invalidate(Model $model): void
    {
        $schema = class_basename($model);

        $this->metadataInvalidator->invalidateSchema($schema);
        $this->metadataInvalidator->invalidateSuppliersOf($model);

        $resource = CacheResourceNaming::pathForModel($model);
        $primaryKey = $model->getKey();

        if ($primaryKey !== null) {
            // Do not flush the broad `reference` tag: that would invalidate every
            // unrelated reference show/index. Targeted tags are enough because
            // entries are registered under resource and id tags on write.
            $this->cache->flushTags([
                'reference:' . $resource,
                'reference:' . $resource . ':' . $primaryKey,
                'mgr-list:' . $resource,
            ]);
        }

        $this->flushBelongsToParents($model);
        $this->flushEditionScope($model);
        $this->flushDivisionAndLotteryTags($model);
        $this->flushSpatieIfApplicable($model);
    }

    private function flushBelongsToParents(Model $model): void
    {
        foreach ($model->getAttributes() as $attribute => $value) {
            if (! is_string($attribute) || ! str_ends_with($attribute, '_id') || $value === null) {
                continue;
            }

            $relationName = Str::camel(str_replace('_id', '', $attribute));

            if (! method_exists($model, $relationName)) {
                continue;
            }

            try {
                $relation = $model->{$relationName}();

                if (! $relation instanceof BelongsTo) {
                    continue;
                }

                $related = $relation->getRelated();

                if (! $related instanceof Model) {
                    continue;
                }

                $parentResource = CacheResourceNaming::pathForModel($related);
                $this->cache->flushTags(['reference:' . $parentResource . ':' . $value]);
            } catch (\Throwable) {
                continue;
            }
        }
    }

    private function flushEditionScope(Model $model): void
    {
        $editionId = $this->resolveEditionId($model);

        if ($editionId !== null) {
            $this->eventDivisionInvalidator->invalidateEdition($editionId);
        }
    }

    private function flushDivisionAndLotteryTags(Model $model): void
    {
        $class = class_basename($model);

        if (in_array($class, ['EventDivision', 'EventDivisionSchedule'], true)) {
            $divisionId = $model->getAttribute('id_event_division');

            if ($divisionId !== null) {
                $this->eventDivisionInvalidator->invalidateSchedule((int) $divisionId);
                $this->eventDivisionInvalidator->invalidateStandings((int) $divisionId);
            }
        }

        if (str_starts_with($class, 'EventLottery')) {
            $lotteryId = $model->getAttribute('id_event_lottery');

            if ($lotteryId !== null) {
                $this->eventDivisionInvalidator->invalidateLottery((int) $lotteryId);
            }
        }
    }

    private function flushSpatieIfApplicable(Model $model): void
    {
        $class = class_basename($model);

        if (in_array($class, ['PadelEvent', 'EventDivision', 'EventRegistration', 'EventParticipation'], true)) {
            $this->cache->flushTags(['response-cache']);
        }
    }

    private function resolveEditionId(Model $model): ?int
    {
        foreach (['id_event', 'event_id'] as $key) {
            $value = $model->getAttribute($key);

            if ($value !== null) {
                return (int) $value;
            }
        }

        if (method_exists($model, 'event')) {
            try {
                $event = $model->relationLoaded('event')
                    ? $model->getRelation('event')
                    : $model->event()->first();

                if ($event instanceof Model && $event->getKey() !== null) {
                    return (int) $event->getKey();
                }
            } catch (\Throwable) {
                // ignore
            }
        }

        return null;
    }
}
