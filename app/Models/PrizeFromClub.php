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
 *     schema="PrizeFromClub",
 *     required={"prize_id"},
 *     @OA\Property(
 *         property="id_club_prize",
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
 *         nullable=true,
 *         minimum=1,
 *         title="Club",
 *         description="Club of the prize from club"
 *     ),
 *     @OA\Property(
 *         property="prize_id",
 *         type="integer",
 *         format="int64",
 *         minimum=1,
 *         title="Prize",
 *         description="Prize of the prize from club"
 *     ),
 *     @OA\Property(property="media_path", type="string", maxLength=512, nullable=true, title="Media Path", description="Storage path to the prize media"),
 *     @OA\Property(property="url", type="string", nullable=true, title="Media URL", description="Public URL for the prize media"),
 *     @OA\Property(
 *         property="template",
 *         type="string",
 *         enum={"IMAGE_LEFT", "IMAGE_TOP", "IMAGE_RIGHT", "IMAGE_MIDDLE", "IMAGE_BOTTOM"},
 *         nullable=true,
 *         title="Template",
 *         description="Layout template for the prize card"
 *     ),
 *     @OA\Property(property="box_1_text", type="string", maxLength=200, nullable=true, title="Box 1 Text", description="Text content for the first box"),
 *     @OA\Property(
 *         property="box_1_bg_color",
 *         type="string",
 *         format="color",
 *         maxLength=7,
 *         nullable=true,
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
 *         nullable=true,
 *         pattern="^#[0-9A-Fa-f]{6}$",
 *         title="Box 1 Text Color",
 *         description="Box 1 text hex color",
 *         example="#FFFFFF"
 *     ),
 *     @OA\Property(property="box_2_text", type="string", maxLength=200, nullable=true, title="Box 2 Text", description="Text content for the second box"),
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
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true, title="Created At", description="Timestamp when the prize from club was created"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true, title="Updated At", description="Timestamp when the prize from club was last updated"),
 *     @OA\Property(
 *         property="club",
 *         ref="#/components/schemas/Club",
 *         title="Club",
 *         description="Related club object",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="prize",
 *         ref="#/components/schemas/Prize",
 *         title="Prize",
 *         description="Related prize object",
 *         nullable=true
 *     )
 * )
 *
 * @property-read null|string $url
 */
class PrizeFromClub extends Model
{
    use HasFactory;
    use HasResourceMetadata;

    /**
     * Fields that can be searched
     */
    public static array $searchableFields = [
        'box_1_text',
        'box_2_text',
    ];

    /**
     * Fields that can be filtered
     */
    public static array $filterableFields = [
        'club_id',
        'prize_id',
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
        'id_club_prize',
        'club_id',
        'prize_id',
        'template',
        'inserted_at',
        'updated_at',
    ];

    /**
     * Field that will be used to display the prize from club in the UI
     */
    public static ?string $displayField = null;

    protected $table = 'prizes_from_clubs';

    protected $primaryKey = 'id_club_prize';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';
    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'club_id',
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

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class, 'club_id');
    }

    public function prize(): BelongsTo
    {
        return $this->belongsTo(Prize::class, 'prize_id');
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
