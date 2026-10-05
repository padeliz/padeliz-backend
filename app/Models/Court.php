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
 *     schema="Court",
 *     required={"location_id", "name", "is_indoor"},
 *     @OA\Property(property="id_court", type="integer", format="int64", readOnly=true),
 *     @OA\Property(property="location_id", type="integer", format="int64"),
 *     @OA\Property(property="name", type="string", maxLength=30),
 *     @OA\Property(property="is_indoor", type="boolean"),
 *     @OA\Property(property="display_order", type="integer", default=0),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true),
 *     @OA\Property(
 *         property="location",
 *         ref="#/components/schemas/Location",
 *         title="Location",
 *         description="Related location object",
 *         nullable=true
 *     )
 * )
 */
class Court extends Model
{
    use HasFactory;
    use HasResourceMetadata;

    public static array $searchableFields = [
        'name',
    ];

    public static array $filterableFields = [
        'location_id',
        'is_indoor',
        'display_order',
    ];

    public static array $sortableFields = [
        'id_court',
        'location_id',
        'name',
        'is_indoor',
        'display_order',
        'inserted_at',
        'updated_at',
    ];

    public static ?string $displayField = 'name';

    protected $table = 'courts';

    protected $primaryKey = 'id_court';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';
    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'location_id',
        'name',
        'is_indoor',
        'display_order',
    ];

    protected $casts = [
        'is_indoor' => 'boolean',
        'display_order' => 'integer',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id', 'id_location');
    }

    public function eventDivisionCourts(): HasMany
    {
        return $this->hasMany(EventDivisionCourt::class, 'court_id', 'id_court');
    }

    public function eventDivisions(): BelongsToMany
    {
        return $this->belongsToMany(
            EventDivision::class,
            'event_division_courts',
            'court_id',
            'event_division_id',
            'id_court',
            'id_event_division'
        )->using(EventDivisionCourt::class)
            ->withTimestamps('inserted_at', 'updated_at')
            ->withPivot(['id_event_division_court', 'display_order']);
    }
}
