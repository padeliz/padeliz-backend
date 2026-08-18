<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Schema(
 *     schema="SponsorPrize",
 *     required={"sponsor_id", "prize_id"},
 *     @OA\Property(property="id_sponsor_prize", type="integer", format="int64", readOnly=true, title="ID"),
 *     @OA\Property(property="sponsor_id", type="integer", format="int64", minimum=1, title="Sponsor"),
 *     @OA\Property(property="prize_id", type="integer", format="int64", minimum=1, title="Prize"),
 *     @OA\Property(property="media_path", type="string", maxLength=512, nullable=true, title="Media Path"),
 *     @OA\Property(
 *         property="url",
 *         type="string",
 *         nullable=true,
 *         readOnly=true,
 *         title="Media URL",
 *         x={
 *             "padeliz-upload": {
 *                 "mode": "single",
 *                 "max_files": 1,
 *                 "max_size_kb": 30720,
 *                 "form_field": "file",
 *                 "accept": {"0":"image/jpeg","1":"image/png","2":"image/gif","3":"image/webp","4":"video/mp4","5":"video/webm","6":"video/quicktime"},
 *                 "upload": {"method":"POST","path":"/api/v1/manager/sponsor-prizes/{id}/media"},
 *                 "delete": {"method":"DELETE","path":"/api/v1/manager/sponsor-prizes/{id}/media"}
 *             }
 *         }
 *     ),
 *     @OA\Property(property="template", type="string", enum={"IMAGE_LEFT", "IMAGE_TOP", "IMAGE_RIGHT", "IMAGE_MIDDLE", "IMAGE_BOTTOM"}, nullable=true, title="Template"),
 *     @OA\Property(property="box_1_text", type="string", maxLength=200, nullable=true, title="Box 1 Text"),
 *     @OA\Property(property="box_1_bg_color", type="string", format="color", maxLength=7, nullable=true, pattern="^#[0-9A-Fa-f]{6}$", title="Box 1 Background"),
 *     @OA\Property(property="box_1_text_color", type="string", format="color", maxLength=7, nullable=true, pattern="^#[0-9A-Fa-f]{6}$", title="Box 1 Text Color"),
 *     @OA\Property(property="box_2_text", type="string", maxLength=200, nullable=true, title="Box 2 Text"),
 *     @OA\Property(property="box_2_bg_color", type="string", format="color", maxLength=7, nullable=true, pattern="^#[0-9A-Fa-f]{6}$", title="Box 2 Background"),
 *     @OA\Property(property="box_2_text_color", type="string", format="color", maxLength=7, nullable=true, pattern="^#[0-9A-Fa-f]{6}$", title="Box 2 Text Color"),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true, title="Created At"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true, title="Updated At"),
 *     @OA\Property(property="sponsor", ref="#/components/schemas/Sponsor", nullable=true, title="Sponsor"),
 *     @OA\Property(property="prize", ref="#/components/schemas/Prize", nullable=true, title="Prize")
 * )
 *
 * @property-read null|string $url
 */
class SponsorPrize extends Model
{
    use HasFactory;
    use HasResourceMetadata;

    public static array $searchableFields = [
        'box_1_text',
        'box_2_text',
    ];

    public static array $filterableFields = [
        'sponsor_id',
        'prize_id',
        'template',
        'box_1_bg_color',
        'box_1_text_color',
        'box_2_bg_color',
        'box_2_text_color',
    ];

    public static array $sortableFields = [
        'id_sponsor_prize',
        'sponsor_id',
        'prize_id',
        'template',
        'inserted_at',
        'updated_at',
    ];

    public static ?string $displayField = null;

    protected $table = 'sponsor_prizes';

    protected $primaryKey = 'id_sponsor_prize';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'sponsor_id',
        'prize_id',
        'media_path',
        'template',
        'box_1_text',
        'box_1_bg_color',
        'box_1_text_color',
        'box_2_text',
        'box_2_bg_color',
        'box_2_text_color',
    ];

    protected $casts = [
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    protected $appends = ['url'];

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class, 'sponsor_id', 'id_sponsor');
    }

    public function prize(): BelongsTo
    {
        return $this->belongsTo(Prize::class, 'prize_id', 'id_prize');
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
