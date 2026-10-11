<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Models\EventDivision;
use App\Models\EventRegistration;
use App\Models\EventRegistrationCheckout;
use App\Models\PadelEvent;
use App\Models\User;
use App\Services\EventCalendarTiming;
use Carbon\Carbon;

final class PricingContext
{
    /**
     * @param  list<int>  $selectedBenefitIds
     * @param  list<\App\Models\ReferralTouchpoint>  $touchpoints
     */
    public function __construct(
        public readonly User $player,
        public readonly PadelEvent $event,
        public readonly EventDivision $eventDivision,
        public readonly EventRegistration $registration,
        public readonly ?EventRegistrationCheckout $checkout,
        public readonly int $now,
        public readonly array $selectedBenefitIds = [],
        public readonly ?int $selectedReferrerPlayerId = null,
        public readonly array $touchpoints = [],
        public readonly bool $hasReferrerSelected = false,
        public readonly bool $lockNonStudentBenefits = false,
    ) {
    }

    public function daysBeforeEditionDate(): int
    {
        $tz = EventCalendarTiming::timezone();
        $today = Carbon::createFromTimestamp($this->now, $tz)->startOfDay();
        $editionDate = Carbon::createFromTimestamp((int) $this->event->event_starts_at, $tz)->startOfDay();

        return (int) $today->diffInDays($editionDate, false);
    }
}
