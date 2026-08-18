<?php

declare(strict_types=1);

namespace App\Services\PlayerImport\Contracts;

interface PlayerCsvFormat
{
    public function formatKey(): string;

    /**
     * @param  list<string>  $headers
     */
    public function supports(array $headers): bool;

    /**
     * @param  array<string, string>  $assoc
     * @return array{name: string, email: string, phone: string}
     */
    public function parseRow(array $assoc): array;
}
