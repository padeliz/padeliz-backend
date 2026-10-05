<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @OA\Schema(
 *     schema="Division",
 *     required={"name", "color", "its_first_event_id"},
 *     @OA\Property(
 *         property="id_division",
 *         type="integer",
 *         format="int64",
 *         readOnly=true,
 *         title="ID",
 *         description="Unique Identifier"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         maxLength=25,
 *         title="Name",
 *         description="Name of the skill division",
 *         example="Advanced"
 *     ),
 *     @OA\Property(
 *         property="color",
 *         type="string",
 *         format="color",
 *         maxLength=7,
 *         pattern="^#[0-9A-Fa-f]{6}$",
 *         title="Color",
 *         description="Hex color code for the division",
 *         example="#FF0000"
 *     ),
 *     @OA\Property(
 *         property="its_first_event_id",
 *         type="integer",
 *         format="int64",
 *         minimum=1,
 *         title="First PadelEvent",
 *         description="First edition for this division",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="its_first_event",
 *         ref="#/components/schemas/Event",
 *         title="First PadelEvent",
 *         description="Related first edition object",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="inserted_at",
 *         type="integer",
 *         format="timestamp",
 *         readOnly=true,
 *         title="Created At",
 *         description="Timestamp when the division was created"
 *     ),
 *     @OA\Property(
 *         property="updated_at",
 *         type="integer",
 *         format="timestamp",
 *         nullable=true,
 *         readOnly=true,
 *         title="Updated At",
 *         description="Timestamp when the division was last updated"
 *     ),
 *     @OA\Property(
 *         property="eventDivisions",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/EventDivision"),
 *         title="PadelEvent Divisions",
 *         description="Array of related edition divisions",
 *         nullable=true
 *     )
 * )
 */
class Division extends Model
{
    use HasFactory;
    use HasResourceMetadata;

    /**
     * Fields that can be searched
     */
    public static array $searchableFields = [
        'name',
    ];

    /**
     * Fields that can be filtered
     */
    public static array $filterableFields = [
        'its_first_event_id',
    ];

    /**
     * Fields that can be sorted
     */
    public static array $sortableFields = [
        'id_division',
        'name',
        'color',
        'its_first_event_id',
        'inserted_at',
        'updated_at',
    ];

    /**
     * Field that will be used to display the division in the UI
     */
    public static ?string $displayField = 'name';

    protected $table = 'divisions';

    protected $primaryKey = 'id_division';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';
    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'name',
        'color',
        'its_first_event_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function itsFirstEvent(): BelongsTo
    {
        return $this->belongsTo(PadelEvent::class, 'its_first_event_id', 'id_event');
    }

    public function eventDivisions(): HasMany
    {
        return $this->hasMany(EventDivision::class, 'division_id');
    }

    public function participations(): HasManyThrough
    {
        return $this->hasManyThrough(
            EventParticipation::class,
            EventDivision::class,
            'division_id',
            'event_division_id',
            'id_division',
            'id_event_division'
        );
    }
}
