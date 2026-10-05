<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @OA\Schema(
 *     schema="Location",
 *     required={"name", "club_id", "address", "total_indoors_courts", "total_outdoor_courts"},
 *     @OA\Property(
 *         property="id_location",
 *         type="integer",
 *         format="int64",
 *         readOnly=true,
 *         title="ID",
 *         description="Unique Identifier"
 *     ),
 *     @OA\Property(
 *         property="club_id",
 *         type="integer",
 *         format="int64",
 *         minimum=1,
 *         title="Club",
 *         description="Club of the location"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         maxLength=30,
 *         title="Name",
 *         description="Name of the padel location",
 *         example="Central Court"
 *     ),
 *     @OA\Property(
 *         property="address",
 *         type="string",
 *         maxLength=200,
 *         title="Address",
 *         description="Full address of the location",
 *         example="123 Sports Street, Bucharest"
 *     ),
 *     @OA\Property(
 *         property="total_indoors_courts",
 *         type="integer",
 *         minimum=0,
 *         title="Indoor Courts",
 *         description="Number of indoor courts",
 *         example=4
 *     ),
 *     @OA\Property(
 *         property="total_outdoor_courts",
 *         type="integer",
 *         minimum=0,
 *         title="Outdoor Courts",
 *         description="Number of outdoor courts",
 *         example=2
 *     ),
 *     @OA\Property(
 *         property="inserted_at",
 *         type="integer",
 *         format="timestamp",
 *         readOnly=true,
 *         title="Created At",
 *         description="Timestamp when the location was created"
 *     ),
 *     @OA\Property(
 *         property="updated_at",
 *         type="integer",
 *         format="timestamp",
 *         nullable=true,
 *         readOnly=true,
 *         title="Updated At",
 *         description="Timestamp when the location was last updated"
 *     ),
 *     @OA\Property(
 *         property="club",
 *         ref="#/components/schemas/Club",
 *         title="Club",
 *         description="Related club object",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="events",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/Event"),
 *         title="Events",
 *         description="Array of related editions",
 *         nullable=true
 *     )
 * )
 */
class Location extends Model
{
    use HasFactory;
    use HasResourceMetadata;

    /**
     * Fields that can be searched
     */
    public static array $searchableFields = [
        'name',
        'address',
    ];

    /**
     * Fields that can be filtered
     */
    public static array $filterableFields = [
        'club_id',
        'total_indoors_courts',
        'total_outdoor_courts',
    ];

    /**
     * Fields that can be sorted
     */
    public static array $sortableFields = [
        'id_location',
        'name',
        'address',
        'club_id',
        'total_indoors_courts',
        'total_outdoor_courts',
        'inserted_at',
        'updated_at',
    ];

    /**
     * Field that will be used to display the location in the UI
     */
    public static ?string $displayField = 'name';

    protected $table = 'locations';

    protected $primaryKey = 'id_location';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';
    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'club_id',
        'name',
        'address',
        'total_indoors_courts',
        'total_outdoor_courts',
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

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class, 'club_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PadelEvent::class, 'location_id');
    }

    public function courts(): HasMany
    {
        return $this->hasMany(Court::class, 'location_id', 'id_location')
            ->orderBy('display_order');
    }
}
