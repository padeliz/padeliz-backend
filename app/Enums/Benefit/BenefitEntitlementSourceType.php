<?php

declare(strict_types=1);

namespace App\Enums\Benefit;

enum BenefitEntitlementSourceType: string
{
    case ManagerGrant = 'manager_grant';
    case ReferralReward = 'referral_reward';
    case System = 'system';

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
