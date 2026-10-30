<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @OA\Schema(
 *     schema="EventDivisionStanding",
 *     required={"event_division_id", "participation_id"},
 *     @OA\Property(property="id_standing", type="integer", format="int64", readOnly=true),
 *     @OA\Property(property="event_division_id", type="integer", format="int64"),
 *     @OA\Property(property="participation_id", type="integer", format="int64"),
 *     @OA\Property(property="points_total", type="integer", nullable=true),
 *     @OA\Property(property="wins", type="number", format="float", nullable=true),
 *     @OA\Property(property="rank", type="integer", nullable=true),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true)
 * )
 */
class EventDivisionStanding extends Model
{
    use HasFactory;
    use HasResourceMetadata;

    public static array $searchableFields = [];

    public static array $filterableFields = [
        'event_division_id',
        'participation_id',
    ];

    public static array $sortableFields = [
        'id_standing',
        'event_division_id',
        'participation_id',
        'points_total',
        'wins',
        'rank',
        'inserted_at',
        'updated_at',
    ];

    public static ?string $displayField = null;

    protected $table = 'event_division_standings';

    protected $primaryKey = 'id_standing';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'event_division_id',
        'participation_id',
        'points_total',
        'wins',
        'rank',
    ];

    protected $casts = [
        'points_total' => 'integer',
        'wins' => 'float',
        'rank' => 'integer',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function eventDivision(): BelongsTo
    {
        return $this->belongsTo(EventDivision::class, 'event_division_id', 'id_event_division');
    }

    public function participation(): BelongsTo
    {
        return $this->belongsTo(EventParticipation::class, 'participation_id', 'id_participation');
    }
}
