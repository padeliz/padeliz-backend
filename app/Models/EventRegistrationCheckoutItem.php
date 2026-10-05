<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Registration\CheckoutItemStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventRegistrationCheckoutItem extends Model
{
    use HasFactory;

    protected $table = 'event_registration_checkout_items';

    protected $primaryKey = 'id_checkout_item';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'checkout_id',
        'registration_id',
        'base_amount_ron',
        'amount',
        'item_status',
    ];

    protected $casts = [
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
        'checkout_id' => 'integer',
        'registration_id' => 'integer',
        'base_amount_ron' => 'integer',
        'amount' => 'integer',
        'item_status' => CheckoutItemStatus::class,
    ];

    public function checkout(): BelongsTo
    {
        return $this->belongsTo(EventRegistrationCheckout::class, 'checkout_id', 'id_checkout');
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(EventRegistration::class, 'registration_id', 'id_registration');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(BenefitRedemption::class, 'checkout_item_id', 'id_checkout_item');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(EventRegistrationPaymentRefund::class, 'checkout_item_id', 'id_checkout_item');
    }

    public function scopeActive($query)
    {
        return $query->where('item_status', CheckoutItemStatus::Active->value);
    }
}
