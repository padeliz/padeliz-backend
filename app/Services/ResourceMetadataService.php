<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ResourceMetadataService
{
    /**
     * Default limit for filter values
     */
    private const DEFAULT_FILTER_LIMIT = 100;

    /**
     * Cache TTL in seconds (1 hour)
     */
    private const CACHE_TTL = 3600;

    /**
     * Get metadata for a specific resource
     */
    public function getMetadata(string $resourceName): array
    {
        $cacheKey = "resource_metadata_{$resourceName}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($resourceName) {
            return $this->generateMetadata($resourceName);
        });
    }

    /**
     * Generate metadata for a resource
     */
    private function generateMetadata(string $resourceName): array
    {
        $modelClass = $this->getModelClass($resourceName);
        $model = new $modelClass();
        $tableName = $model->getTable();

        return [
            'resource' => $resourceName,
            'searchable_fields' => $this->getSearchableFields($modelClass),
            'filterable_fields' => $this->getFilterableFields($modelClass, $tableName),
            'sortable_fields' => $this->getSortableFields($modelClass),
        ];
    }

    /**
     * Get model class from resource name
     */
    private function getModelClass(string $resourceName): string
    {
        $modelName = Str::studly(Str::singular($resourceName));
        $modelClass = "App\\Models\\{$modelName}";

        if (! class_exists($modelClass)) {
            throw new \InvalidArgumentException("Model class {$modelClass} not found");
        }

        if (! is_subclass_of($modelClass, Model::class)) {
            throw new \InvalidArgumentException("Class {$modelClass} is not a valid Eloquent model");
        }

        return $modelClass;
    }

    /**
     * Get searchable fields from model
     */
    private function getSearchableFields(string $modelClass): array
    {
        if (! property_exists($modelClass, 'searchableFields')) {
            return [];
        }

        return $modelClass::$searchableFields ?? [];
    }

    /**
     * Get filterable fields with their values
     */
    private function getFilterableFields(string $modelClass, string $tableName): array
    {
        if (! property_exists($modelClass, 'filterableFields')) {
            return [];
        }

        $filterableFields = $modelClass::$filterableFields ?? [];
        $result = [];

        foreach ($filterableFields as $field) {
            $result[$field] = $this->getFieldMetadata($modelClass, $tableName, $field);
        }

        return $result;
    }

    /**
     * Get sortable fields from model
     */
    private function getSortableFields(string $modelClass): array
    {
        if (! property_exists($modelClass, 'sortableFields')) {
            return [];
        }

        return $modelClass::$sortableFields ?? [];
    }

    /**
     * Get metadata for a specific field
     */
    private function getFieldMetadata(string $modelClass, string $tableName, string $field): array
    {
        $columnInfo = $this->getColumnInfo($tableName, $field);

        if ($this->isForeignKey($tableName, $field)) {
            return $this->getForeignKeyMetadata($modelClass, $field);
        }

        return [
            'type' => $this->getFieldType($columnInfo),
            'values' => $this->getFieldValues($modelClass, $tableName, $field, $columnInfo),
        ];
    }

    /**
     * Get column information from database
     */
    private function getColumnInfo(string $tableName, string $field): ?array
    {
        if (! Schema::hasTable($tableName)) {
            return null;
        }

        $columns = Schema::getColumnListing($tableName);
        if (! in_array($field, $columns)) {
            return null;
        }

        // Get the table prefix from config
        $prefix = config('database.connections.mysql.prefix', '');
        $fullTableName = $prefix . $tableName;

        // Get column type using raw SQL
        $columnInfo = DB::select('
            SELECT
                DATA_TYPE as type,
                IS_NULLABLE as nullable,
                COLUMN_DEFAULT as default_value
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
            AND COLUMN_NAME = ?
        ', [$fullTableName, $field]);

        if (empty($columnInfo)) {
            return null;
        }

        $info = $columnInfo[0];

        return [
            'type' => $info->type,
            'nullable' => $info->nullable === 'YES',
            'default' => $info->default_value,
        ];
    }

    /**
     * Check if a field is a foreign key
     */
    private function isForeignKey(string $tableName, string $field): bool
    {
        // Get the table prefix from config
        $prefix = config('database.connections.mysql.prefix', '');
        $fullTableName = $prefix . $tableName;

        $foreignKeys = DB::select('
            SELECT
                COLUMN_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
            AND COLUMN_NAME = ?
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ', [$fullTableName, $field]);

        return ! empty($foreignKeys);
    }

    /**
     * Get foreign key metadata
     */
    private function getForeignKeyMetadata(string $modelClass, string $field): array
    {
        $model = new $modelClass();
        $relationship = $this->getRelationship($model, $field);

        if (! $relationship) {
            throw new \InvalidArgumentException("No relationship found for foreign key field: {$field}");
        }

        $relatedModelClass = get_class($relationship->getRelated());
        $relatedModel = new $relatedModelClass();
        $relatedTableName = $relatedModel->getTable();

        // Check if related model has searchable fields
        if (! property_exists($relatedModelClass, 'searchableFields') || empty($relatedModelClass::$searchableFields)) {
            throw new \InvalidArgumentException("Related model {$relatedModelClass} does not have searchable fields defined");
        }

        $searchableFields = $relatedModelClass::$searchableFields ?? [];
        if (empty($searchableFields)) {
            throw new \InvalidArgumentException("Related model {$relatedModelClass} has empty searchable fields");
        }
        $searchableField = $searchableFields[0]; // Use first searchable field

        return [
            'type' => 'foreign_key',
            'related_model' => $this->getResourceNameFromModel($relatedModelClass),
            'searchable_field' => $searchableField,
            'values' => $this->getRelatedModelValues($relatedModelClass, $relatedTableName, $searchableField),
        ];
    }

    /**
     * Get relationship from model
     */
    private function getRelationship(Model $model, string $field): ?\Illuminate\Database\Eloquent\Relations\Relation
    {
        $relationshipName = $this->getRelationshipName($field);

        if (! method_exists($model, $relationshipName)) {
            return null;
        }

        try {
            return $model->{$relationshipName}();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get relationship name from foreign key field
     */
    private function getRelationshipName(string $field): string
    {
        // Remove _id suffix and convert to camelCase
        $name = str_replace('_id', '', $field);

        return Str::camel($name);
    }

    /**
     * Get resource name from model class
     */
    private function getResourceNameFromModel(string $modelClass): string
    {
        $modelName = class_basename($modelClass);

        return Str::plural(Str::snake($modelName));
    }

    /**
     * Get field type from column info
     */
    private function getFieldType(?array $columnInfo): string
    {
        if (! $columnInfo) {
            return 'string';
        }

        $type = strtolower($columnInfo['type']);

        return match ($type) {
            'int', 'integer', 'bigint', 'smallint' => 'integer',
            'tinyint' => 'boolean', // MySQL boolean is stored as tinyint
            'decimal', 'float', 'double', 'real' => 'float',
            'bool', 'boolean', 'bit' => 'boolean',
            'datetime', 'timestamp' => 'datetime',
            'date' => 'date',
            'time' => 'time',
            'json' => 'json',
            'text', 'longtext', 'mediumtext', 'tinytext' => 'text',
            'varchar', 'char', 'string' => 'string',
            default => 'string',
        };
    }

    /**
     * Get field values based on type
     */
    private function getFieldValues(string $modelClass, string $tableName, string $field, ?array $columnInfo): array
    {
        $type = $this->getFieldType($columnInfo);

        return match ($type) {
            'boolean' => [true, false],
            'integer', 'float' => $this->getDistinctValues($modelClass, $tableName, $field),
            'string' => $this->getDistinctValues($modelClass, $tableName, $field),
            default => $this->getDistinctValues($modelClass, $tableName, $field),
        };
    }

    /**
     * Get distinct values from database
     */
    private function getDistinctValues(string $modelClass, string $tableName, string $field): array
    {
        $query = $modelClass::select($field)
            ->whereNotNull($field)
            ->where($field, '!=', '')
            ->distinct()
            ->limit(self::DEFAULT_FILTER_LIMIT)
            ->orderBy($field);

        $values = $query->pluck($field)->toArray();

        // Remove null and empty values
        return array_filter($values, fn ($value) => $value !== null && $value !== '');
    }

    /**
     * Get values from related model
     */
    private function getRelatedModelValues(string $relatedModelClass, string $relatedTableName, string $searchableField): array
    {
        $query = $relatedModelClass::select($searchableField)
            ->whereNotNull($searchableField)
            ->where($searchableField, '!=', '')
            ->distinct()
            ->limit(self::DEFAULT_FILTER_LIMIT)
            ->orderBy($searchableField);

        $values = $query->pluck($searchableField)->toArray();

        // Remove null and empty values
        return array_filter($values, fn ($value) => $value !== null && $value !== '');
    }

    /**
     * Clear cache for a specific resource
     */
    public function clearCache(string $resourceName): void
    {
        $cacheKey = "resource_metadata_{$resourceName}";
        Cache::forget($cacheKey);
    }

    /**
     * Clear all metadata cache
     */
    public function clearAllCache(): void
    {
        // This is a simple implementation - in production you might want to use cache tags
        // or maintain a list of cached keys
        Cache::flush();
    }
}
