<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventReferralCodeInvitee extends Model
{
    use HasFactory;

    protected $table = 'event_referral_code_invitees';

    protected $primaryKey = 'id_referral_code_invitee';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'referral_code_id',
        'email',
        'phone',
        'player_id',
    ];

    protected $casts = [
        'referral_code_id' => 'integer',
        'player_id' => 'integer',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function referralCode(): BelongsTo
    {
        return $this->belongsTo(EventReferralCode::class, 'referral_code_id', 'id_referral_code');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id', 'id_player');
    }
}
