<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Schema(
 *     schema="Club",
 *     required={"name", "city"},
 *     @OA\Property(
 *         property="id_club",
 *         type="integer",
 *         format="int64",
 *         readOnly=true,
 *         title="ID",
 *         description="Unique Identifier"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         maxLength=30,
 *         title="Name",
 *         description="Name of the padel club"
 *     ),
 *     @OA\Property(
 *         property="city",
 *         type="string",
 *         maxLength=30,
 *         title="City",
 *         description="City where the club is located",
 *         example="Bucharest"
 *     ),
 *     @OA\Property(
 *         property="logo_path",
 *         type="string",
 *         maxLength=512,
 *         nullable=true,
 *         readOnly=true,
 *         title="Logo Path",
 *         description="Club logo path on the uploads disk, used as club-host branding on posters"
 *     ),
 *     @OA\Property(
 *         property="url",
 *         type="string",
 *         nullable=true,
 *         readOnly=true,
 *         title="Logo URL",
 *         description="Public URL for the club logo",
 *         x={
 *             "padeliz-upload": {
 *                 "mode": "single",
 *                 "max_files": 1,
 *                 "max_size_kb": 5120,
 *                 "form_field": "file",
 *                 "accept": {"0":"image/jpeg","1":"image/png","2":"image/gif","3":"image/webp"},
 *                 "upload": {"method":"POST","path":"/api/v1/manager/clubs/{id}/logo"},
 *                 "delete": {"method":"DELETE","path":"/api/v1/manager/clubs/{id}/logo"}
 *             },
 *             "padeliz-image": {"role": "list"}
 *         }
 *     ),
 *     @OA\Property(
 *         property="inserted_at",
 *         type="integer",
 *         format="timestamp",
 *         readOnly=true,
 *         title="Created At",
 *         description="Timestamp when the club was created"
 *     ),
 *     @OA\Property(
 *         property="updated_at",
 *         type="integer",
 *         format="timestamp",
 *         nullable=true,
 *         readOnly=true,
 *         title="Updated At",
 *         description="Timestamp when the club was last updated"
 *     ),
 *     @OA\Property(
 *         property="locations",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/Location"),
 *         title="Locations",
 *         description="Array of related locations",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="sponsors",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/Sponsor"),
 *         title="Sponsors",
 *         description="Sponsors linked to this club",
 *         nullable=true
 *     )
 * )
 *
 * @property-read null|string $url
 */
class Club extends Model
{
    use HasFactory;
    use HasResourceMetadata;

    protected $table = 'clubs';

    protected $primaryKey = 'id_club';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';
    public const UPDATED_AT = 'updated_at';

    /**
     * Fields that can be filled.
     */
    protected $fillable = [
        'name',
        'city',
        'logo_path',
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

    protected $appends = ['url'];

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
        'id_club',
        'name',
        'city',
        'inserted_at',
        'updated_at',
    ];

    /**
     * Field that will be used to display the club in the UI
     */
    public static ?string $displayField = 'name';

    public static array $listFields = [
        'url',
    ];

    public function storageDisk(): string
    {
        return config('filesystems.user_uploads_disk', 'public');
    }

    public function getUrlAttribute(): ?string
    {
        if ($this->logo_path === null || $this->logo_path === '') {
            return null;
        }

        return Storage::disk($this->storageDisk())->url($this->logo_path);
    }

    public function locations()
    {
        return $this->hasMany(Location::class, 'club_id');
    }

    public function sponsors()
    {
        return $this->hasMany(Sponsor::class, 'club_id');
    }
}
