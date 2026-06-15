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
 *         title="ID",
 *         description="Unique Identifier"
 *     ),
 *     @OA\Property(
 *         property="location_id",
 *         type="integer",
 *         format="int64",
 *         minimum=1,
 *         title="Location",
 *         description="Location of the edition"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         maxLength=25,
 *         title="Name",
 *         description="Edition name",
 *         example="Summer 2024"
 *     ),
 *     @OA\Property(
 *         property="date",
 *         type="string",
 *         format="date",
 *         pattern="^\\d{4}-\\d{2}-\\d{2}$",
 *         title="Date",
 *         description="Edition date in YYYY-MM-DD format",
 *         example="2024-06-15"
 *     ),
 *     @OA\Property(
 *         property="entry_fee",
 *         type="integer",
 *         minimum=0,
 *         title="Entry Fee",
 *         description="Entry fee in RON",
 *         example=55
 *     ),
 *     @OA\Property(
 *         property="students_entry_fee",
 *         type="integer",
 *         minimum=0,
 *         title="Students Entry Fee",
 *         description="Students entry fee in RON",
 *         example=45
 *     ),
 *     @OA\Property(
 *         property="arriving_time",
 *         type="string",
 *         format="time",
 *         pattern="^([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$",
 *         title="Arriving Time",
 *         description="Arriving time in HH:MM:SS format",
 *         example="09:00:00"
 *     ),
 *     @OA\Property(
 *         property="closing_time",
 *         type="string",
 *         format="time",
 *         pattern="^([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$",
 *         title="Closing Time",
 *         description="Closing time in HH:MM:SS format",
 *         example="18:00:00"
 *     ),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true, title="Created At", description="Timestamp when the edition was created"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true, title="Updated At", description="Timestamp when the edition was last updated"),
 *     @OA\Property(
 *         property="location",
 *         ref="#/components/schemas/Location",
 *         title="Location",
 *         description="Related location object",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="divisions",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/EditionDivision"),
 *         title="Divisions",
 *         description="Array of related edition divisions",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="participations",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/EditionParticipation"),
 *         title="Participations",
 *         description="Array of related participations",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="lotteries",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/EditionLottery"),
 *         title="Lotteries",
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
        'id_edition',
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

    /**
     * Field that will be used to display the edition in the UI
     */
    public static ?string $displayField = 'name';

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

    protected $appends = [
        'formatted_date',
        'formatted_arriving_time',
        'formatted_closing_time',
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

    /**
     * Get the formatted date as Y-m-d
     */
    public function getFormattedDateAttribute()
    {
        if ($this->attributes['date']) {
            return $this->asDate($this->attributes['date'])->format('Y-m-d');
        }

        return null;
    }

    /**
     * Get the formatted arriving time as H:i:s
     */
    public function getFormattedArrivingTimeAttribute()
    {
        if ($this->attributes['arriving_time']) {
            return $this->asDateTime($this->attributes['arriving_time'])->format('H:i:s');
        }

        return null;
    }

    /**
     * Get the formatted closing time as H:i:s
     */
    public function getFormattedClosingTimeAttribute()
    {
        if ($this->attributes['closing_time']) {
            return $this->asDateTime($this->attributes['closing_time'])->format('H:i:s');
        }

        return null;
    }
}
