<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @OA\Schema(
 *     schema="EventDivisionCourt",
 *     required={"event_division_id", "court_id"},
 *     @OA\Property(property="id_event_division_court", type="integer", format="int64", readOnly=true),
 *     @OA\Property(property="event_division_id", type="integer", format="int64"),
 *     @OA\Property(property="court_id", type="integer", format="int64"),
 *     @OA\Property(property="display_order", type="integer", default=0),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true)
 * )
 */
class EventDivisionCourt extends Pivot
{
    use HasFactory;

    protected $table = 'event_division_courts';

    protected $primaryKey = 'id_event_division_court';

    public $incrementing = true;

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'event_division_id',
        'court_id',
        'display_order',
    ];

    protected $casts = [
        'display_order' => 'integer',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function eventDivision(): BelongsTo
    {
        return $this->belongsTo(EventDivision::class, 'event_division_id', 'id_event_division');
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class, 'court_id', 'id_court');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(GameMatch::class, 'event_division_court_id', 'id_event_division_court');
    }
}
