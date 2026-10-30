<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\MissingApiDocumentationException;
use App\Services\Cache\ModelSchemaCatalog;
use App\Services\Cache\PadelizCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ResourceMetadataService
{
    /**
     * Default limit for filter values
     */
    private const DEFAULT_FILTER_LIMIT = 100;

    public function __construct(
        private readonly PadelizCache $padelizCache,
        private readonly ModelSchemaCatalog $modelSchemaCatalog,
    ) {
    }

    /**
     * Get metadata for a specific schema/model
     */
    public function getMetadata(string $schemaName, string $version = 'v1'): array
    {
        $policy = config('cache_policies.metadata');
        $baseTags = array_merge($policy['tags'], ['metadata:' . $schemaName]);
        $cacheKey = "metadata:v1:{$schemaName}";
        $ttl = (int) $policy['ttl'];

        // Use consistent tags for read/write. Supplier invalidation is handled by
        // MetadataCacheInvalidator::invalidateSuppliersOf via MetadataConsumerRegistry.
        return $this->padelizCache->remember(
            $cacheKey,
            $ttl,
            $baseTags,
            fn () => $this->generateMetadata($schemaName, $version),
        );
    }

    /**
     * Generate metadata for a schema/model
     */
    private function generateMetadata(string $schemaName, string $version): array
    {
        $modelClass = $this->getModelClassFromSchema($schemaName);
        $model = new $modelClass();
        $tableName = $model->getTable();

        // Get schema from Swagger docs
        $swaggerSchema = $this->getSwaggerSchema($schemaName, $version);

        return [
            'version' => $version,
            'schema_name' => $schemaName,
            'resource_path' => $this->getResourceNameFromSchema($schemaName),
            'swagger_schema' => $swaggerSchema,
            'request_body' => $this->getRequestBodySchemas($schemaName, $version),
            'display_field' => $this->getDisplayField($modelClass),
            'searchable_fields' => $this->getSearchableFields($modelClass),
            'filterable_fields' => $this->getFilterableFields($modelClass, $tableName),
            'sortable_fields' => $this->getSortableFields($modelClass),
            'list_fields' => $this->getListFields($modelClass),
        ];
    }

    /**
     * Get model class from schema name
     */
    private function getModelClassFromSchema(string $schemaName): string
    {
        return $this->modelSchemaCatalog->classForMetadataSchema($schemaName);
    }

    /**
     * Get Swagger schema for a model
     *
     * @return array<string, mixed>
     */
    private function getSwaggerSchema(string $schemaName, string $version): array
    {
        $swaggerFile = $this->getSwaggerFilePath($version);

        if (! File::exists($swaggerFile)) {
            throw new MissingApiDocumentationException(
                "OpenAPI documentation file is missing for version {$version} ({$swaggerFile})."
            );
        }

        $swaggerContent = File::get($swaggerFile);
        $swaggerData = json_decode($swaggerContent, true);
        $lookup = $schemaName === 'PadelEvent' ? 'Event' : $schemaName;

        if (! is_array($swaggerData) || ! isset($swaggerData['components']['schemas'][$lookup])) {
            throw new MissingApiDocumentationException(
                "OpenAPI schema [{$lookup}] is missing from documentation version {$version}."
            );
        }

        /** @var array<string, mixed> $schema */
        $schema = $swaggerData['components']['schemas'][$lookup];

        return $schema;
    }

    /**
     * Get Swagger file path for version
     */
    private function getSwaggerFilePath(string $version): string
    {
        return storage_path("api-docs/api-docs-{$version}.json");
    }

    /**
     * Get request body schemas for a schema/model
     */
    private function getRequestBodySchemas(string $schemaName, string $version): array
    {
        $swaggerFile = $this->getSwaggerFilePath($version);

        if (! File::exists($swaggerFile)) {
            return [];
        }

        $swaggerContent = File::get($swaggerFile);
        $swaggerData = json_decode($swaggerContent, true);

        if (! isset($swaggerData['paths'])) {
            return [];
        }

        $requestBodies = [];
        $resourceName = $this->getResourceNameFromSchema($schemaName);

        foreach ($swaggerData['paths'] as $path => $pathData) {
            // Check if this path is related to our resource
            if (strpos($path, "/api/{$version}/manager/{$resourceName}") !== false) {
                foreach ($pathData as $method => $methodData) {
                    if (isset($methodData['requestBody']['content']['application/json']['schema']['properties'])) {
                        $methodUpper = strtoupper($method);
                        $pathShort = $this->getShortPath($path, $resourceName);

                        if (! isset($requestBodies[$methodUpper])) {
                            $requestBodies[$methodUpper] = [];
                        }

                        $requestBodies[$methodUpper][$pathShort] = $methodData['requestBody']['content']['application/json']['schema']['properties'];
                    }
                }
            }
        }

        return $requestBodies;
    }

    /**
     * Get resource name from schema name
     */
    private function getResourceNameFromSchema(string $schemaName): string
    {
        $modelName = class_basename($schemaName);

        // PHP root model is PadelEvent (avoids Facade clash); HTTP resource stays /events.
        if ($modelName === 'PadelEvent' || $modelName === 'Event') {
            return 'events';
        }

        // Account aggregate is OpenAPI "User" but product directory stays /players.
        // Emails/phones/identities keep product player-* paths.
        $productPaths = [
            'User' => 'players',
            'UserEmail' => 'player-emails',
            'UserPhone' => 'player-phones',
            'UserIdentity' => 'player-identities',
        ];
        if (isset($productPaths[$modelName])) {
            return $productPaths[$modelName];
        }

        $kebabCase = Str::kebab($modelName);

        // For hyphenated strings, only pluralize the last word
        if (str_contains($kebabCase, '-')) {
            $parts = explode('-', $kebabCase);
            $lastPart = array_pop($parts);
            $pluralizedLastPart = Str::plural($lastPart);
            $parts[] = $pluralizedLastPart;

            return implode('-', $parts);
        }

        // For single words, pluralize normally
        return Str::plural($kebabCase);
    }

    /**
     * Get short path from full API path
     */
    private function getShortPath(string $fullPath, string $resourceName): string
    {
        // Convert /api/v1/manager/clubs/{club} to /clubs/{club}
        $pattern = "/\/api\/v\d+\/manager\/{$resourceName}(.*)/";
        if (preg_match($pattern, $fullPath, $matches)) {
            return "/{$resourceName}{$matches[1]}";
        }

        return "/{$resourceName}";
    }

    /**
     * Get display field from model
     */
    private function getDisplayField(string $modelClass): ?string
    {
        if (! property_exists($modelClass, 'displayField')) {
            return null;
        }

        return $modelClass::$displayField ?? null;
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
     * Get preferred list column field keys from model (always an array).
     *
     * @return list<string>
     */
    private function getListFields(string $modelClass): array
    {
        if (! property_exists($modelClass, 'listFields')) {
            return [];
        }

        return array_values($modelClass::$listFields ?? []);
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
            'values' => $this->getFieldValues($modelClass, $field, $columnInfo),
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
        $primaryKey = $relatedModel->getKeyName();

        $searchableFields = property_exists($relatedModelClass, 'searchableFields')
            ? ($relatedModelClass::$searchableFields ?? [])
            : [];

        // Fall back to primary key when related model has no searchable string fields
        // (e.g. EventLottery used as lottery_id filter on EventLotteryRule).
        $labelField = ! empty($searchableFields) ? $searchableFields[0] : $primaryKey;

        $relatedSchema = class_basename($relatedModelClass);
        if ($relatedSchema === 'PadelEvent') {
            $relatedSchema = 'Event';
        }

        return [
            'type' => 'foreign_key',
            'related_schema_name' => $relatedSchema,
            'related_schema_field' => $labelField,
            'values' => $this->getRelatedModelValuesAsObject($relatedModelClass, $labelField),
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
        // Users FKs are *user_id (or user_id); role relations drop the user segment
        // (player_user_id → player, imported_by_manager_user_id → importedByManager).
        if (str_ends_with($field, '_user_id')) {
            return Str::camel(substr($field, 0, -strlen('_user_id')));
        }

        if ($field === 'user_id') {
            return 'user';
        }

        // Remove _id suffix and convert to camelCase
        $name = str_replace('_id', '', $field);

        return Str::camel($name);
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
    private function getFieldValues(string $modelClass, string $field, ?array $columnInfo): array
    {
        $type = $this->getFieldType($columnInfo);

        return match ($type) {
            'boolean' => [true, false],
            'integer', 'float' => $this->getDistinctValues($modelClass, $field),
            'string' => $this->getDistinctValues($modelClass, $field),
            default => $this->getDistinctValues($modelClass, $field),
        };
    }

    /**
     * Get distinct values from database
     */
    private function getDistinctValues(string $modelClass, string $field): array
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
     * Get values from related model as object with foreign key IDs as keys
     */
    private function getRelatedModelValuesAsObject(string $relatedModelClass, string $searchableField): \stdClass
    {
        $relatedModel = new $relatedModelClass();
        $primaryKey = $relatedModel->getKeyName();

        $query = $relatedModelClass::select([$primaryKey, $searchableField])
            ->whereNotNull($searchableField)
            ->limit(self::DEFAULT_FILTER_LIMIT);

        // String labels: skip empty strings. PK / integer labels: no empty-string filter.
        if ($searchableField !== $primaryKey) {
            $query->where($searchableField, '!=', '');
        }

        $query->orderBy($searchableField);

        $results = $query->get();

        $values = new \stdClass();
        foreach ($results as $result) {
            // Convert to string to ensure JSON object serialization
            $values->{(string) $result->{$primaryKey}} = $result->{$searchableField};
        }

        return $values;
    }
}
