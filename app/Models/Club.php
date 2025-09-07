<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="Club",
 *     required={"name", "city"},
 *     @OA\Property(
 *         property="id_club",
 *         type="integer",
 *         format="int64",
 *         readOnly=true,
 *         description="Primary key - Club ID"
 *     ),
 *     @OA\Property(property="name", type="string", maxLength=30),
 *     @OA\Property(
 *         property="city",
 *         type="string",
 *         maxLength=30,
 *         description="City where the club is located",
 *         example="Bucharest"
 *     ),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true),
 *     @OA\Property(
 *         property="locations",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/Location"),
 *         description="Array of related locations",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="prizes",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/PrizeFromClub"),
 *         description="Array of related club prizes",
 *         nullable=true
 *     )
 * )
 */
class Club extends Model
{
    use HasFactory;
    use HasResourceMetadata;

    /**
     * Fields that can be searched
     */
    public static array $searchableFields = [
        'name',
        'city',
    ];

    /**
     * Fields that can be filtered
     */
    public static array $filterableFields = [
        'city',
    ];

    /**
     * Fields that can be sorted
     */
    public static array $sortableFields = [
        'name',
        'city',
        'inserted_at',
        'updated_at',
    ];

    protected $table = 'clubs';

    protected $primaryKey = 'id_club';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';
    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'name',
        'city',
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

    public function locations()
    {
        return $this->hasMany(Location::class, 'club_id');
    }

    public function prizes()
    {
        return $this->hasMany(PrizeFromClub::class, 'club_id');
    }
}
