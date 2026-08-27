<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BackendRefreshCommand extends Command
{
    protected $signature = 'backend:refresh {--deploy : Run production post-deploy steps}';

    protected $description = 'Clear stale bootstrap/Redis state and regenerate API docs (deploy: migrate + rebuild caches + queue restart)';

    public function handle(): int
    {
        $deploy = (bool) $this->option('deploy');

        foreach ($this->steps($deploy) as [$command, $parameters]) {
            $exitCode = $this->call($command, $parameters);

            if ($exitCode !== self::SUCCESS) {
                $this->error("Command [{$command}] failed with exit code {$exitCode}.");

                return $exitCode;
            }
        }

        $this->info($deploy ? 'Backend deploy refresh completed.' : 'Backend refresh completed.');

        return self::SUCCESS;
    }

    /**
     * @return list<array{0: string, 1: array<string, mixed>}>
     */
    protected function steps(bool $deploy): array
    {
        $steps = [];

        if ($deploy) {
            $steps[] = ['migrate', ['--force' => true]];
        }

        $steps[] = ['optimize:clear', []];

        if ($deploy) {
            $steps[] = ['config:cache', []];
            $steps[] = ['route:cache', []];
            $steps[] = ['event:cache', []];
            $steps[] = ['queue:restart', []];
        }

        $steps[] = ['api:docs:generate-all', []];

        return $steps;
    }
}
