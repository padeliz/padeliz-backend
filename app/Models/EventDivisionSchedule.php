<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @OA\Schema(
 *     schema="EventDivisionSchedule",
 *     required={"event_division_id"},
 *     @OA\Property(property="id_event_division_schedule", type="integer", format="int64", readOnly=true),
 *     @OA\Property(property="event_division_id", type="integer", format="int64", minimum=1),
 *     @OA\Property(property="player_opponents_nr", type="integer", nullable=true, minimum=1),
 *     @OA\Property(property="player_matches_repeat", type="integer", nullable=true, minimum=1),
 *     @OA\Property(property="include_scores", type="boolean"),
 *     @OA\Property(property="has_demonstrative_match", type="boolean"),
 *     @OA\Property(property="allow_replacements", type="boolean"),
 *     @OA\Property(property="points_per_match", type="integer", nullable=true),
 *     @OA\Property(property="final_points_target", type="integer"),
 *     @OA\Property(property="include_final", type="boolean"),
 *     @OA\Property(property="template_version", type="integer", nullable=true),
 *     @OA\Property(property="adjust", type="integer", description="Points-per-match adjust; allowed -6,-4,-2,0,2,4,6"),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true)
 * )
 */
class EventDivisionSchedule extends Model
{
    use HasFactory;
    use HasResourceMetadata;

    public static array $searchableFields = [];

    public static array $filterableFields = [
        'event_division_id',
        'include_scores',
        'has_demonstrative_match',
        'allow_replacements',
        'include_final',
    ];

    public static array $sortableFields = [
        'id_event_division_schedule',
        'event_division_id',
        'inserted_at',
        'updated_at',
    ];

    protected $table = 'event_division_schedules';

    protected $primaryKey = 'id_event_division_schedule';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';
    public const UPDATED_AT = 'updated_at';

    /** @var list<int> */
    public const ADJUST_VALUES = [-6, -4, -2, 0, 2, 4, 6];

    protected $fillable = [
        'event_division_id',
        'player_opponents_nr',
        'player_matches_repeat',
        'include_scores',
        'has_demonstrative_match',
        'allow_replacements',
        'points_per_match',
        'final_points_target',
        'include_final',
        'template_version',
        'adjust',
    ];

    protected $casts = [
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
        'include_scores' => 'boolean',
        'has_demonstrative_match' => 'boolean',
        'allow_replacements' => 'boolean',
        'include_final' => 'boolean',
        'points_per_match' => 'integer',
        'final_points_target' => 'integer',
        'template_version' => 'integer',
        'adjust' => 'integer',
        'player_opponents_nr' => 'integer',
        'player_matches_repeat' => 'integer',
    ];

    public function eventDivision(): BelongsTo
    {
        return $this->belongsTo(EventDivision::class, 'event_division_id', 'id_event_division');
    }
}
