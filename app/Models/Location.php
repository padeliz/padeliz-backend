<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="Location",
 *     required={"name", "club_id", "address", "total_indoors_courts", "total_outdoor_courts"},
 *     @OA\Property(
 *         property="id_location",
 *         type="integer",
 *         format="int64",
 *         readOnly=true,
 *         description="Primary key - Location ID"
 *     ),
 *     @OA\Property(
 *         property="club_id",
 *         type="integer",
 *         format="int64",
 *         description="Foreign key - References Club.id_club"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         maxLength=30,
 *         description="Location name",
 *         example="Central Court"
 *     ),
 *     @OA\Property(
 *         property="address",
 *         type="string",
 *         maxLength=200,
 *         description="Full address of the location",
 *         example="123 Sports Street, Bucharest"
 *     ),
 *     @OA\Property(
 *         property="total_indoors_courts",
 *         type="integer",
 *         minimum=0,
 *         description="Number of indoor courts",
 *         example=4
 *     ),
 *     @OA\Property(
 *         property="total_outdoor_courts",
 *         type="integer",
 *         minimum=0,
 *         description="Number of outdoor courts",
 *         example=2
 *     ),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true),
 *     @OA\Property(
 *         property="club",
 *         ref="#/components/schemas/Club",
 *         description="Related club object (loaded when requested)",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="editions",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/Edition"),
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
        'name',
        'address',
        'club_id',
        'total_indoors_courts',
        'total_outdoor_courts',
        'inserted_at',
        'updated_at',
    ];

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

    public function club()
    {
        return $this->belongsTo(Club::class, 'club_id');
    }

    public function editions()
    {
        return $this->hasMany(Edition::class, 'location_id');
    }
}
