<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DeployOptimizeCommand extends Command
{
    protected $signature = 'deploy:optimize';

    protected $description = 'Container boot: cache config, routes, events, and views from runtime env';

    public function handle(): int
    {
        foreach ($this->steps() as [$command, $parameters]) {
            $exitCode = $this->call($command, $parameters);

            if ($exitCode !== self::SUCCESS) {
                $this->error("Command [{$command}] failed with exit code {$exitCode}.");

                return $exitCode;
            }
        }

        $this->info('Deploy optimize completed.');

        return self::SUCCESS;
    }

    /**
     * @return list<array{0: string, 1: array<string, mixed>}>
     */
    protected function steps(): array
    {
        return [
            ['config:cache', []],
            ['route:cache', []],
            ['event:cache', []],
            ['view:cache', []],
        ];
    }
}
