<?php

declare(strict_types=1);

namespace App\Services\Cache;

use Illuminate\Database\Eloquent\Model;

class MetadataCacheInvalidator
{
    public function __construct(
        private readonly PadelizCache $cache,
        private readonly MetadataConsumerRegistry $registry,
    ) {
    }

    public function invalidateSchema(string $schema): void
    {
        $this->cache->flushTags(['metadata:'.$schema]);
    }

    public function invalidateAll(): void
    {
        $this->cache->flushTags(['metadata']);
    }

    public function invalidateSuppliersOf(Model $model): void
    {
        $supplierSchema = class_basename($model);

        $this->cache->flushTags(['metadata-supplier:'.$supplierSchema]);

        foreach ($this->registry->consumersForSupplier($supplierSchema) as $consumerSchema) {
            $this->invalidateSchema($consumerSchema);
        }
    }
}
