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
 *         title="ID",
 *         description="Unique Identifier"
 *     ),
 *     @OA\Property(property="player_1", type="string", maxLength=50, title="Player 1", description="Player 1 of the team"),
 *     @OA\Property(property="player_2", type="string", maxLength=50, title="Player 2", description="Player 2 of the team"),
 *     @OA\Property(
 *         property="player_1_is_collecting_points",
 *         type="boolean",
 *         default=true,
 *         title="Player 1 Collecting Points",
 *         description="Whether player 1 is collecting points"
 *     ),
 *     @OA\Property(
 *         property="player_2_is_collecting_points",
 *         type="boolean",
 *         default=true,
 *         title="Player 2 Collecting Points",
 *         description="Whether player 2 is collecting points"
 *     ),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true, title="Created At", description="Timestamp when the matches team was created"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true, title="Updated At", description="Timestamp when the matches team was last updated")
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
        'player_1',
        'player_2',
    ];

    /**
     * Fields that can be filtered
     */
    public static array $filterableFields = [
        'player_1',
        'player_2',
        'player_1_is_collecting_points',
        'player_2_is_collecting_points',
    ];

    /**
     * Fields that can be sorted
     */
    public static array $sortableFields = [
        'id_team',
        'player_1',
        'player_2',
        'player_1_is_collecting_points',
        'player_2_is_collecting_points',
        'inserted_at',
        'updated_at',
    ];

    /**
     * Field that will be used to display the matches team in the UI
     */
    public static ?string $displayField = null;

    protected $table = 'matches_teams';

    protected $primaryKey = 'id_team';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';
    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'player_1',
        'player_2',
        'player_1_is_collecting_points',
        'player_2_is_collecting_points',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
        'player_1_is_collecting_points' => 'boolean',
        'player_2_is_collecting_points' => 'boolean',
    ];
}
