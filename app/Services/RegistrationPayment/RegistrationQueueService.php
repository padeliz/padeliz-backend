<?php

declare(strict_types=1);

namespace App\Services\RegistrationPayment;

use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * @deprecated Prefer CheckoutQueueService for manager payment ops queues.
 * Kept for registration-scoped list filters during migration.
 */
class RegistrationQueueService
{
    public const QUEUE_AWAITING_PROOF = CheckoutQueueService::QUEUE_AWAITING_PROOF;

    public const QUEUE_REFUND_PENDING = CheckoutQueueService::QUEUE_REFUND_PENDING;

    public const QUEUE_ABANDONED_NETOPIA = CheckoutQueueService::QUEUE_ABANDONED_NETOPIA;

    public function __construct(
        private readonly CheckoutQueueService $checkoutQueueService,
    ) {
    }

    /**
     * @return list<string>
     */
    public static function allowedQueues(): array
    {
        return CheckoutQueueService::allowedQueues();
    }

    public function applyQueue(Builder $query, string $queue): Builder
    {
        return match ($queue) {
            self::QUEUE_AWAITING_PROOF => $query->queueAwaitingProof(),
            self::QUEUE_REFUND_PENDING => $query->queueRefundPending(),
            self::QUEUE_ABANDONED_NETOPIA => $query->queueAbandonedNetopia(),
            default => throw new InvalidArgumentException('Invalid registration queue.'),
        };
    }

    /**
     * @return array{awaiting_proof: int, refund_pending: int, abandoned_netopia: int}
     */
    public function counts(): array
    {
        return $this->checkoutQueueService->counts();
    }
}
