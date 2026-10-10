<?php

declare(strict_types=1);

namespace App\Enums\PlayerLevel;

enum AssessmentReviewStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
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

    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }
}
