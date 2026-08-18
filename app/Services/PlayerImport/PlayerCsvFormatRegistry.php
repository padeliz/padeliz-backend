<?php

declare(strict_types=1);

namespace App\Services\PlayerImport;

use App\Services\PlayerImport\Contracts\PlayerCsvFormat;

final class PlayerCsvFormatRegistry
{
    /**
     * @param  list<PlayerCsvFormat>  $formats
     */
    public function __construct(
        private readonly array $formats
    ) {
    }

    /**
     * @param  list<string>  $headers
     */
    public function detect(array $headers): ?PlayerCsvFormat
    {
        foreach ($this->formats as $format) {
            if ($format->supports($headers)) {
                return $format;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function formatKeys(): array
    {
        return array_values(array_map(
            static fn (PlayerCsvFormat $format): string => $format->formatKey(),
            $this->formats
        ));
    }

    public function byKey(string $key): ?PlayerCsvFormat
    {
        foreach ($this->formats as $format) {
            if ($format->formatKey() === $key) {
                return $format;
            }
        }

        return null;
    }
}
