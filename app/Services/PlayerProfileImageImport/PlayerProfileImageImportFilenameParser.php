<?php

declare(strict_types=1);

namespace App\Services\PlayerProfileImageImport;

final class PlayerProfileImageImportFilenameParser
{
    public const TYPE_EMAIL = 'email';

    public const TYPE_PHONE = 'phone';

    public const TYPE_ID = 'id';

    public const TYPE_NAME = 'name';

    public const TYPE_UNKNOWN = 'unknown';

    /**
     * @return array{type: string, stem: string}
     */
    public function parse(string $filename): array
    {
        $stem = pathinfo($filename, PATHINFO_FILENAME);
        $stem = trim($stem);

        if ($stem === '') {
            return ['type' => self::TYPE_UNKNOWN, 'stem' => ''];
        }

        if (filter_var($stem, FILTER_VALIDATE_EMAIL) !== false) {
            return ['type' => self::TYPE_EMAIL, 'stem' => $stem];
        }

        $normalizedPhone = \App\Support\PlayerPhoneNormalizer::normalize($stem);
        if ($normalizedPhone !== '' && preg_match('/^\+?\d{6,15}$/', $normalizedPhone) === 1) {
            return ['type' => self::TYPE_PHONE, 'stem' => $normalizedPhone];
        }

        if (preg_match('/^\d+$/', $stem) === 1) {
            return ['type' => self::TYPE_ID, 'stem' => $stem];
        }

        if (preg_match('/[\p{L}]/u', $stem) === 1) {
            return ['type' => self::TYPE_NAME, 'stem' => $stem];
        }

        return ['type' => self::TYPE_UNKNOWN, 'stem' => $stem];
    }
}
