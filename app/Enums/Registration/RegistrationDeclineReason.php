<?php

declare(strict_types=1);

namespace App\Enums\Registration;

enum RegistrationDeclineReason: string
{
    case PaymentNotCompleted = 'payment_not_completed';
    case InvalidPayment = 'invalid_payment';
    case Ineligible = 'ineligible';
    case SkillMismatch = 'skill_mismatch';
    case FalseInformation = 'false_information';
    case Misconduct = 'misconduct';
    case Administrative = 'administrative';
    case Other = 'other';

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
