<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="Edition",
 *     required={"name", "date", "location_id"},
 *     @OA\Property(property="id_edition", type="integer", format="int64"),
 *     @OA\Property(property="location_id", type="integer", format="int64"),
 *     @OA\Property(property="name", type="string", maxLength=255),
 *     @OA\Property(property="date", type="string", format="date"),
 *     @OA\Property(property="entry_fee", type="number"),
 *     @OA\Property(property="students_entry_fee", type="number"),
 *     @OA\Property(property="arriving_time", type="string", format="time"),
 *     @OA\Property(property="closing_time", type="string", format="time"),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true),
 *     @OA\Property(
 *         property="location",
 *         ref="#/components/schemas/Location"
 *     ),
 *     @OA\Property(
 *         property="divisions",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/EditionDivision")
 *     ),
 *     @OA\Property(
 *         property="participations",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/EditionParticipation")
 *     )
 * )
 */
class Edition extends Model
{
    use HasFactory;

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
        'date',
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
