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
 *     schema="EventDivision",
 *     required={"event_id", "division_id", "division_title", "players_limit", "playing_start_time", "playing_end_time"},
 *     @OA\Property(property="id_event_division", type="integer", format="int64", readOnly=true, title="ID"),
 *     @OA\Property(property="event_id", type="integer", format="int64", minimum=1, title="PadelEvent"),
 *     @OA\Property(property="division_id", type="integer", format="int64", minimum=1, title="Division"),
 *     @OA\Property(property="division_title", type="string", maxLength=64, title="Division Title"),
 *     @OA\Property(property="players_limit", type="integer", minimum=1, title="Players Limit", example=16),
 *     @OA\Property(property="entry_fee", type="integer", minimum=0, title="Entry Fee", description="Entry fee in RON", example=55),
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
 *     @OA\Property(property="event", ref="#/components/schemas/Event", nullable=true),
 *     @OA\Property(property="division", ref="#/components/schemas/Division", nullable=true),
 *     @OA\Property(property="courts", type="array", @OA\Items(ref="#/components/schemas/Court"), nullable=true),
 *     @OA\Property(property="schedule", ref="#/components/schemas/EventDivisionSchedule", nullable=true)
 * )
 */
class EventDivision extends Model
{
    use HasFactory;
    use HasResourceMetadata;

    public static array $searchableFields = [
        'division_title',
    ];

    public static array $filterableFields = [
        'event_id',
        'division_id',
        'players_limit',
        'entry_fee',
        'skill_order',
    ];

    public static array $sortableFields = [
        'id_event_division',
        'event_id',
        'division_id',
        'division_title',
        'players_limit',
        'entry_fee',
        'skill_order',
        'playing_start_time',
        'playing_end_time',
        'inserted_at',
        'updated_at',
    ];

    public static ?string $displayField = 'division_title';

    protected $table = 'event_divisions';

    protected $primaryKey = 'id_event_division';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';
    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'event_id',
        'division_id',
        'division_title',
        'players_limit',
        'entry_fee',
        'skill_order',
        'playing_start_time',
        'playing_end_time',
    ];

    protected $casts = [
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
        'skill_order' => 'integer',
        'entry_fee' => 'integer',
    ];

    protected $appends = [
        'formatted_playing_start_time',
        'formatted_playing_end_time',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(PadelEvent::class, 'event_id');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'division_id');
    }

    public function schedule(): HasOne
    {
        return $this->hasOne(EventDivisionSchedule::class, 'event_division_id', 'id_event_division');
    }

    /**
     * Schedule knobs live on their own row; callers that cannot work without them use this.
     */
    public function requireSchedule(): EventDivisionSchedule
    {
        $schedule = $this->relationLoaded('schedule')
            ? $this->getRelation('schedule')
            : $this->schedule()->first();

        if (! $schedule instanceof EventDivisionSchedule) {
            throw new InvalidArgumentException(sprintf(
                'PadelEvent division %d has no schedule row.',
                (int) $this->id_event_division
            ));
        }

        return $schedule;
    }

    public function eventDivisionCourts(): HasMany
    {
        return $this->hasMany(EventDivisionCourt::class, 'event_division_id', 'id_event_division')
            ->orderBy('display_order');
    }

    public function courts(): BelongsToMany
    {
        return $this->belongsToMany(
            Court::class,
            'event_division_courts',
            'event_division_id',
            'court_id',
            'id_event_division',
            'id_court'
        )->using(EventDivisionCourt::class)
            ->withTimestamps('inserted_at', 'updated_at')
            ->withPivot(['id_event_division_court', 'display_order'])
            ->orderByPivot('display_order');
    }

    public function posters(): HasMany
    {
        return $this->hasMany(EventDivisionPoster::class, 'event_division_id', 'id_event_division');
    }

    public function standings(): HasMany
    {
        return $this->hasMany(EventDivisionStanding::class, 'event_division_id', 'id_event_division');
    }

    public function replacements(): HasMany
    {
        return $this->hasMany(EventDivisionReplacement::class, 'event_division_id', 'id_event_division');
    }

    public function participations(): HasMany
    {
        return $this->hasMany(EventParticipation::class, 'event_division_id', 'id_event_division');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class, 'event_division_id', 'id_event_division');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(GameMatch::class, 'event_division_id', 'id_event_division');
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
