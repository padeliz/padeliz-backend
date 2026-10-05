<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Registration\PaymentAttemptStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventRegistrationPaymentAttempt extends Model
{
    use HasFactory;

    protected $table = 'event_registration_payment_attempts';

    protected $primaryKey = 'id_payment_attempt';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'checkout_id',
        'amount',
        'currency',
        'status',
        'ntp_id',
        'order_id',
        'redirect_url',
        'provider_payload',
        'error_message',
        'coverage',
    ];

    protected $casts = [
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
        'amount' => 'integer',
        'checkout_id' => 'integer',
        'provider_payload' => 'array',
        'coverage' => 'array',
        'status' => PaymentAttemptStatus::class,
    ];

    public function checkout(): BelongsTo
    {
        return $this->belongsTo(EventRegistrationCheckout::class, 'checkout_id', 'id_checkout');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(EventRegistrationPaymentRefund::class, 'payment_attempt_id', 'id_payment_attempt');
    }
}
