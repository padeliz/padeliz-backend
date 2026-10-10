<?php

declare(strict_types=1);

namespace App\Services\Referral;

use App\Enums\Benefit\BenefitEntitlementStatus;
use App\Enums\Referral\ReferralAttributionStatus;
use App\Models\BenefitEntitlement;
use App\Models\EventParticipation;
use App\Models\EventReferralAttribution;
use App\Models\EventReferralCode;
use App\Models\EventReferralCodeInvitee;
use App\Models\EventReferralSetting;
use App\Models\EventRegistration;
use App\Models\EventRegistrationCheckout;
use App\Models\PadelEvent;
use App\Models\Player;
use App\Models\PlayerEmail;
use App\Models\PlayerPhone;
use App\Models\ReferralTouchpoint;
use App\Services\PlayerEventHistoryService;
use App\Support\PlayerPhoneNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class ReferralService
{
    public function effectiveTtlHours(int $editionId): int
    {
        $override = EventReferralSetting::query()->where('event_id', $editionId)->value('code_ttl_hours');

        return $override !== null
            ? (int) $override
            : (int) config('referral.code_ttl_hours', 24);
    }

    public function remainingSlots(int $referrerPlayerId, int $editionId): int
    {
        $cap = (int) config('referral.edition_discount_cap', 6);
        $used = EventReferralAttribution::query()
            ->where('event_id', $editionId)
            ->where('referrer_player_id', $referrerPlayerId)
            ->whereIn('status', [
                ReferralAttributionStatus::Pending->value,
                ReferralAttributionStatus::Confirmed->value,
            ])
            ->count();

        return max(0, $cap - $used);
    }

    /**
     * @param  list<array{email?: ?string, phone?: ?string, player_id?: ?int}>  $invitees
     */
    public function createCode(Player $referrer, int $editionId, array $invitees): EventReferralCode
    {
        PadelEvent::query()->findOrFail($editionId);

        if (! $this->hasCleanParticipation((int) $referrer->id_player)) {
            throw new InvalidArgumentException('Only players with at least one clean past participation may create referral codes.');
        }

        $remaining = $this->remainingSlots((int) $referrer->id_player, $editionId);
        if ($remaining <= 0) {
            throw new InvalidArgumentException('No referral discount slots remaining for this edition.');
        }

        $count = count($invitees);
        if ($count < 1 || $count > min(6, $remaining)) {
            throw new InvalidArgumentException(sprintf(
                'Allowlist must contain between 1 and %d invitees.',
                min(6, $remaining)
            ));
        }

        $normalized = [];
        $seen = [];
        foreach ($invitees as $invitee) {
            $email = isset($invitee['email']) ? $this->normalizeEmail((string) $invitee['email']) : null;
            $phone = isset($invitee['phone']) ? $this->normalizePhone((string) $invitee['phone']) : null;
            $playerId = isset($invitee['player_id']) ? (int) $invitee['player_id'] : null;

            if ($email === null && $phone === null && $playerId === null) {
                throw new InvalidArgumentException('Each invitee requires email and/or phone (or player_id).');
            }

            if ($playerId !== null) {
                $player = Player::query()->findOrFail($playerId);
                if ((int) $player->id_player === (int) $referrer->id_player) {
                    throw new InvalidArgumentException('Cannot invite yourself.');
                }
                $email = $email ?? PlayerEmail::query()->where('player_id', $playerId)->value('email');
                $phone = $phone ?? PlayerPhone::query()->where('player_id', $playerId)->value('phone');
                $email = $email ? $this->normalizeEmail((string) $email) : null;
                $phone = $phone ? $this->normalizePhone((string) $phone) : null;
            } else {
                if ($email) {
                    $playerId = PlayerEmail::query()->where('email', $email)->value('player_id');
                }
                if ($playerId === null && $phone) {
                    $playerId = PlayerPhone::query()->where('phone', $phone)->value('player_id');
                }
                if ($playerId !== null && (int) $playerId === (int) $referrer->id_player) {
                    throw new InvalidArgumentException('Cannot invite yourself.');
                }
            }

            if ($email === null && $phone === null) {
                throw new InvalidArgumentException('Each invitee requires email and/or phone.');
            }

            $key = ($email ?? '') . '|' . ($phone ?? '') . '|' . ($playerId ?? '');
            if (isset($seen[$key])) {
                throw new InvalidArgumentException('Duplicate invitees on the same code are not allowed.');
            }
            $seen[$key] = true;
            $normalized[] = [
                'email' => $email,
                'phone' => $phone,
                'player_id' => $playerId,
            ];
        }

        $ttl = $this->effectiveTtlHours($editionId);
        $now = time();

        return DB::transaction(function () use ($referrer, $editionId, $normalized, $ttl, $now) {
            $code = EventReferralCode::query()->create([
                'event_id' => $editionId,
                'referrer_player_id' => $referrer->id_player,
                'code' => Str::upper(Str::random(8)),
                'expires_at' => $now + ($ttl * 3600),
                'revoked_at' => null,
            ]);

            foreach ($normalized as $row) {
                EventReferralCodeInvitee::query()->create([
                    'referral_code_id' => $code->id_referral_code,
                    'email' => $row['email'],
                    'phone' => $row['phone'],
                    'player_id' => $row['player_id'],
                ]);
            }

            return $code->load('invitees');
        });
    }

    public function recordTouchpoint(Player $player, string $code, int $editionId, ?array $clientMetadata = null): ReferralTouchpoint
    {
        $referralCode = EventReferralCode::query()
            ->where('event_id', $editionId)
            ->where('code', $code)
            ->first();

        if (! $referralCode) {
            throw new InvalidArgumentException('Unknown referral code.');
        }
        if (! $referralCode->isUsable()) {
            throw new InvalidArgumentException('Referral code is expired or revoked.');
        }
        if ((int) $referralCode->referrer_player_id === (int) $player->id_player) {
            throw new InvalidArgumentException('Self-referral is not allowed.');
        }

        $eligible = $this->playerMatchesInvitees($player, $referralCode);

        return ReferralTouchpoint::query()->create([
            'event_id' => $editionId,
            'referred_player_id' => $player->id_player,
            'referrer_player_id' => $referralCode->referrer_player_id,
            'referral_code_id' => $referralCode->id_referral_code,
            'discount_eligible' => $eligible,
            'touched_at' => time(),
            'client_metadata' => $clientMetadata,
        ]);
    }

    /**
     * @return list<array{referrer_player_id: int, latest_touched_at: int}>
     */
    public function candidatesForCheckout(Player $player, int $editionId): array
    {
        $rows = ReferralTouchpoint::query()
            ->where('event_id', $editionId)
            ->where('referred_player_id', $player->id_player)
            ->where('discount_eligible', true)
            ->orderByDesc('touched_at')
            ->get();

        $pending = EventReferralAttribution::query()
            ->where('event_id', $editionId)
            ->where('referred_player_id', $player->id_player)
            ->where('status', ReferralAttributionStatus::Pending->value)
            ->first();

        $out = [];
        $seen = [];
        foreach ($rows as $row) {
            $rid = (int) $row->referrer_player_id;
            if (isset($seen[$rid])) {
                continue;
            }
            $hasSlot = $this->remainingSlots($rid, $editionId) > 0
                || ($pending && (int) $pending->referrer_player_id === $rid);
            if (! $hasSlot) {
                continue;
            }
            $seen[$rid] = true;
            $out[] = [
                'referrer_player_id' => $rid,
                'latest_touched_at' => (int) $row->touched_at,
            ];
        }

        return $out;
    }

    public function commitAttribution(
        EventRegistrationCheckout $checkout,
        int $referrerPlayerId,
    ): EventReferralAttribution {
        $editionId = (int) $checkout->event_id;
        $referredId = (int) $checkout->player_id;

        if ($referrerPlayerId === $referredId) {
            throw new InvalidArgumentException('Self-referral is not allowed.');
        }

        $candidates = collect($this->candidatesForCheckout(
            Player::query()->findOrFail($referredId),
            $editionId
        ))->pluck('referrer_player_id')->all();

        if (! in_array($referrerPlayerId, $candidates, true)) {
            throw new InvalidArgumentException('Selected referrer is not an eligible candidate.');
        }

        return DB::transaction(function () use ($checkout, $editionId, $referredId, $referrerPlayerId) {
            EventReferralAttribution::query()
                ->where('event_id', $editionId)
                ->where('referred_player_id', $referredId)
                ->where('status', ReferralAttributionStatus::Pending->value)
                ->where('referrer_player_id', '!=', $referrerPlayerId)
                ->delete();

            $existing = EventReferralAttribution::query()
                ->where('event_id', $editionId)
                ->where('referred_player_id', $referredId)
                ->lockForUpdate()
                ->first();

            if ($existing && $existing->status === ReferralAttributionStatus::Confirmed) {
                throw new InvalidArgumentException('Referral attribution is already confirmed for this edition.');
            }
            if ($existing && $existing->status === ReferralAttributionStatus::Released) {
                throw new InvalidArgumentException('Referral discount was already used for this edition.');
            }

            if ($existing && (int) $existing->referrer_player_id === $referrerPlayerId) {
                $existing->checkout_id = $checkout->id_checkout;
                $existing->save();

                return $existing;
            }

            if ($this->remainingSlots($referrerPlayerId, $editionId) <= 0) {
                throw new InvalidArgumentException('Selected referrer has no remaining discount slots.');
            }

            if ($existing) {
                $existing->delete();
            }

            return EventReferralAttribution::query()->create([
                'event_id' => $editionId,
                'referred_player_id' => $referredId,
                'referrer_player_id' => $referrerPlayerId,
                'referral_code_id' => null,
                'checkout_id' => $checkout->id_checkout,
                'status' => ReferralAttributionStatus::Pending->value,
            ]);
        });
    }

    public function clearPendingAttribution(int $editionId, int $playerId): void
    {
        EventReferralAttribution::query()
            ->where('event_id', $editionId)
            ->where('referred_player_id', $playerId)
            ->where('status', ReferralAttributionStatus::Pending->value)
            ->delete();
    }

    public function releaseOnRefund(EventRegistration $registration): void
    {
        $attribution = EventReferralAttribution::query()
            ->where('event_id', $registration->event_id)
            ->where('referred_player_id', $registration->player_id)
            ->where('status', ReferralAttributionStatus::Confirmed->value)
            ->first();

        if (! $attribution) {
            return;
        }

        $attribution->status = ReferralAttributionStatus::Released->value;
        $attribution->save();

        $entitlements = BenefitEntitlement::query()
            ->where('source_type', 'referral_reward')
            ->where('source_id', $attribution->id_attribution)
            ->where('status', BenefitEntitlementStatus::Active->value)
            ->get();

        foreach ($entitlements as $entitlement) {
            $entitlement->status = BenefitEntitlementStatus::Revoked;
            $entitlement->save();
        }
    }

    private function hasCleanParticipation(int $playerId): bool
    {
        $history = app(PlayerEventHistoryService::class);

        return EventParticipation::query()
            ->where('player_id', $playerId)
            ->get()
            ->contains(static fn (EventParticipation $row): bool => $history->isClean($row));
    }

    private function playerMatchesInvitees(Player $player, EventReferralCode $code): bool
    {
        $emails = PlayerEmail::query()->where('player_id', $player->id_player)->pluck('email')
            ->map(fn ($e) => $this->normalizeEmail((string) $e))->filter()->all();
        $phones = PlayerPhone::query()->where('player_id', $player->id_player)->pluck('phone')
            ->map(fn ($p) => $this->normalizePhone((string) $p))->filter()->all();

        foreach ($code->invitees as $invitee) {
            if ($invitee->player_id && (int) $invitee->player_id === (int) $player->id_player) {
                return true;
            }
            if ($invitee->email && in_array($this->normalizeEmail((string) $invitee->email), $emails, true)) {
                return true;
            }
            if ($invitee->phone && in_array($this->normalizePhone((string) $invitee->phone), $phones, true)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeEmail(string $email): ?string
    {
        $email = strtolower(trim($email));

        return $email === '' ? null : $email;
    }

    private function normalizePhone(string $phone): ?string
    {
        $normalized = PlayerPhoneNormalizer::normalize($phone);

        return $normalized === '' ? null : $normalized;
    }
}
