<?php

declare(strict_types=1);

namespace App\Services\RegistrationPayment;

use App\Enums\Registration\CheckoutStatus;
use App\Enums\Registration\PaymentProofReviewStatus;
use App\Enums\Registration\RegistrationPaymentMethod;
use App\Enums\Registration\RegistrationPaymentStatus;
use App\Models\EventRegistration;
use App\Models\EventRegistrationCheckout;
use App\Models\EventRegistrationPaymentProof;
use App\Services\Push\ExpoPushService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class BankProofService
{
    public function __construct(
        private readonly RegistrationService $registrationService,
        private readonly CheckoutService $checkoutService,
        private readonly ExpoPushService $expoPushService,
    ) {
    }

    public function uploadForCheckout(EventRegistrationCheckout $checkout, UploadedFile $file): EventRegistrationPaymentProof
    {
        return DB::transaction(function () use ($checkout, $file) {
            $checkout = $this->checkoutService->lockCheckout((int) $checkout->id_checkout);
            $this->assertCheckoutPayable($checkout);
            $this->assertPlayerPaymentWindowOpen($checkout);
            $this->revalidateStudentBenefits($checkout);

            if ($this->checkoutService->payableTotal($checkout) <= 0) {
                throw new InvalidArgumentException('Amount due must be greater than zero.');
            }

            $path = Storage::disk($this->disk())->putFile(
                'registration-payment-proofs/checkout-' . $checkout->id_checkout,
                $file
            );

            $proof = EventRegistrationPaymentProof::query()->create([
                'checkout_id' => $checkout->id_checkout,
                'proof_path' => $path,
                'review_status' => PaymentProofReviewStatus::Pending,
                'coverage' => $this->checkoutService->coverageSnapshot($checkout),
            ]);

            $this->checkoutService->markPayableItemsAwaitingProof($checkout);

            $this->expoPushService->notifyRegistrationStatus(
                (int) $checkout->player_id,
                'awaiting_proof_review',
                ['checkout_id' => $checkout->id_checkout]
            );

            return $proof->refresh();
        });
    }

    /** @deprecated Prefer uploadForCheckout */
    public function upload(EventRegistration $registration, UploadedFile $file): EventRegistrationPaymentProof
    {
        $checkout = $this->checkoutService->openCheckoutForRegistration($registration)
            ?? $this->checkoutService->openOrRefreshForUnpaid(
                (int) $registration->event_id,
                (int) $registration->player_id
            );

        return $this->uploadForCheckout($checkout, $file);
    }

    public function approve(EventRegistrationPaymentProof $proof, int $managerPlayerId, ?string $message = null): EventRegistrationPaymentProof
    {
        return DB::transaction(function () use ($proof, $managerPlayerId, $message) {
            if ($proof->review_status !== PaymentProofReviewStatus::Pending) {
                throw new InvalidArgumentException('Only pending proofs can be approved.');
            }

            $checkout = $this->checkoutService->lockCheckout((int) $proof->checkout_id);
            $this->assertCheckoutPayable($checkout);

            $proof->review_status = PaymentProofReviewStatus::Approved;
            $proof->review_message = $message;
            $proof->reviewed_by = $managerPlayerId;
            $proof->reviewed_at = time();
            $proof->save();

            $this->checkoutService->markPayableItemsPaid($checkout, RegistrationPaymentMethod::BankTransfer->value);

            return $proof->refresh();
        });
    }

    public function reject(EventRegistrationPaymentProof $proof, int $managerPlayerId, ?string $message = null): EventRegistrationPaymentProof
    {
        return DB::transaction(function () use ($proof, $managerPlayerId, $message) {
            if ($proof->review_status !== PaymentProofReviewStatus::Pending) {
                throw new InvalidArgumentException('Only pending proofs can be rejected.');
            }

            $checkout = $this->checkoutService->lockCheckout((int) $proof->checkout_id);

            $proof->review_status = PaymentProofReviewStatus::Rejected;
            $proof->review_message = $message;
            $proof->reviewed_by = $managerPlayerId;
            $proof->reviewed_at = time();
            $proof->save();

            $stillPending = EventRegistrationPaymentProof::query()
                ->where('checkout_id', $checkout->id_checkout)
                ->where('review_status', PaymentProofReviewStatus::Pending->value)
                ->exists();

            if (! $stillPending && $checkout->status === CheckoutStatus::AwaitingProof) {
                foreach ($this->checkoutService->payableActiveItems($checkout) as $item) {
                    $reg = $item->registration;
                    if ($reg === null) {
                        continue;
                    }
                    if ($reg->payment_status === RegistrationPaymentStatus::AwaitingProofReview) {
                        $reg->payment_status = RegistrationPaymentStatus::Unpaid;
                        $reg->save();
                    }
                }
                $checkout->status = CheckoutStatus::Open;
                $checkout->save();

                $this->expoPushService->notifyRegistrationStatus(
                    (int) $checkout->player_id,
                    'proof_rejected',
                    ['checkout_id' => $checkout->id_checkout]
                );
            }

            return $proof->refresh();
        });
    }

    private function assertCheckoutPayable(EventRegistrationCheckout $checkout): void
    {
        if (! $checkout->status->isOpen()) {
            throw new InvalidArgumentException('Checkout is not open for bank transfer proofs.');
        }

        if ($this->checkoutService->payableTotal($checkout) <= 0) {
            throw new InvalidArgumentException('Amount due must be greater than zero.');
        }
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

    private function disk(): string
    {
        return (string) config('filesystems.user_uploads_disk', 'public');
    }
}
