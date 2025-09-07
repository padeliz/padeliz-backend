<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="EditionDivision",
 *     required={"edition_id", "division_id", "players_limit", "court", "playing_start_time", "playing_end_time", "player_matches_repeat", "include_scores", "has_demonstrative_match", "fixed_teams"},
 *     @OA\Property(
 *         property="id_edition_division",
 *         type="integer",
 *         format="int64",
 *         readOnly=true,
 *         description="Primary key - Edition Division ID"
 *     ),
 *     @OA\Property(
 *         property="edition_id",
 *         type="integer",
 *         format="int64",
 *         minimum=1,
 *         description="Foreign key - References Edition.id_edition"
 *     ),
 *     @OA\Property(
 *         property="division_id",
 *         type="integer",
 *         format="int64",
 *         minimum=1,
 *         description="Foreign key - References Division.id_division"
 *     ),
 *     @OA\Property(
 *         property="players_limit",
 *         type="integer",
 *         minimum=1,
 *         description="Maximum number of players allowed",
 *         example=16
 *     ),
 *     @OA\Property(
 *         property="court",
 *         type="string",
 *         maxLength=30,
 *         description="Court name or identifier",
 *         example="Court A"
 *     ),
 *     @OA\Property(
 *         property="playing_start_time",
 *         type="string",
 *         format="time",
 *         pattern="^([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$",
 *         description="Playing start time in HH:MM:SS format",
 *         example="10:00:00"
 *     ),
 *     @OA\Property(
 *         property="playing_end_time",
 *         type="string",
 *         format="time",
 *         pattern="^([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$",
 *         description="Playing end time in HH:MM:SS format",
 *         example="16:00:00"
 *     ),
 *     @OA\Property(
 *         property="player_opponents_nr",
 *         type="integer",
 *         nullable=true,
 *         minimum=1,
 *         description="Number of opponents per player",
 *         example=3
 *     ),
 *     @OA\Property(
 *         property="player_matches_repeat",
 *         type="integer",
 *         minimum=1,
 *         description="Number of times matches repeat",
 *         example=1
 *     ),
 *     @OA\Property(property="include_scores", type="boolean"),
 *     @OA\Property(property="has_demonstrative_match", type="boolean"),
 *     @OA\Property(property="fixed_teams", type="boolean"),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true),
 *     @OA\Property(
 *         property="edition",
 *         ref="#/components/schemas/Edition",
 *         description="Related edition object (loaded when requested)",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="division",
 *         ref="#/components/schemas/Division",
 *         description="Related division object (loaded when requested)",
 *         nullable=true
 *     )
 * )
 */
class EditionDivision extends Model
{
    use HasFactory;
    use HasResourceMetadata;

    /**
     * Fields that can be searched
     */
    public static array $searchableFields = [
        'court',
    ];

    /**
     * Fields that can be filtered
     */
    public static array $filterableFields = [
        'edition_id',
        'division_id',
        'players_limit',
        'player_opponents_nr',
        'player_matches_repeat',
        'include_scores',
        'has_demonstrative_match',
        'fixed_teams',
    ];

    /**
     * Fields that can be sorted
     */
    public static array $sortableFields = [
        'edition_id',
        'division_id',
        'players_limit',
        'court',
        'playing_start_time',
        'playing_end_time',
        'player_opponents_nr',
        'player_matches_repeat',
        'include_scores',
        'has_demonstrative_match',
        'fixed_teams',
        'inserted_at',
        'updated_at',
    ];

    protected $table = 'edition_divisions';

    protected $primaryKey = 'id_edition_division';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';
    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'edition_id',
        'division_id',
        'players_limit',
        'court',
        'playing_start_time',
        'playing_end_time',
        'player_opponents_nr',
        'player_matches_repeat',
        'include_scores',
        'has_demonstrative_match',
        'fixed_teams',
    ];

    protected $casts = [
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
        'playing_start_time' => 'datetime:H:i:s',
        'playing_end_time' => 'datetime:H:i:s',
    ];

    public function edition()
    {
        return $this->belongsTo(Edition::class, 'edition_id');
    }

    public function division()
    {
        return $this->belongsTo(Division::class, 'division_id');
    }
}
