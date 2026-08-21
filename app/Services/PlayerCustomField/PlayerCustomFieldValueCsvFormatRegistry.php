<?php

declare(strict_types=1);

namespace App\Services\PlayerCustomField;

use App\Services\PlayerCustomField\Contracts\PlayerCustomFieldValueCsvFormat;

final class PlayerCustomFieldValueCsvFormatRegistry
{
    /**
     * @param  list<PlayerCustomFieldValueCsvFormat>  $formats
     */
    public function __construct(
        private readonly array $formats
    ) {
    }

    /**
     * @param  list<string>  $headers
     */
    public function detect(array $headers): ?PlayerCustomFieldValueCsvFormat
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
            static fn (PlayerCustomFieldValueCsvFormat $format): string => $format->formatKey(),
            $this->formats
        ));
    }

    public function byKey(string $key): ?PlayerCustomFieldValueCsvFormat
    {
        foreach ($this->formats as $format) {
            if ($format->formatKey() === $key) {
                return $format;
            }
        }

        return null;
    }
}
