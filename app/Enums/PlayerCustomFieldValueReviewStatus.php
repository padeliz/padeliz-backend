<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Review state of one custom field value. Assessment approval maps to `validated` here.
 */
enum PlayerCustomFieldValueReviewStatus: string
{
    case Pending = 'pending';
    case Validated = 'validated';
    case Rejected = 'rejected';
    case Discarded = 'discarded';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $case): string => $case->value,
            self::cases()
        );
    }
}
