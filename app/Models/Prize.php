<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Schema(
 *     schema="Prize",
 *     required={"name", "box_1_text", "box_1_bg_color", "box_1_text_color"},
 *     @OA\Property(
 *         property="id_prize",
 *         type="integer",
 *         format="int64",
 *         readOnly=true,
 *         title="ID",
 *         description="Unique Identifier"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         maxLength=60,
 *         title="Name",
 *         description="Name of the prize"
 *     ),
 *     @OA\Property(
 *         property="media_path",
 *         type="string",
 *         maxLength=512,
 *         nullable=true,
 *         readOnly=true,
 *         title="Media Path",
 *         description="Storage path to the prize media"
 *     ),
 *     @OA\Property(
 *         property="url",
 *         type="string",
 *         nullable=true,
 *         readOnly=true,
 *         title="Media URL",
 *         description="Public URL for the prize media",
 *         x={
 *             "padeliz-upload": {
 *                 "mode": "single",
 *                 "max_files": 1,
 *                 "max_size_kb": 30720,
 *                 "form_field": "file",
 *                 "accept": {"0":"image/jpeg","1":"image/png","2":"image/gif","3":"image/webp","4":"video/mp4","5":"video/webm","6":"video/quicktime"},
 *                 "upload": {"method":"POST","path":"/api/v1/manager/prizes/{id}/media"},
 *                 "delete": {"method":"DELETE","path":"/api/v1/manager/prizes/{id}/media"}
 *             }
 *         }
 *     ),
 *     @OA\Property(
 *         property="template",
 *         type="string",
 *         enum={"IMAGE_LEFT", "IMAGE_TOP", "IMAGE_RIGHT", "IMAGE_MIDDLE", "IMAGE_BOTTOM"},
 *         nullable=true,
 *         title="Template",
 *         description="Layout template for the prize card"
 *     ),
 *     @OA\Property(
 *         property="box_1_text",
 *         type="string",
 *         maxLength=200,
 *         title="Box 1 Text",
 *         description="Text content for the first box"
 *     ),
 *     @OA\Property(
 *         property="box_1_bg_color",
 *         type="string",
 *         format="color",
 *         maxLength=7,
 *         pattern="^#[0-9A-Fa-f]{6}$",
 *         title="Box 1 Background",
 *         description="Box 1 background hex color",
 *         example="#FF0000"
 *     ),
 *     @OA\Property(
 *         property="box_1_text_color",
 *         type="string",
 *         format="color",
 *         maxLength=7,
 *         pattern="^#[0-9A-Fa-f]{6}$",
 *         title="Box 1 Text Color",
 *         description="Box 1 text hex color",
 *         example="#FFFFFF"
 *     ),
 *     @OA\Property(
 *         property="box_2_text",
 *         type="string",
 *         maxLength=200,
 *         nullable=true,
 *         title="Box 2 Text",
 *         description="Text content for the second box"
 *     ),
 *     @OA\Property(
 *         property="box_2_bg_color",
 *         type="string",
 *         format="color",
 *         maxLength=7,
 *         nullable=true,
 *         pattern="^#[0-9A-Fa-f]{6}$",
 *         title="Box 2 Background",
 *         description="Box 2 background hex color",
 *         example="#0000FF"
 *     ),
 *     @OA\Property(
 *         property="box_2_text_color",
 *         type="string",
 *         format="color",
 *         maxLength=7,
 *         nullable=true,
 *         pattern="^#[0-9A-Fa-f]{6}$",
 *         title="Box 2 Text Color",
 *         description="Box 2 text hex color",
 *         example="#FFFFFF"
 *     ),
 *     @OA\Property(
 *         property="inserted_at",
 *         type="integer",
 *         format="timestamp",
 *         readOnly=true,
 *         title="Created At",
 *         description="Timestamp when the prize was created"
 *     ),
 *     @OA\Property(
 *         property="updated_at",
 *         type="integer",
 *         format="timestamp",
 *         nullable=true,
 *         readOnly=true,
 *         title="Updated At",
 *         description="Timestamp when the prize was last updated"
 *     ),
 *     @OA\Property(
 *         property="lotteryPrizes",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/EditionLotteryPrize"),
 *         title="Lottery Prizes",
 *         description="Array of related lottery prize lines",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="sponsorPrizes",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/SponsorPrize"),
 *         title="Sponsor Prizes",
 *         description="Array of related sponsor prize overlays",
 *         nullable=true
 *     )
 * )
 *
 * @property-read null|string $url
 */
class Prize extends Model
{
    use HasFactory;
    use HasResourceMetadata;

    /**
     * Fields that can be searched
     */
    public static array $searchableFields = [
        'name',
        'box_1_text',
        'box_2_text',
    ];

    /**
     * Fields that can be filtered
     */
    public static array $filterableFields = [
        'template',
        'box_1_bg_color',
        'box_1_text_color',
        'box_2_bg_color',
        'box_2_text_color',
    ];

    /**
     * Fields that can be sorted
     */
    public static array $sortableFields = [
        'id_prize',
        'name',
        'template',
        'inserted_at',
        'updated_at',
    ];

    /**
     * Field that will be used to display the prize in the UI
     */
    public static ?string $displayField = 'name';

    protected $table = 'prizes';

    protected $primaryKey = 'id_prize';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';
    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'name',
        'media_path',
        'template',
        'box_1_text',
        'box_1_bg_color',
        'box_1_text_color',
        'box_2_text',
        'box_2_bg_color',
        'box_2_text_color',
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

    public function lotteryPrizes(): HasMany
    {
        return $this->hasMany(EditionLotteryPrize::class, 'prize_id', 'id_prize');
    }

    public function sponsorPrizes(): HasMany
    {
        return $this->hasMany(SponsorPrize::class, 'prize_id', 'id_prize');
    }

    public function storageDisk(): string
    {
        return config('filesystems.user_uploads_disk', 'public');
    }

    public function getUrlAttribute(): ?string
    {
        if ($this->media_path === null || $this->media_path === '') {
            return null;
        }

        return Storage::disk($this->storageDisk())->url($this->media_path);
    }
}
