<?php

declare(strict_types=1);

namespace App\Integrations\RankedinBpt\Contracts;

interface BptTopPlayersReportBuilder
{
    /**
     * @return array{events: list<array<string, mixed>>, divisionsRanking: list<array<string, mixed>>, debug?: array<string, mixed>}
     */
    public function build(int $take, bool $debug): array;
}
