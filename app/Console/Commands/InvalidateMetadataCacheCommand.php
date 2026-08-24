<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Cache\MetadataCacheInvalidator;
use App\Services\Cache\PadelizCache;
use Illuminate\Console\Command;

class InvalidateMetadataCacheCommand extends Command
{
    protected $signature = 'padeliz:cache:invalidate-metadata
                            {--schema= : Flush metadata for a single schema (e.g. Club)}
                            {--scope= : Use "application" to flush all application cache tags}';

    protected $description = 'Invalidate metadata cache (or all application tags with --scope=application)';

    public function handle(MetadataCacheInvalidator $invalidator, PadelizCache $cache): int
    {
        if ($this->option('scope') === 'application') {
            $cache->flushApplicationTags();
            $this->info('Application cache tags flushed.');

            return self::SUCCESS;
        }

        $schema = $this->option('schema');

        if (is_string($schema) && $schema !== '') {
            $invalidator->invalidateSchema($schema);
            $this->info("Metadata cache flushed for schema: {$schema}");

            return self::SUCCESS;
        }

        $invalidator->invalidateAll();
        $this->info('All metadata cache tags flushed.');

        return self::SUCCESS;
    }
}
