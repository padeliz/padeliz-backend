<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Models\PadelEvent;
use App\Models\EventDivision;
use App\Models\EventRegistration;
use App\Models\EventRegistrationCheckout;
use App\Models\Eligibility;
use App\Models\Player;
use Carbon\Carbon;

final class PricingContext
{
    /**
     * @param  list<int>  $selectedBenefitIds
     * @param  list<\App\Models\ReferralTouchpoint>  $touchpoints
     */
    public function __construct(
        public readonly Player $player,
        public readonly PadelEvent $event,
        public readonly EventDivision $eventDivision,
        public readonly EventRegistration $registration,
        public readonly ?EventRegistrationCheckout $checkout,
        public readonly ?Eligibility $eligibility,
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
        $tz = (string) config('app.timezone', 'UTC');
        $today = Carbon::createFromTimestamp($this->now, $tz)->startOfDay();
        $editionDate = Carbon::parse((string) $this->event->date, $tz)->startOfDay();

        return (int) $today->diffInDays($editionDate, false);
    }
}
