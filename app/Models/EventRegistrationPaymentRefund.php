<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Registration\PaymentRefundStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventRegistrationPaymentRefund extends Model
{
    use HasFactory;

    protected $table = 'event_registration_payment_refunds';

    protected $primaryKey = 'id_refund';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'checkout_id',
        'checkout_item_id',
        'payment_attempt_id',
        'amount',
        'currency',
        'status',
        'provider_refund_id',
        'provider_payload',
        'error_message',
    ];

    protected $casts = [
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
        'checkout_id' => 'integer',
        'checkout_item_id' => 'integer',
        'payment_attempt_id' => 'integer',
        'amount' => 'integer',
        'provider_payload' => 'array',
        'status' => PaymentRefundStatus::class,
    ];

    public function checkout(): BelongsTo
    {
        return $this->belongsTo(EventRegistrationCheckout::class, 'checkout_id', 'id_checkout');
    }

    public function checkoutItem(): BelongsTo
    {
        return $this->belongsTo(EventRegistrationCheckoutItem::class, 'checkout_item_id', 'id_checkout_item');
    }

    public function paymentAttempt(): BelongsTo
    {
        return $this->belongsTo(EventRegistrationPaymentAttempt::class, 'payment_attempt_id', 'id_payment_attempt');
    }
}
