<?php

declare(strict_types=1);

namespace App\Services\RegistrationPayment;

use App\Enums\Registration\CheckoutItemStatus;
use App\Enums\Registration\CheckoutStatus;
use App\Enums\Registration\PaymentAttemptStatus;
use App\Enums\Registration\PaymentRefundStatus;
use App\Enums\Registration\RegistrationLifecycleStatus;
use App\Enums\Registration\RegistrationPaymentMethod;
use App\Enums\Registration\RegistrationPaymentStatus;
use App\Models\EventRegistration;
use App\Models\EventRegistrationCheckout;
use App\Models\EventRegistrationCheckoutItem;
use App\Models\EventRegistrationPaymentAttempt;
use App\Models\EventRegistrationPaymentRefund;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;

class NetopiaPaymentService
{
    public function __construct(
        private readonly RegistrationService $registrationService,
        private readonly CheckoutService $checkoutService,
    ) {
    }

    public function startForCheckout(EventRegistrationCheckout $checkout): EventRegistrationPaymentAttempt
    {
        return DB::transaction(function () use ($checkout) {
            $checkout = $this->checkoutService->lockCheckout((int) $checkout->id_checkout);

            if (! $checkout->status->isOpen()) {
                throw new InvalidArgumentException('Checkout is not open for payment.');
            }

            $this->assertPlayerPaymentWindowOpen($checkout);
            $this->revalidateStudentBenefits($checkout);

            $checkout = $checkout->fresh(['items.registration']) ?? $checkout;
            $amountDue = $this->checkoutService->payableTotal($checkout);
            if ($amountDue <= 0) {
                throw new InvalidArgumentException('Amount due must be greater than zero for Netopia checkout.');
            }

            // Supersede in-flight attempts whose amount no longer matches post-revalidation totals.
            EventRegistrationPaymentAttempt::query()
                ->where('checkout_id', $checkout->id_checkout)
                ->whereIn('status', [
                    PaymentAttemptStatus::Started->value,
                    PaymentAttemptStatus::Pending->value,
                ])
                ->where('amount', '!=', $amountDue)
                ->update([
                    'status' => PaymentAttemptStatus::Superseded->value,
                    'updated_at' => time(),
                ]);

            $checkout->loadMissing('player', 'event');
            $orderId = 'PAD-C' . $checkout->id_checkout . '-' . Str::lower(Str::random(12));

            $attempt = EventRegistrationPaymentAttempt::query()->create([
                'checkout_id' => $checkout->id_checkout,
                'amount' => $amountDue,
                'currency' => 'RON',
                'status' => PaymentAttemptStatus::Started,
                'order_id' => $orderId,
                'coverage' => $this->checkoutService->coverageSnapshot($checkout),
            ]);

            if ((int) $attempt->amount !== $this->checkoutService->payableTotal($checkout)) {
                throw new InvalidArgumentException('Payment attempt amount does not match checkout total after revalidation.');
            }

            $payload = [
                'config' => [
                    'notifyUrl' => (string) config('netopia.notify_url'),
                    'redirectUrl' => (string) config('netopia.return_url'),
                    'language' => 'RO',
                ],
                'order' => [
                    'posSignature' => (string) config('netopia.pos_signature'),
                    'dateTime' => now()->toIso8601String(),
                    'description' => 'Padeliz checkout #' . $checkout->id_checkout,
                    'orderID' => $orderId,
                    'amount' => $amountDue,
                    'currency' => 'RON',
                    'billing' => [
                        'email' => (string) ($checkout->player->email ?? 'player@padeliz.ro'),
                        'phone' => (string) ($checkout->player->phone ?? '0700000000'),
                        'firstName' => (string) ($checkout->player->first_name ?? 'Player'),
                        'lastName' => (string) ($checkout->player->last_name ?? 'Padeliz'),
                        'city' => 'Bucuresti',
                        'country' => 642,
                    ],
                ],
            ];

            $startUrl = config('netopia.is_live')
                ? (string) config('netopia.live_start_url')
                : (string) config('netopia.sandbox_start_url');

            $response = Http::withHeaders([
                'Authorization' => (string) config('netopia.api_key'),
                'Content-Type' => 'application/json',
            ])->post($startUrl, $payload);

            $body = $response->json() ?? [];
            $attempt->provider_payload = $body;

            if (! $response->successful()) {
                $attempt->status = PaymentAttemptStatus::Failed;
                $attempt->error_message = is_string($body['message'] ?? null)
                    ? $body['message']
                    : (is_string($body['error']['message'] ?? null) ? $body['error']['message'] : 'Netopia start failed');
                $attempt->save();

                throw new InvalidArgumentException('Unable to start Netopia payment.');
            }

            $root = is_array($body['data'] ?? null) ? $body['data'] : $body;
            $payment = is_array($root['payment'] ?? null) ? $root['payment'] : [];
            $customerAction = is_array($root['customerAction'] ?? null) ? $root['customerAction'] : [];

            $attempt->ntp_id = isset($payment['ntpID']) ? (string) $payment['ntpID'] : null;
            $redirectUrl = null;
            if (isset($customerAction['url']) && is_string($customerAction['url']) && $customerAction['url'] !== '') {
                $redirectUrl = $customerAction['url'];
            } elseif (isset($payment['paymentURL']) && is_string($payment['paymentURL']) && $payment['paymentURL'] !== '') {
                $redirectUrl = $payment['paymentURL'];
            } elseif (isset($root['url']) && is_string($root['url']) && $root['url'] !== '') {
                $redirectUrl = $root['url'];
            }

            $attempt->redirect_url = $redirectUrl;
            $attempt->status = PaymentAttemptStatus::Pending;
            $attempt->save();

            if ($redirectUrl === null) {
                $attempt->status = PaymentAttemptStatus::Failed;
                $attempt->error_message = 'Netopia did not return a payment page URL.';
                $attempt->save();

                throw new InvalidArgumentException('Unable to start Netopia payment.');
            }

            foreach ($this->checkoutService->payableActiveItems($checkout) as $item) {
                $reg = $item->registration;
                if ($reg !== null) {
                    $reg->payment_method = RegistrationPaymentMethod::Netopia;
                    $reg->save();
                }
            }
            $checkout->payment_method = RegistrationPaymentMethod::Netopia;
            $checkout->save();

            return $attempt->refresh();
        });
    }

    /** @deprecated Prefer startForCheckout */
    public function start(EventRegistration $registration): EventRegistrationPaymentAttempt
    {
        $checkout = $this->checkoutService->openCheckoutForRegistration($registration)
            ?? $this->checkoutService->openOrRefreshForUnpaid(
                (int) $registration->event_id,
                (int) $registration->player_id
            );

        return $this->startForCheckout($checkout);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handleIpn(array $payload): EventRegistrationPaymentAttempt
    {
        if (! $this->verifySignature($payload)) {
            throw new InvalidArgumentException('Invalid Netopia IPN signature.');
        }

        return DB::transaction(function () use ($payload) {
            $orderId = (string) ($payload['orderID'] ?? $payload['order']['orderID'] ?? $payload['data']['order']['orderID'] ?? '');
            $ntpId = (string) ($payload['ntpID'] ?? $payload['payment']['ntpID'] ?? $payload['data']['payment']['ntpID'] ?? '');
            $status = (int) ($payload['status'] ?? $payload['payment']['status'] ?? $payload['data']['payment']['status'] ?? -1);
            $errorCode = (string) ($payload['error']['code'] ?? $payload['data']['error']['code'] ?? '');

            $attempt = EventRegistrationPaymentAttempt::query()
                ->when($orderId !== '', fn ($q) => $q->where('order_id', $orderId))
                ->when($orderId === '' && $ntpId !== '', fn ($q) => $q->where('ntp_id', $ntpId))
                ->first();

            if ($attempt === null) {
                throw new InvalidArgumentException('Payment attempt not found for IPN.');
            }

            $checkout = $this->checkoutService->lockCheckout((int) $attempt->checkout_id);

            // Late IPN is allowed when the attempt was created before payment_due_at.
            $this->registrationService->assertCheckoutPaymentWindowOpen(
                $checkout,
                $attempt->inserted_at !== null ? (int) $attempt->inserted_at : null
            );

            if ($attempt->status === PaymentAttemptStatus::Succeeded) {
                return $attempt;
            }

            $attempt->ntp_id = $ntpId !== '' ? $ntpId : $attempt->ntp_id;
            $attempt->provider_payload = array_merge($attempt->provider_payload ?? [], ['ipn' => $payload]);

            $isSuccess = $errorCode === '00' || $errorCode === '0' || $status === 3;

            if (! $isSuccess) {
                $attempt->status = PaymentAttemptStatus::Failed;
                $attempt->error_message = (string) ($payload['error']['message'] ?? $payload['data']['error']['message'] ?? 'Payment failed');
                $attempt->save();

                return $attempt->refresh();
            }

            $attempt->status = PaymentAttemptStatus::Succeeded;
            $attempt->save();

            $payable = $this->checkoutService->payableActiveItems($checkout);
            $alreadyCovered = $payable->isEmpty() && $checkout->status === CheckoutStatus::Completed;

            if ($alreadyCovered) {
                $this->refundFullCapture($checkout, $attempt);
                $this->supersedeOtherStartedAttempts($checkout, (int) $attempt->id_payment_attempt);

                return $attempt->refresh();
            }

            $payableTotal = $this->checkoutService->payableTotal($checkout);
            if ($payable->isNotEmpty() && (int) $attempt->amount !== $payableTotal) {
                $this->refundFullCapture($checkout, $attempt);
                $this->supersedeOtherStartedAttempts($checkout, (int) $attempt->id_payment_attempt);

                return $attempt->refresh();
            }

            $cancelledItems = EventRegistrationCheckoutItem::query()
                ->where('checkout_id', $checkout->id_checkout)
                ->where('item_status', CheckoutItemStatus::Cancelled->value)
                ->where('amount', '>', 0)
                ->get();

            if ($payable->isNotEmpty()) {
                $this->checkoutService->markPayableItemsPaid($checkout, RegistrationPaymentMethod::Netopia->value);
                foreach ($cancelledItems as $item) {
                    $this->refundItemAmount($checkout, $attempt, $item);
                }
            } else {
                // No remaining payable actives — refund the full capture.
                $this->refundFullCapture($checkout, $attempt);
            }

            $this->supersedeOtherStartedAttempts($checkout, (int) $attempt->id_payment_attempt);

            return $attempt->refresh();
        });
    }

    public function refundItemAmount(
        EventRegistrationCheckout $checkout,
        EventRegistrationPaymentAttempt $attempt,
        EventRegistrationCheckoutItem $item
    ): EventRegistrationPaymentRefund {
        $existing = EventRegistrationPaymentRefund::query()
            ->where('checkout_id', $checkout->id_checkout)
            ->where('checkout_item_id', $item->id_checkout_item)
            ->where('payment_attempt_id', $attempt->id_payment_attempt)
            ->where('status', PaymentRefundStatus::Succeeded->value)
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        $amount = (int) $item->amount;
        $refund = EventRegistrationPaymentRefund::query()->create([
            'checkout_id' => $checkout->id_checkout,
            'checkout_item_id' => $item->id_checkout_item,
            'payment_attempt_id' => $attempt->id_payment_attempt,
            'amount' => $amount,
            'currency' => 'RON',
            'status' => PaymentRefundStatus::Pending,
        ]);

        $result = $this->callNetopiaRefund($attempt, $amount, 'item-' . $item->id_checkout_item);
        $refund->provider_payload = $result['payload'];
        $refund->provider_refund_id = $result['refund_id'];
        $refund->error_message = $result['error'];
        $refund->status = $result['ok'] ? PaymentRefundStatus::Succeeded : PaymentRefundStatus::Failed;
        $refund->save();

        $reg = EventRegistration::query()->find($item->registration_id);
        if ($reg !== null) {
            $reg->payment_status = $result['ok']
                ? RegistrationPaymentStatus::Refunded
                : RegistrationPaymentStatus::RefundPending;
            if ($reg->paid_at === null) {
                $reg->paid_at = time();
            }
            $reg->payment_method = RegistrationPaymentMethod::Netopia;
            $reg->save();
        }

        return $refund->refresh();
    }

    public function refundFullCapture(
        EventRegistrationCheckout $checkout,
        EventRegistrationPaymentAttempt $attempt
    ): EventRegistrationPaymentRefund {
        $existing = EventRegistrationPaymentRefund::query()
            ->where('checkout_id', $checkout->id_checkout)
            ->whereNull('checkout_item_id')
            ->where('payment_attempt_id', $attempt->id_payment_attempt)
            ->where('status', PaymentRefundStatus::Succeeded->value)
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        $amount = (int) $attempt->amount;
        $refund = EventRegistrationPaymentRefund::query()->create([
            'checkout_id' => $checkout->id_checkout,
            'checkout_item_id' => null,
            'payment_attempt_id' => $attempt->id_payment_attempt,
            'amount' => $amount,
            'currency' => 'RON',
            'status' => PaymentRefundStatus::Pending,
        ]);

        $result = $this->callNetopiaRefund($attempt, $amount, 'full-' . $attempt->id_payment_attempt);
        $refund->provider_payload = $result['payload'];
        $refund->provider_refund_id = $result['refund_id'];
        $refund->error_message = $result['error'];
        $refund->status = $result['ok'] ? PaymentRefundStatus::Succeeded : PaymentRefundStatus::Failed;
        $refund->save();

        // Mark cancelled payable regs linked to this attempt's cancelled items
        $items = EventRegistrationCheckoutItem::query()
            ->where('checkout_id', $checkout->id_checkout)
            ->where('amount', '>', 0)
            ->get();
        foreach ($items as $item) {
            $reg = EventRegistration::query()->find($item->registration_id);
            if ($reg === null) {
                continue;
            }
            if ($reg->payment_status === RegistrationPaymentStatus::Refunded) {
                continue;
            }
            if (in_array($reg->lifecycle_status, [
                RegistrationLifecycleStatus::Cancelled,
                RegistrationLifecycleStatus::Declined,
            ], true)
                || $reg->payment_status === RegistrationPaymentStatus::RefundPending
            ) {
                $reg->payment_status = $result['ok']
                    ? RegistrationPaymentStatus::Refunded
                    : RegistrationPaymentStatus::RefundPending;
                $reg->payment_method = RegistrationPaymentMethod::Netopia;
                $reg->paid_at = $reg->paid_at ?? time();
                $reg->save();
            }
        }

        return $refund->refresh();
    }

    public function refundPaidRegistrationLine(EventRegistration $registration): void
    {
        $item = EventRegistrationCheckoutItem::query()
            ->where('registration_id', $registration->id_registration)
            ->where('item_status', CheckoutItemStatus::Cancelled->value)
            ->latest('id_checkout_item')
            ->first();

        if ($item === null) {
            $item = EventRegistrationCheckoutItem::query()
                ->where('registration_id', $registration->id_registration)
                ->latest('id_checkout_item')
                ->first();
        }

        if ($item === null) {
            $registration->payment_status = RegistrationPaymentStatus::RefundPending;
            $registration->save();

            return;
        }

        $checkout = EventRegistrationCheckout::query()->findOrFail($item->checkout_id);
        $attempt = EventRegistrationPaymentAttempt::query()
            ->where('checkout_id', $checkout->id_checkout)
            ->where('status', PaymentAttemptStatus::Succeeded->value)
            ->latest('id_payment_attempt')
            ->first();

        if ($attempt === null) {
            $registration->payment_status = RegistrationPaymentStatus::RefundPending;
            $registration->save();

            return;
        }

        $item->item_status = CheckoutItemStatus::Cancelled;
        $item->save();

        $this->refundItemAmount($checkout, $attempt, $item);
    }

    /**
     * @return array{ok: bool, payload: array<string, mixed>, refund_id: null|string, error: null|string}
     */
    private function callNetopiaRefund(EventRegistrationPaymentAttempt $attempt, int $amount, string $idempotencyKey): array
    {
        $ntpId = (string) ($attempt->ntp_id ?? '');
        if ($ntpId === '') {
            return [
                'ok' => false,
                'payload' => [],
                'refund_id' => null,
                'error' => 'Missing ntp_id for refund.',
            ];
        }

        $refundUrl = config('netopia.is_live')
            ? (string) config('netopia.live_refund_url', 'https://secure.mobilpay.ro/pay/payment/card/refund')
            : (string) config('netopia.sandbox_refund_url', 'https://secure.sandbox.netopia-payments.com/payment/card/refund');

        $payload = [
            'ntpID' => $ntpId,
            'amount' => $amount,
            'currency' => 'RON',
            'posSignature' => (string) config('netopia.pos_signature'),
            'idempotencyKey' => $idempotencyKey,
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => (string) config('netopia.api_key'),
                'Content-Type' => 'application/json',
            ])->post($refundUrl, $payload);

            $body = $response->json() ?? [];
            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'payload' => is_array($body) ? $body : [],
                    'refund_id' => null,
                    'error' => is_string($body['message'] ?? null)
                        ? $body['message']
                        : 'Netopia refund failed',
                ];
            }

            $refundId = isset($body['refundID']) ? (string) $body['refundID'] : (isset($body['ntpID']) ? (string) $body['ntpID'] : null);

            return [
                'ok' => true,
                'payload' => is_array($body) ? $body : [],
                'refund_id' => $refundId,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'payload' => [],
                'refund_id' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function supersedeOtherStartedAttempts(EventRegistrationCheckout $checkout, int $keepAttemptId): void
    {
        EventRegistrationPaymentAttempt::query()
            ->where('checkout_id', $checkout->id_checkout)
            ->where('id_payment_attempt', '!=', $keepAttemptId)
            ->whereIn('status', [
                PaymentAttemptStatus::Started->value,
                PaymentAttemptStatus::Pending->value,
            ])
            ->update([
                'status' => PaymentAttemptStatus::Superseded->value,
                'updated_at' => time(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function verifySignature(array $payload): bool
    {
        $expected = (string) config('netopia.pos_signature');
        if ($expected === '') {
            return true;
        }

        $provided = (string) ($payload['posSignature'] ?? $payload['signature'] ?? $expected);

        return hash_equals($expected, $provided);
    }

    private function assertPlayerPaymentWindowOpen(EventRegistrationCheckout $checkout): void
    {
        $this->registrationService->assertCheckoutPaymentWindowOpen($checkout);
    }

    private function revalidateStudentBenefits(EventRegistrationCheckout $checkout): void
    {
        $pricing = app(\App\Services\Pricing\PricingService::class);
        foreach ($this->checkoutService->payableActiveItems($checkout) as $item) {
            $pricing->revalidateStudentOnPay($item);
        }

        $this->checkoutService->recomputeTotals($checkout->fresh(['items.registration']));
    }
}
