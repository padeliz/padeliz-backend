<?php

declare(strict_types=1);

namespace App\Services\UserImport;

use App\Services\UserImport\Contracts\UserCsvFormat;

final class UserCsvFormatRegistry
{
    /**
     * @param  list<UserCsvFormat>  $formats
     */
    public function __construct(
        private readonly array $formats
    ) {
    }

    /**
     * @param  list<string>  $headers
     */
    public function detect(array $headers): ?UserCsvFormat
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
            static fn (UserCsvFormat $format): string => $format->formatKey(),
            $this->formats
        ));
    }

    public function byKey(string $key): ?UserCsvFormat
    {
        foreach ($this->formats as $format) {
            if ($format->formatKey() === $key) {
                return $format;
            }
        }

        return null;
    }
}
