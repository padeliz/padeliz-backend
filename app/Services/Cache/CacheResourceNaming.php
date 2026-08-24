<?php

declare(strict_types=1);

namespace App\Services\Cache;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class CacheResourceNaming
{
    public static function pathForSchema(string $schemaName): string
    {
        $modelName = class_basename($schemaName);
        $kebabCase = Str::kebab($modelName);

        if (str_contains($kebabCase, '-')) {
            $parts = explode('-', $kebabCase);
            $lastPart = array_pop($parts);
            $parts[] = Str::plural($lastPart);

            return implode('-', $parts);
        }

        return Str::plural($kebabCase);
    }

    public static function pathForModel(Model $model): string
    {
        return self::pathForSchema(class_basename($model));
    }
}
