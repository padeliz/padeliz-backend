<?php

declare(strict_types=1);

namespace App\Enums\Registration;

enum CheckoutStatus: string
{
    case Open = 'open';
    case AwaitingProof = 'awaiting_proof';
    case Completed = 'completed';

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

    public function isOpen(): bool
    {
        return $this === self::Open || $this === self::AwaitingProof;
    }
}
