<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Support\Str;

/**
 * Trait for models that support resource metadata
 *
 * This trait provides standardized metadata structure and common methods
 * for models that need to expose their searchable, filterable, and sortable fields.
 */
trait HasResourceMetadata
{
    /**
     * Get the resource name for this model
     */
    public static function getResourceName(): string
    {
        $modelName = class_basename(static::class);

        return Str::plural(Str::snake($modelName));
    }

    /**
     * Get searchable fields for this model
     */
    public static function getSearchableFields(): array
    {
        return static::$searchableFields ?? [];
    }

    /**
     * Get filterable fields for this model
     */
    public static function getFilterableFields(): array
    {
        return static::$filterableFields ?? [];
    }

    /**
     * Get sortable fields for this model
     */
    public static function getSortableFields(): array
    {
        return static::$sortableFields ?? [];
    }

    /**
     * Check if a field is searchable
     */
    public static function isSearchableField(string $field): bool
    {
        return in_array($field, static::getSearchableFields());
    }

    /**
     * Check if a field is filterable
     */
    public static function isFilterableField(string $field): bool
    {
        return in_array($field, static::getFilterableFields());
    }

    /**
     * Check if a field is sortable
     */
    public static function isSortableField(string $field): bool
    {
        return in_array($field, static::getSortableFields());
    }

    /**
     * Get all metadata for this model
     */
    public static function getMetadata(): array
    {
        return [
            'resource' => static::getResourceName(),
            'searchable_fields' => static::getSearchableFields(),
            'filterable_fields' => static::getFilterableFields(),
            'sortable_fields' => static::getSortableFields(),
        ];
    }
}
