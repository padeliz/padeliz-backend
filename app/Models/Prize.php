<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
 *         property="image",
 *         type="string",
 *         maxLength=100,
 *         nullable=true,
 *         title="Image",
 *         description="URL or path to the prize image"
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
 *         maxLength=7,
 *         pattern="^#[0-9A-F]{6}$",
 *         title="Box 1 Background",
 *         description="Box 1 background hex color",
 *         example="#FF0000"
 *     ),
 *     @OA\Property(
 *         property="box_1_text_color",
 *         type="string",
 *         maxLength=7,
 *         pattern="^#[0-9A-F]{6}$",
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
 *     @OA\Property(
 *         property="inserted_at",
 *         type="integer",
 *         format="timestamp",
 *         title="Created At",
 *         description="Timestamp when the prize was created"
 *     ),
 *     @OA\Property(
 *         property="updated_at",
 *         type="integer",
 *         format="timestamp",
 *         nullable=true,
 *         title="Updated At",
 *         description="Timestamp when the prize was last updated"
 *     ),
 *     @OA\Property(
 *         property="clubPrizes",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/PrizeFromClub"),
 *         title="Club Prizes",
 *         description="Array of related club prizes",
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
        'box_1_bg_color',
        'box_1_text_color',
        'box_2_bg_color',
        'box_2_text_color',
    ];

    /**
     * Fields that can be sorted
     */
    public static array $sortableFields = [
        'name',
        'inserted_at',
        'updated_at',
    ];

    protected $table = 'prizes';

    protected $primaryKey = 'id_prize';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';
    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'name',
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

    public function clubPrizes()
    {
        return $this->hasMany(PrizeFromClub::class, 'prize_id', 'id_prize');
    }

    public function lotteries()
    {
        return $this->hasMany(EditionLottery::class, 'prize_id');
    }
}
