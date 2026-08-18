<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @OA\Schema(
 *     schema="MatchPlayer",
 *     required={"match_side_id", "slot"},
 *     @OA\Property(property="id_match_player", type="integer", format="int64", readOnly=true),
 *     @OA\Property(property="match_side_id", type="integer", format="int64"),
 *     @OA\Property(property="slot", type="integer"),
 *     @OA\Property(property="participation_id", type="integer", format="int64", nullable=true),
 *     @OA\Property(property="player_id", type="integer", format="int64", nullable=true),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true)
 * )
 */
class MatchPlayer extends Model
{
    use HasFactory;

    protected $table = 'match_players';

    protected $primaryKey = 'id_match_player';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';
    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'match_side_id',
        'slot',
        'participation_id',
        'player_id',
    ];

    protected $casts = [
        'slot' => 'integer',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function matchSide(): BelongsTo
    {
        return $this->belongsTo(MatchSide::class, 'match_side_id', 'id_match_side');
    }

    public function participation(): BelongsTo
    {
        return $this->belongsTo(EditionParticipation::class, 'participation_id', 'id_participation');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id', 'id_player');
    }
}
