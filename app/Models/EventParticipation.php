<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @OA\Schema(
 *     schema="EventParticipation",
 *     required={"event_id", "player_id", "event_division_id"},
 *     @OA\Property(
 *         property="id_participation",
 *         type="integer",
 *         format="int64",
 *         readOnly=true,
 *         title="ID",
 *         description="Unique Identifier"
 *     ),
 *     @OA\Property(
 *         property="event_id",
 *         type="integer",
 *         format="int64",
 *         minimum=1,
 *         title="PadelEvent",
 *         description="Foreign key - References PadelEvent.id_event"
 *     ),
 *     @OA\Property(
 *         property="player_id",
 *         type="integer",
 *         format="int64",
 *         minimum=1,
 *         title="Player",
 *         description="Player of the participation"
 *     ),
 *     @OA\Property(
 *         property="event_division_id",
 *         type="integer",
 *         format="int64",
 *         minimum=1,
 *         title="PadelEvent Division",
 *         description="PadelEvent division of the participation"
 *     ),
 *     @OA\Property(
 *         property="sort_order",
 *         type="integer",
 *         default=0,
 *         title="Sort Order",
 *         description="Display order within the edition division"
 *     ),
 *     @OA\Property(
 *         property="is_collecting_points",
 *         type="boolean",
 *         default=true,
 *         title="Collecting Points",
 *         description="Whether the participant is collecting points"
 *     ),
 *     @OA\Property(
 *         property="absence_reason",
 *         type="string",
 *         enum={"SICK", "FORGOT", "EXAMS", "RECONSIDERED"},
 *         nullable=true,
 *         title="Absence Reason",
 *         description="Reason for absence from the edition"
 *     ),
 *     @OA\Property(
 *         property="ban_reason",
 *         type="string",
 *         enum={"LEFT SUDDENLY", "AGGRESSIVE PLAYER", "LIBIDINOUS", ""},
 *         nullable=true,
 *         title="Ban Reason",
 *         description="Reason for banning the participant"
 *     ),
 *     @OA\Property(
 *         property="inserted_at",
 *         type="integer",
 *         format="timestamp",
 *         readOnly=true,
 *         title="Created At",
 *         description="Timestamp when the participation was created"
 *     ),
 *     @OA\Property(
 *         property="updated_at",
 *         type="integer",
 *         format="timestamp",
 *         nullable=true,
 *         readOnly=true,
 *         title="Updated At",
 *         description="Timestamp when the participation was last updated"
 *     ),
 *     @OA\Property(
 *         property="event",
 *         ref="#/components/schemas/Event",
 *         title="PadelEvent",
 *         description="Related edition object",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="player",
 *         ref="#/components/schemas/Player",
 *         title="Player",
 *         description="Related player object",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="eventDivision",
 *         ref="#/components/schemas/EventDivision",
 *         title="PadelEvent Division",
 *         description="Related edition division object",
 *         nullable=true
 *     )
 * )
 */
class EventParticipation extends Model
{
    use HasFactory;
    use HasResourceMetadata;

    /**
     * Fields that can be searched
     */
    public static array $searchableFields = [
        'absence_reason',
        'ban_reason',
    ];

    /**
     * Fields that can be filtered
     */
    public static array $filterableFields = [
        'event_id',
        'player_id',
        'event_division_id',
        'sort_order',
        'is_collecting_points',
    ];

    /**
     * Fields that can be sorted
     */
    public static array $sortableFields = [
        'id_participation',
        'event_id',
        'player_id',
        'event_division_id',
        'sort_order',
        'is_collecting_points',
        'inserted_at',
        'updated_at',
    ];

    /**
     * Field that will be used to display the edition participation in the UI
     */
    public static ?string $displayField = null;

    protected $table = 'event_participations';

    protected $primaryKey = 'id_participation';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';
    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'event_id',
        'player_id',
        'event_division_id',
        'sort_order',
        'is_collecting_points',
        'absence_reason',
        'ban_reason',
    ];

    protected $casts = [
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
        'sort_order' => 'integer',
        'is_collecting_points' => 'boolean',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(PadelEvent::class, 'event_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id');
    }

    public function eventDivision(): BelongsTo
    {
        return $this->belongsTo(EventDivision::class, 'event_division_id', 'id_event_division');
    }

    /**
     * The player level a manager entered for this participation (at most one row).
     */
    public function playerLevel(): HasOne
    {
        return $this->hasOne(PlayerLevel::class, 'event_participation_id', 'id_participation');
    }
}
