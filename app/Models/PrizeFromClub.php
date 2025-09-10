<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="PrizeFromClub",
 *     required={"club_id", "prize_id"},
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
 *     @OA\Property(property="image", type="string", maxLength=100, nullable=true, title="Image", description="URL or path to the prize image"),
 *     @OA\Property(property="box_1_text", type="string", maxLength=200, nullable=true, title="Box 1 Text", description="Text content for the first box"),
 *     @OA\Property(
 *         property="box_1_bg_color",
 *         type="string",
 *         maxLength=7,
 *         nullable=true,
 *         pattern="^#[0-9A-F]{6}$",
 *         title="Box 1 Background",
 *         description="Box 1 background hex color",
 *         example="#FF0000"
 *     ),
 *     @OA\Property(
 *         property="box_1_text_color",
 *         type="string",
 *         maxLength=7,
 *         nullable=true,
 *         pattern="^#[0-9A-F]{6}$",
 *         title="Box 1 Text Color",
 *         description="Box 1 text hex color",
 *         example="#FFFFFF"
 *     ),
 *     @OA\Property(property="box_2_text", type="string", maxLength=200, nullable=true, title="Box 2 Text", description="Text content for the second box"),
 *     @OA\Property(
 *         property="box_2_bg_color",
 *         type="string",
 *         maxLength=7,
 *         nullable=true,
 *         pattern="^#[0-9A-F]{6}$",
 *         title="Box 2 Background",
 *         description="Box 2 background hex color",
 *         example="#0000FF"
 *     ),
 *     @OA\Property(
 *         property="box_2_text_color",
 *         type="string",
 *         maxLength=7,
 *         nullable=true,
 *         pattern="^#[0-9A-F]{6}$",
 *         title="Box 2 Text Color",
 *         description="Box 2 text hex color",
 *         example="#FFFFFF"
 *     ),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", title="Created At", description="Timestamp when the prize from club was created"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, title="Updated At", description="Timestamp when the prize from club was last updated"),
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
        'box_1_bg_color',
        'box_1_text_color',
        'box_2_bg_color',
        'box_2_text_color',
    ];

    /**
     * Fields that can be sorted
     */
    public static array $sortableFields = [
        'club_id',
        'prize_id',
        'inserted_at',
        'updated_at',
    ];

    protected $table = 'prizes_from_clubs';

    protected $primaryKey = 'id_club_prize';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';
    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'club_id',
        'prize_id',
        'image',
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

    public function club()
    {
        return $this->belongsTo(Club::class, 'club_id');
    }

    public function prize()
    {
        return $this->belongsTo(Prize::class, 'prize_id');
    }
}
