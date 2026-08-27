<?php

declare(strict_types=1);

namespace App\Services\Cache;

use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class ModelSchemaCatalog
{
    /** @var null|array<string, class-string<Model>> schema basename => FQCN */
    private ?array $schemaToClass = null;

    public function __construct(
        private readonly ?string $modelsPath = null,
        private readonly string $rootNamespace = 'App\\Models',
    ) {
    }

    /**
     * @return class-string<Model>
     */
    public function classForSchema(string $schema): string
    {
        $map = $this->map();

        if (! isset($map[$schema])) {
            throw new InvalidArgumentException("Model class for schema {$schema} not found");
        }

        return $map[$schema];
    }

    /**
     * @return list<class-string<Model>>
     */
    public function eloquentClasses(): array
    {
        $classes = array_values($this->map());
        sort($classes);

        return $classes;
    }

    /**
     * @return list<class-string<Model>>
     */
    public function metadataClasses(): array
    {
        $classes = [];

        foreach ($this->eloquentClasses() as $class) {
            if (! in_array(HasResourceMetadata::class, class_uses_recursive($class), true)) {
                continue;
            }

            $classes[] = $class;
        }

        return $classes;
    }

    public function reset(): void
    {
        $this->schemaToClass = null;
    }

    /**
     * @return array<string, class-string<Model>>
     */
    private function map(): array
    {
        if ($this->schemaToClass !== null) {
            return $this->schemaToClass;
        }

        $root = $this->modelsPath ?? app_path('Models');
        $map = [];

        if (! is_dir($root)) {
            return $this->schemaToClass = $map;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen(rtrim($root, DIRECTORY_SEPARATOR)) + 1);
            $relative = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);

            if (! str_ends_with($relative, '.php')) {
                continue;
            }

            $relativeWithoutExt = substr($relative, 0, -4);
            $classSuffix = str_replace(DIRECTORY_SEPARATOR, '\\', $relativeWithoutExt);
            $class = $this->rootNamespace . '\\' . $classSuffix;

            if (! class_exists($class)) {
                continue;
            }

            if (! is_subclass_of($class, Model::class)) {
                continue;
            }

            $basename = class_basename($class);

            if (isset($map[$basename])) {
                throw new InvalidArgumentException(
                    "Duplicate model schema basename {$basename}: {$map[$basename]} and {$class}"
                );
            }

            $map[$basename] = $class;
        }

        return $this->schemaToClass = $map;
    }
}
