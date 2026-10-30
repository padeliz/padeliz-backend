<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DeployReleaseCommand extends Command
{
    protected $signature = 'deploy:release';

    protected $description = 'App Platform PRE_DEPLOY: migrate and signal queue workers to restart';

    public function handle(): int
    {
        foreach ($this->steps() as [$command, $parameters]) {
            $exitCode = $this->call($command, $parameters);

            if ($exitCode !== self::SUCCESS) {
                $this->error("Command [{$command}] failed with exit code {$exitCode}.");

                return $exitCode;
            }
        }

        $this->info('Deploy release completed.');

        return self::SUCCESS;
    }

    /**
     * @return list<array{0: string, 1: array<string, mixed>}>
     */
    protected function steps(): array
    {
        return [
            ['migrate', ['--force' => true]],
            ['queue:restart', []],
        ];
    }
}
