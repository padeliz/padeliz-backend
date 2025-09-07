<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="Edition",
 *     required={"name", "date", "location_id"},
 *     @OA\Property(
 *         property="id_edition",
 *         type="integer",
 *         format="int64",
 *         readOnly=true,
 *         description="Primary key - Edition ID"
 *     ),
 *     @OA\Property(
 *         property="location_id",
 *         type="integer",
 *         format="int64",
 *         minimum=1,
 *         description="Foreign key - References Location.id_location"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         maxLength=25,
 *         description="Edition name",
 *         example="Summer 2024"
 *     ),
 *     @OA\Property(
 *         property="date",
 *         type="string",
 *         format="date",
 *         pattern="^\\d{4}-\\d{2}-\\d{2}$",
 *         description="Edition date in YYYY-MM-DD format",
 *         example="2024-06-15"
 *     ),
 *     @OA\Property(
 *         property="entry_fee",
 *         type="integer",
 *         minimum=0,
 *         description="Entry fee in RON",
 *         example=55
 *     ),
 *     @OA\Property(
 *         property="students_entry_fee",
 *         type="integer",
 *         minimum=0,
 *         description="Students entry fee in RON",
 *         example=45
 *     ),
 *     @OA\Property(
 *         property="arriving_time",
 *         type="string",
 *         format="time",
 *         pattern="^([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$",
 *         description="Arriving time in HH:MM:SS format",
 *         example="09:00:00"
 *     ),
 *     @OA\Property(
 *         property="closing_time",
 *         type="string",
 *         format="time",
 *         pattern="^([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$",
 *         description="Closing time in HH:MM:SS format",
 *         example="18:00:00"
 *     ),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true),
 *     @OA\Property(
 *         property="location",
 *         ref="#/components/schemas/Location",
 *         description="Related location object (loaded when requested)",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="divisions",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/EditionDivision"),
 *         description="Array of related edition divisions",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="participations",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/EditionParticipation"),
 *         description="Array of related participations",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="lotteries",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/EditionLottery"),
 *         description="Array of related edition lotteries",
 *         nullable=true
 *     )
 * )
 */
class Edition extends Model
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
        'location_id',
        'entry_fee',
        'students_entry_fee',
    ];

    /**
     * Fields that can be sorted
     */
    public static array $sortableFields = [
        'name',
        'date',
        'location_id',
        'entry_fee',
        'students_entry_fee',
        'arriving_time',
        'closing_time',
        'inserted_at',
        'updated_at',
    ];

    protected $table = 'editions';

    protected $primaryKey = 'id_edition';

    public $timestamps = true;

    protected $dateFormat = 'U'; // handle inserted_at and updated_at as Unix timestamps

    public const CREATED_AT = 'inserted_at';
    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'name',
        'date',
        'location_id',
        'entry_fee',
        'students_entry_fee',
        'arriving_time',
        'closing_time',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'arriving_time' => 'datetime:H:i:s',
        'closing_time' => 'datetime:H:i:s',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function divisions()
    {
        return $this->hasMany(EditionDivision::class, 'edition_id');
    }

    public function participations()
    {
        return $this->hasMany(EditionParticipation::class, 'edition_id');
    }

    public function lotteries()
    {
        return $this->hasMany(EditionLottery::class, 'edition_id');
    }
}
