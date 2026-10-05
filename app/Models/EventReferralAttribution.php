<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Referral\ReferralAttributionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventReferralAttribution extends Model
{
    use HasFactory;

    protected $table = 'event_referral_attributions';

    protected $primaryKey = 'id_attribution';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'event_id',
        'referred_player_id',
        'referrer_player_id',
        'referral_code_id',
        'checkout_id',
        'status',
    ];

    protected $casts = [
        'event_id' => 'integer',
        'referred_player_id' => 'integer',
        'referrer_player_id' => 'integer',
        'referral_code_id' => 'integer',
        'checkout_id' => 'integer',
        'status' => ReferralAttributionStatus::class,
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(PadelEvent::class, 'event_id', 'id_event');
    }

    public function referredPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'referred_player_id', 'id_player');
    }

    public function referrerPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'referrer_player_id', 'id_player');
    }

    public function referralCode(): BelongsTo
    {
        return $this->belongsTo(EventReferralCode::class, 'referral_code_id', 'id_referral_code');
    }

    public function checkout(): BelongsTo
    {
        return $this->belongsTo(EventRegistrationCheckout::class, 'checkout_id', 'id_checkout');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(BenefitRedemption::class, 'referral_attribution_id', 'id_attribution');
    }
}
