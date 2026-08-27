<?php

declare(strict_types=1);

namespace App\Services\Cache;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;

class MetadataConsumerRegistry
{
    /** @var null|array<string, list<string>> */
    private ?array $supplierToConsumers = null;

    public function __construct(
        private readonly ModelSchemaCatalog $modelSchemaCatalog,
    ) {
    }

    /**
     * @return list<string> PascalCase consumer schema names
     */
    public function consumersForSupplier(string $supplierSchema): array
    {
        return $this->map()[$supplierSchema] ?? [];
    }

    /**
     * @return array<string, list<string>> supplier Schema => consumer Schemas
     */
    public function map(): array
    {
        if ($this->supplierToConsumers !== null) {
            return $this->supplierToConsumers;
        }

        $map = [];

        foreach ($this->metadataModelClasses() as $consumerClass) {
            $consumerSchema = class_basename($consumerClass);

            foreach ($consumerClass::getFilterableFields() as $field) {
                $supplierSchema = $this->resolveSupplierSchema($consumerClass, $field);

                if ($supplierSchema === null) {
                    continue;
                }

                $map[$supplierSchema] ??= [];

                if (! in_array($consumerSchema, $map[$supplierSchema], true)) {
                    $map[$supplierSchema][] = $consumerSchema;
                }
            }
        }

        return $this->supplierToConsumers = $map;
    }

    public function reset(): void
    {
        $this->supplierToConsumers = null;
    }

    /**
     * @return list<class-string<Model>>
     */
    private function metadataModelClasses(): array
    {
        return $this->modelSchemaCatalog->metadataClasses();
    }

    /**
     * @param  class-string<Model>  $consumerClass
     */
    private function resolveSupplierSchema(string $consumerClass, string $field): ?string
    {
        if (! str_ends_with($field, '_id')) {
            return null;
        }

        try {
            /** @var Model $model */
            $model = new $consumerClass();
            $relationName = Str::camel(str_replace('_id', '', $field));

            if (! method_exists($model, $relationName)) {
                return null;
            }

            $relation = $model->{$relationName}();

            if (! $relation instanceof Relation) {
                return null;
            }

            return class_basename($relation->getRelated());
        } catch (\Throwable) {
            return null;
        }
    }
}
