<?php

declare(strict_types=1);

namespace App\Services\PlayerCustomField\Contracts;

interface PlayerCustomFieldValueCsvFormat
{
    public function formatKey(): string;

    /**
     * @param  list<string>  $headers
     */
    public function supports(array $headers): bool;

    /**
     * @param  array<string, string>  $assoc
     * @return array{
     *     full_name: string,
     *     email: string,
     *     phone: string,
     *     answers: array<string, string>
     * }
     */
    public function parseRow(array $assoc): array;
}
