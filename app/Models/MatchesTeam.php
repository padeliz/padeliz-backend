<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="MatchesTeam",
 *     required={"player_1", "player_2"},
 *     @OA\Property(
 *         property="id_team",
 *         type="integer",
 *         format="int64",
 *         readOnly=true,
 *         description="Primary key - Matches Team ID"
 *     ),
 *     @OA\Property(property="player_1", type="string", maxLength=50),
 *     @OA\Property(property="player_2", type="string", maxLength=50),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true),
 *     @OA\Property(
 *         property="player1",
 *         ref="#/components/schemas/Player",
 *         description="Related player 1 object (loaded when requested)",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="player2",
 *         ref="#/components/schemas/Player",
 *         description="Related player 2 object (loaded when requested)",
 *         nullable=true
 *     )
 * )
 */
class MatchesTeam extends Model
{
    use HasFactory;
    use HasResourceMetadata;

    /**
     * Fields that can be searched
     */
    public static array $searchableFields = [
        // No searchable fields for this model
    ];

    /**
     * Fields that can be filtered
     */
    public static array $filterableFields = [
        'player_1',
        'player_2',
    ];

    /**
     * Fields that can be sorted
     */
    public static array $sortableFields = [
        'player_1',
        'player_2',
        'inserted_at',
        'updated_at',
    ];

    protected $table = 'matches_teams';

    protected $primaryKey = 'id_team';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';
    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'player_1',
        'player_2',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function player1()
    {
        return $this->belongsTo(Player::class, 'player_1', 'id_player');
    }

    public function player2()
    {
        return $this->belongsTo(Player::class, 'player_2', 'id_player');
    }
}
