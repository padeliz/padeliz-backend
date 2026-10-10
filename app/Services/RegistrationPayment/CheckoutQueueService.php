<?php

declare(strict_types=1);

namespace App\Services\RegistrationPayment;

use App\Enums\Registration\CheckoutStatus;
use App\Enums\Registration\PaymentAttemptStatus;
use App\Enums\Registration\PaymentProofReviewStatus;
use App\Enums\Registration\PaymentRefundStatus;
use App\Enums\Registration\RegistrationPaymentStatus;
use App\Models\EventRegistrationCheckout;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

class CheckoutQueueService
{
    public const QUEUE_AWAITING_PROOF = 'awaiting_proof';

    public const QUEUE_REFUND_PENDING = 'refund_pending';

    public const QUEUE_ABANDONED_NETOPIA = 'abandoned_netopia';

    /**
     * @return list<string>
     */
    public static function allowedQueues(): array
    {
        return [
            self::QUEUE_AWAITING_PROOF,
            self::QUEUE_REFUND_PENDING,
            self::QUEUE_ABANDONED_NETOPIA,
        ];
    }

    public function applyQueue(Builder $query, string $queue): Builder
    {
        return match ($queue) {
            self::QUEUE_AWAITING_PROOF => $this->scopeAwaitingProof($query),
            self::QUEUE_REFUND_PENDING => $this->scopeRefundPending($query),
            self::QUEUE_ABANDONED_NETOPIA => $this->scopeAbandonedNetopia($query),
            default => throw new InvalidArgumentException('Invalid checkout queue.'),
        };
    }

    /**
     * @return array{awaiting_proof: int, refund_pending: int, abandoned_netopia: int}
     */
    public function counts(): array
    {
        return [
            self::QUEUE_AWAITING_PROOF => (int) $this->scopeAwaitingProof(EventRegistrationCheckout::query())->count(),
            self::QUEUE_REFUND_PENDING => (int) $this->scopeRefundPending(EventRegistrationCheckout::query())->count(),
            self::QUEUE_ABANDONED_NETOPIA => (int) $this->scopeAbandonedNetopia(EventRegistrationCheckout::query())->count(),
        ];
    }

    private function scopeAwaitingProof(Builder $query): Builder
    {
        return $query->where('status', CheckoutStatus::AwaitingProof->value)
            ->whereHas('items', static function ($items): void {
                $items->where('item_status', \App\Enums\Registration\CheckoutItemStatus::Active->value)
                    ->whereHas('registration', static function ($regs): void {
                        $regs->where('lifecycle_status', \App\Enums\Registration\RegistrationLifecycleStatus::Active->value)
                            ->where('payment_status', RegistrationPaymentStatus::AwaitingProofReview->value);
                    });
            })
            ->whereHas('paymentProofs', static function ($proofs): void {
                $proofs->where('review_status', PaymentProofReviewStatus::Pending->value);
            });
    }

    private function scopeRefundPending(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereHas('items.registration', static function ($regs): void {
                $regs->where('payment_status', RegistrationPaymentStatus::RefundPending->value);
            })->orWhereHas('refunds', static function ($refunds): void {
                $refunds->whereIn('status', [
                    PaymentRefundStatus::Pending->value,
                    PaymentRefundStatus::Failed->value,
                ]);
            });
        });
    }

    private function scopeAbandonedNetopia(Builder $query): Builder
    {
        return $query->where('status', CheckoutStatus::Open->value)
            ->whereHas('paymentAttempts', static function ($attempts): void {
                $attempts->whereIn('status', [
                    PaymentAttemptStatus::Pending->value,
                    PaymentAttemptStatus::Failed->value,
                ]);
            });
    }
}
