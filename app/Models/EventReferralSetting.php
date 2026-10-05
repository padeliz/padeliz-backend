<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventReferralSetting extends Model
{
    use HasFactory;

    protected $table = 'event_referral_settings';

    protected $primaryKey = 'id_event_referral_setting';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'event_id',
        'code_ttl_hours',
    ];

    protected $casts = [
        'event_id' => 'integer',
        'code_ttl_hours' => 'integer',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(PadelEvent::class, 'event_id', 'id_event');
    }
}
