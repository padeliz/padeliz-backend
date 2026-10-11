<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventReferralCode extends Model
{
    use HasFactory;

    protected $table = 'event_referral_codes';

    protected $primaryKey = 'id_referral_code';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'event_id',
        'referrer_player_user_id',
        'code',
        'expires_at',
        'revoked_at',
    ];

    protected $casts = [
        'event_id' => 'integer',
        'referrer_player_user_id' => 'integer',
        'expires_at' => 'integer',
        'revoked_at' => 'integer',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(PadelEvent::class, 'event_id', 'id_event');
    }

    public function referrerPlayer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_player_user_id', 'id_user');
    }

    public function invitees(): HasMany
    {
        return $this->hasMany(EventReferralCodeInvitee::class, 'referral_code_id', 'id_referral_code');
    }

    public function touchpoints(): HasMany
    {
        return $this->hasMany(ReferralTouchpoint::class, 'referral_code_id', 'id_referral_code');
    }

    public function attributions(): HasMany
    {
        return $this->hasMany(EventReferralAttribution::class, 'referral_code_id', 'id_referral_code');
    }

    public function isExpired(?int $at = null): bool
    {
        return (int) $this->expires_at <= ($at ?? time());
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isUsable(?int $at = null): bool
    {
        return ! $this->isExpired($at) && ! $this->isRevoked();
    }
}
