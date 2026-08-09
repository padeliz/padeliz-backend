<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @OA\Schema(
 *     schema="EditionDivision",
 *     required={"edition_id", "division_id", "division_title", "players_limit", "playing_start_time", "playing_end_time", "player_matches_repeat", "include_scores", "has_demonstrative_match", "fixed_teams"},
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
 *         property="division_title",
 *         type="string",
 *         maxLength=64,
 *         title="Division Title",
 *         description="Display title for this edition division"
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
 *     @OA\Property(
 *         property="points_per_match",
 *         type="integer",
 *         nullable=true,
 *         title="Points Per Match",
 *         description="Points awarded per match"
 *     ),
 *     @OA\Property(
 *         property="final_points_target",
 *         type="integer",
 *         nullable=true,
 *         title="Final Points Target",
 *         description="Points target for the final match"
 *     ),
 *     @OA\Property(
 *         property="include_final",
 *         type="boolean",
 *         default=false,
 *         title="Include Final",
 *         description="Whether this edition division includes a final"
 *     ),
 *     @OA\Property(
 *         property="finalized_at",
 *         type="integer",
 *         format="timestamp",
 *         nullable=true,
 *         title="Finalized At",
 *         description="Unix timestamp when standings were finalized"
 *     ),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true, title="Created At", description="Timestamp when the edition division was created"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true, title="Updated At", description="Timestamp when the edition division was last updated"),
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
        'division_title',
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
        'include_final',
    ];

    /**
     * Fields that can be sorted
     */
    public static array $sortableFields = [
        'id_edition_division',
        'edition_id',
        'division_id',
        'division_title',
        'players_limit',
        'playing_start_time',
        'playing_end_time',
        'player_opponents_nr',
        'player_matches_repeat',
        'include_scores',
        'has_demonstrative_match',
        'fixed_teams',
        'points_per_match',
        'final_points_target',
        'include_final',
        'finalized_at',
        'inserted_at',
        'updated_at',
    ];

    /**
     * Field that will be used to display the edition division in the UI
     */
    public static ?string $displayField = 'division_title';

    protected $table = 'edition_divisions';

    protected $primaryKey = 'id_edition_division';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';
    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'edition_id',
        'division_id',
        'division_title',
        'players_limit',
        'playing_start_time',
        'playing_end_time',
        'player_opponents_nr',
        'player_matches_repeat',
        'include_scores',
        'has_demonstrative_match',
        'fixed_teams',
        'points_per_match',
        'final_points_target',
        'include_final',
        'finalized_at',
    ];

    protected $casts = [
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
        'finalized_at' => 'integer',
        'playing_start_time' => 'datetime:H:i:s',
        'playing_end_time' => 'datetime:H:i:s',
        'include_final' => 'boolean',
        'points_per_match' => 'integer',
        'final_points_target' => 'integer',
    ];

    protected $appends = [
        'formatted_playing_start_time',
        'formatted_playing_end_time',
    ];

    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class, 'edition_id');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'division_id');
    }

    public function editionDivisionCourts(): HasMany
    {
        return $this->hasMany(EditionDivisionCourt::class, 'edition_division_id', 'id_edition_division')
            ->orderBy('display_order');
    }

    public function courts(): BelongsToMany
    {
        return $this->belongsToMany(
            Court::class,
            'edition_division_courts',
            'edition_division_id',
            'court_id',
            'id_edition_division',
            'id_court'
        )->withPivot(['id_edition_division_court', 'display_order', 'inserted_at', 'updated_at'])
            ->orderByPivot('display_order');
    }

    public function posters(): HasMany
    {
        return $this->hasMany(EditionDivisionPoster::class, 'edition_division_id', 'id_edition_division');
    }

    public function standings(): HasMany
    {
        return $this->hasMany(EditionDivisionStanding::class, 'edition_division_id', 'id_edition_division');
    }

    public function replacements(): HasMany
    {
        return $this->hasMany(EditionDivisionReplacement::class, 'edition_division_id', 'id_edition_division');
    }

    public function participations(): HasMany
    {
        return $this->hasMany(EditionParticipation::class, 'edition_division_id', 'id_edition_division');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(GameMatch::class, 'edition_division_id', 'id_edition_division');
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
