<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Schedule\PlayingWindow;
use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use InvalidArgumentException;

/**
 * @OA\Schema(
 *     schema="EditionDivision",
 *     required={"edition_id", "division_id", "division_title", "players_limit", "playing_start_time", "playing_end_time"},
 *     @OA\Property(property="id_edition_division", type="integer", format="int64", readOnly=true, title="ID"),
 *     @OA\Property(property="edition_id", type="integer", format="int64", minimum=1, title="Edition"),
 *     @OA\Property(property="division_id", type="integer", format="int64", minimum=1, title="Division"),
 *     @OA\Property(property="division_title", type="string", maxLength=64, title="Division Title"),
 *     @OA\Property(property="players_limit", type="integer", minimum=1, title="Players Limit", example=16),
 *     @OA\Property(property="skill_order", type="integer", minimum=0, title="Skill Order"),
 *     @OA\Property(
 *         property="playing_start_time",
 *         type="string",
 *         format="time",
 *         pattern="^([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$",
 *         title="Playing Start Time",
 *         example="10:00:00"
 *     ),
 *     @OA\Property(
 *         property="playing_end_time",
 *         type="string",
 *         format="time",
 *         pattern="^([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$",
 *         title="Playing End Time",
 *         example="16:00:00"
 *     ),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true),
 *     @OA\Property(property="edition", ref="#/components/schemas/Edition", nullable=true),
 *     @OA\Property(property="division", ref="#/components/schemas/Division", nullable=true),
 *     @OA\Property(property="courts", type="array", @OA\Items(ref="#/components/schemas/Court"), nullable=true),
 *     @OA\Property(property="schedule", ref="#/components/schemas/EditionDivisionSchedule", nullable=true)
 * )
 */
class EditionDivision extends Model
{
    use HasFactory;
    use HasResourceMetadata;

    public static array $searchableFields = [
        'division_title',
    ];

    public static array $filterableFields = [
        'edition_id',
        'division_id',
        'players_limit',
        'skill_order',
    ];

    public static array $sortableFields = [
        'id_edition_division',
        'edition_id',
        'division_id',
        'division_title',
        'players_limit',
        'skill_order',
        'playing_start_time',
        'playing_end_time',
        'inserted_at',
        'updated_at',
    ];

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
        'skill_order',
        'playing_start_time',
        'playing_end_time',
    ];

    protected $casts = [
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
        'skill_order' => 'integer',
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

    public function schedule(): HasOne
    {
        return $this->hasOne(EditionDivisionSchedule::class, 'edition_division_id', 'id_edition_division');
    }

    /**
     * Schedule knobs live on their own row; callers that cannot work without them use this.
     */
    public function requireSchedule(): EditionDivisionSchedule
    {
        $schedule = $this->relationLoaded('schedule')
            ? $this->getRelation('schedule')
            : $this->schedule()->first();

        if (! $schedule instanceof EditionDivisionSchedule) {
            throw new InvalidArgumentException(sprintf(
                'Edition division %d has no schedule row.',
                (int) $this->id_edition_division
            ));
        }

        return $schedule;
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
        )->using(EditionDivisionCourt::class)
            ->withTimestamps('inserted_at', 'updated_at')
            ->withPivot(['id_edition_division_court', 'display_order'])
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

    public function registrations(): HasMany
    {
        return $this->hasMany(EditionRegistration::class, 'edition_division_id', 'id_edition_division');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(GameMatch::class, 'edition_division_id', 'id_edition_division');
    }

    public function getFormattedPlayingStartTimeAttribute()
    {
        return PlayingWindow::toClockString($this->attributes['playing_start_time'] ?? null);
    }

    public function getFormattedPlayingEndTimeAttribute()
    {
        return PlayingWindow::toClockString($this->attributes['playing_end_time'] ?? null);
    }
}
