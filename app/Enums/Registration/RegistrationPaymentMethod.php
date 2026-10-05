<?php

declare(strict_types=1);

namespace App\Enums\Registration;

enum RegistrationPaymentMethod: string
{
    case Netopia = 'netopia';
    case BankTransfer = 'bank_transfer';

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
