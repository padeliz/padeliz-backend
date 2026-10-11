<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralTouchpoint extends Model
{
    use HasFactory;

    protected $table = 'referral_touchpoints';

    protected $primaryKey = 'id_touchpoint';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'event_id',
        'referred_player_user_id',
        'referrer_player_user_id',
        'referral_code_id',
        'discount_eligible',
        'touched_at',
        'client_metadata',
    ];

    protected $casts = [
        'event_id' => 'integer',
        'referred_player_user_id' => 'integer',
        'referrer_player_user_id' => 'integer',
        'referral_code_id' => 'integer',
        'discount_eligible' => 'boolean',
        'touched_at' => 'integer',
        'client_metadata' => 'array',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(PadelEvent::class, 'event_id', 'id_event');
    }

    public function referredPlayer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_player_user_id', 'id_user');
    }

    public function referrerPlayer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_player_user_id', 'id_user');
    }

    public function referralCode(): BelongsTo
    {
        return $this->belongsTo(EventReferralCode::class, 'referral_code_id', 'id_referral_code');
    }
}
