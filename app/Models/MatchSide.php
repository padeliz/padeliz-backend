<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @OA\Schema(
 *     schema="MatchSide",
 *     required={"match_id", "side"},
 *     @OA\Property(property="id_match_side", type="integer", format="int64", readOnly=true),
 *     @OA\Property(property="match_id", type="integer", format="int64"),
 *     @OA\Property(property="side", type="string", enum={"LEFT", "RIGHT"}),
 *     @OA\Property(property="score", type="integer", nullable=true),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", nullable=true, readOnly=true),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true)
 * )
 */
class MatchSide extends Model
{
    use HasFactory;

    protected $table = 'match_sides';

    protected $primaryKey = 'id_match_side';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';
    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'match_id',
        'side',
        'score',
    ];

    protected $casts = [
        'score' => 'integer',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id', 'id_match');
    }

    public function players(): HasMany
    {
        return $this->hasMany(MatchPlayer::class, 'match_side_id', 'id_match_side')
            ->orderBy('slot');
    }
}
