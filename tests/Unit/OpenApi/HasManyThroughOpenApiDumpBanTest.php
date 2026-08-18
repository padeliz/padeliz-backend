<?php

declare(strict_types=1);

namespace Tests\Unit\OpenApi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionNamedType;
use Tests\TestCase;

/**
 * Ban OpenAPI array $ref dumps for HasManyThrough relations.
 *
 * Linked-entity UIs must use dedicated list endpoints, not parent schema dumps.
 */
class HasManyThroughOpenApiDumpBanTest extends TestCase
{
    public function test_models_do_not_document_has_many_through_as_array_ref_properties(): void
    {
        $violations = [];
        $discoveredArrayRefs = [];

        foreach (glob(app_path('Models/*.php')) ?: [] as $file) {
            $className = 'App\\Models\\' . basename($file, '.php');

            if (! class_exists($className) || ! is_subclass_of($className, Model::class)) {
                continue;
            }

            $reflection = new ReflectionClass($className);
            $docComment = $reflection->getDocComment();

            if ($docComment === false) {
                continue;
            }

            foreach ($this->extractArrayRefProperties($docComment) as $propertyName) {
                $discoveredArrayRefs[] = "{$className}.{$propertyName}";

                $methodName = $this->resolveRelationMethodName($reflection, $propertyName);

                if ($methodName === null) {
                    continue;
                }

                if ($this->methodReturnsHasManyThrough($reflection, $methodName)) {
                    $violations[] = "{$className}::{$methodName}() is HasManyThrough but OA documents array \$ref property \"{$propertyName}\"";
                }
            }
        }

        $this->assertNotEmpty(
            $discoveredArrayRefs,
            'Expected to discover at least one OA array $ref property (scanner sanity check)'
        );

        $this->assertContains(
            'App\\Models\\Edition.participations',
            $discoveredArrayRefs,
            'HasMany Edition.participations must remain discoverable as an OA array $ref'
        );

        $this->assertContains(
            'App\\Models\\EditionLotteryRule.luckies',
            $discoveredArrayRefs,
            'HasMany EditionLotteryRule.luckies must remain discoverable as an OA array $ref'
        );

        $this->assertNotContains(
            'App\\Models\\Division.participations',
            $discoveredArrayRefs,
            'HasManyThrough Division.participations must not be dumped in OA'
        );

        $this->assertSame(
            [],
            $violations,
            "HasManyThrough relations must not be dumped as OA array \$ref properties:\n" . implode("\n", $violations)
        );
    }

    /**
     * @return list<string>
     */
    private function extractArrayRefProperties(string $docComment): array
    {
        $properties = [];

        if (! preg_match_all('/@OA\\\\Property\s*\((.*?)\)/s', $docComment, $blocks)) {
            return [];
        }

        foreach ($blocks[1] as $block) {
            if (! preg_match('/property\s*=\s*"([^"]+)"/', $block, $propertyMatch)) {
                continue;
            }

            if (! preg_match('/type\s*=\s*"array"/', $block)) {
                continue;
            }

            if (! preg_match('/@OA\\\\Items\s*\(\s*ref\s*=\s*"#\/components\/schemas\/[^"]+"/', $block)) {
                continue;
            }

            $properties[] = $propertyMatch[1];
        }

        return $properties;
    }

    private function resolveRelationMethodName(ReflectionClass $reflection, string $propertyName): ?string
    {
        $candidates = array_values(array_unique([
            $propertyName,
            Str::camel($propertyName),
        ]));

        foreach ($candidates as $candidate) {
            if ($reflection->hasMethod($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function methodReturnsHasManyThrough(ReflectionClass $reflection, string $methodName): bool
    {
        $method = $reflection->getMethod($methodName);

        if (! $method->isPublic() || $method->getNumberOfRequiredParameters() > 0) {
            return false;
        }

        $returnType = $method->getReturnType();

        if ($returnType instanceof ReflectionNamedType && $returnType->getName() === HasManyThrough::class) {
            return true;
        }

        $model = $reflection->newInstanceWithoutConstructor();

        if (! $model instanceof Model) {
            return false;
        }

        $relation = $model->{$methodName}();

        return $relation instanceof HasManyThrough;
    }
}
