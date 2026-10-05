<?php

declare(strict_types=1);

namespace App\Enums\Registration;

enum RegistrationCancelReason: string
{
    case ChangeOfPlans = 'change_of_plans';
    case ScheduleConflict = 'schedule_conflict';
    case InjuryOrIllness = 'injury_or_illness';
    case TravelIssue = 'travel_issue';
    case PersonalReasons = 'personal_reasons';
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
