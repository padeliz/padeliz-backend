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
 *         title="ID",
 *         description="Unique Identifier"
 *     ),
 *     @OA\Property(
 *         property="edition_id",
 *         type="integer",
 *         format="int64",
 *         minimum=1,
 *         title="Edition",
 *         description="Edition of the division"
 *     ),
 *     @OA\Property(
 *         property="division_id",
 *         type="integer",
 *         format="int64",
 *         minimum=1,
 *         title="Division",
 *         description="Division of the edition"
 *     ),
 *     @OA\Property(
 *         property="players_limit",
 *         type="integer",
 *         minimum=1,
 *         title="Players Limit",
 *         description="Maximum number of players allowed",
 *         example=16
 *     ),
 *     @OA\Property(
 *         property="court",
 *         type="string",
 *         maxLength=30,
 *         title="Court",
 *         description="Court name or identifier",
 *         example="Court A"
 *     ),
 *     @OA\Property(
 *         property="playing_start_time",
 *         type="string",
 *         format="time",
 *         pattern="^([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$",
 *         title="Playing Start Time",
 *         description="Playing start time in HH:MM:SS format",
 *         example="10:00:00"
 *     ),
 *     @OA\Property(
 *         property="playing_end_time",
 *         type="string",
 *         format="time",
 *         pattern="^([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$",
 *         title="Playing End Time",
 *         description="Playing end time in HH:MM:SS format",
 *         example="16:00:00"
 *     ),
 *     @OA\Property(
 *         property="player_opponents_nr",
 *         type="integer",
 *         nullable=true,
 *         minimum=1,
 *         title="Player Opponents NR",
 *         description="Number of opponents per player",
 *         example=3
 *     ),
 *     @OA\Property(
 *         property="player_matches_repeat",
 *         type="integer",
 *         minimum=1,
 *         title="Player Matches Repeat",
 *         description="Number of times matches repeat",
 *         example=1
 *     ),
 *     @OA\Property(property="include_scores", type="boolean", title="Include Scores", description="Whether to include scores"),
 *     @OA\Property(property="has_demonstrative_match", type="boolean", title="Has Demonstrative Match", description="Whether to include a demonstrative match"),
 *     @OA\Property(property="fixed_teams", type="boolean", title="Fixed Teams", description="Whether to include fixed teams"),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", title="Created At", description="Timestamp when the edition division was created"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, title="Updated At", description="Timestamp when the edition division was last updated"),
 *     @OA\Property(
 *         property="edition",
 *         ref="#/components/schemas/Edition",
 *         title="Edition",
 *         description="Related edition object",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="division",
 *         ref="#/components/schemas/Division",
 *         title="Division",
 *         description="Related division object",
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
        // No searchable fields for this model
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
        'id_edition_division',
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

    /**
     * Field that will be used to display the edition division in the UI
     */
    public static ?string $displayField = null;

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

    protected $appends = [
        'formatted_playing_start_time',
        'formatted_playing_end_time',
    ];

    public function edition()
    {
        return $this->belongsTo(Edition::class, 'edition_id');
    }

    public function division()
    {
        return $this->belongsTo(Division::class, 'division_id');
    }

    /**
     * Get the formatted playing start time as H:i:s
     */
    public function getFormattedPlayingStartTimeAttribute()
    {
        if ($this->attributes['playing_start_time']) {
            return $this->asDateTime($this->attributes['playing_start_time'])->format('H:i:s');
        }

        return null;
    }

    /**
     * Get the formatted playing end time as H:i:s
     */
    public function getFormattedPlayingEndTimeAttribute()
    {
        if ($this->attributes['playing_end_time']) {
            return $this->asDateTime($this->attributes['playing_end_time'])->format('H:i:s');
        }

        return null;
    }
}
