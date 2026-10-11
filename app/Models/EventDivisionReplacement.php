<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @OA\Schema(
 *     schema="EventDivisionReplacement",
 *     required={"event_division_id", "original_participation_id"},
 *     @OA\Property(property="id_replacement", type="integer", format="int64", readOnly=true),
 *     @OA\Property(property="event_division_id", type="integer", format="int64"),
 *     @OA\Property(property="original_participation_id", type="integer", format="int64"),
 *     @OA\Property(property="substitute_participation_id", type="integer", format="int64", nullable=true),
 *     @OA\Property(property="substitute_player_user_id", type="integer", format="int64", nullable=true),
 *     @OA\Property(property="match_id", type="integer", format="int64", nullable=true),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true)
 * )
 */
class EventDivisionReplacement extends Model
{
    use HasFactory;

    protected $table = 'event_division_replacements';

    protected $primaryKey = 'id_replacement';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';
    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'event_division_id',
        'original_participation_id',
        'substitute_participation_id',
        'substitute_player_user_id',
        'match_id',
    ];

    protected $casts = [
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function eventDivision(): BelongsTo
    {
        return $this->belongsTo(EventDivision::class, 'event_division_id', 'id_event_division');
    }

    public function originalParticipation(): BelongsTo
    {
        return $this->belongsTo(EventParticipation::class, 'original_participation_id', 'id_participation');
    }

    public function substituteParticipation(): BelongsTo
    {
        return $this->belongsTo(EventParticipation::class, 'substitute_participation_id', 'id_participation');
    }

    public function substitutePlayer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'substitute_player_user_id', 'id_user');
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id', 'id_match');
    }
}
