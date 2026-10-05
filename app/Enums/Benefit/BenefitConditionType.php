<?php

declare(strict_types=1);

namespace App\Enums\Benefit;

enum BenefitConditionType: string
{
    case EligibilityFlag = 'eligibility_flag';
    case DaysBeforeEditionDate = 'days_before_edition_date';
    case ReferrerSelected = 'referrer_selected';
    case NoPriorPaidRegistrationInEdition = 'no_prior_paid_registration_in_edition';

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
