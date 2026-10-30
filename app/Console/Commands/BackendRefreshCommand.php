<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BackendRefreshCommand extends Command
{
    protected $signature = 'backend:refresh';

    protected $description = 'Clear stale bootstrap/Redis state and regenerate API docs (local/dev)';

    public function handle(): int
    {
        foreach ($this->steps() as [$command, $parameters]) {
            $exitCode = $this->call($command, $parameters);

            if ($exitCode !== self::SUCCESS) {
                $this->error("Command [{$command}] failed with exit code {$exitCode}.");

                return $exitCode;
            }
        }

        $this->info('Backend refresh completed.');

        return self::SUCCESS;
    }

    /**
     * @return list<array{0: string, 1: array<string, mixed>}>
     */
    protected function steps(): array
    {
        return [
            ['optimize:clear', []],
            ['api:docs:generate-all', []],
        ];
    }
}
