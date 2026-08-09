<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @OA\Schema(
 *     schema="EditionLottery",
 *     required={"edition_id", "prize_quantity"},
 *     @OA\Property(
 *         property="id_lottery",
 *         type="integer",
 *         format="int64",
 *         readOnly=true,
 *         title="ID",
 *         description="Unique Identifier"
 *     ),
 *     @OA\Property(
 *         property="edition_id",
 *         type="integer",
 *         format="int64",
 *         minimum=1,
 *         title="Edition",
 *         description="Edition of the lottery"
 *     ),
 *     @OA\Property(
 *         property="prize_id",
 *         type="integer",
 *         format="int64",
 *         nullable=true,
 *         minimum=1,
 *         title="Prize",
 *         description="Prize of the lottery"
 *     ),
 *     @OA\Property(
 *         property="prize_quantity",
 *         type="integer",
 *         minimum=1,
 *         title="Prize Quantity",
 *         description="Number of prizes available",
 *         example=3
 *     ),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true, title="Created At", description="Timestamp when the edition lottery was created"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true, title="Updated At", description="Timestamp when the edition lottery was last updated"),
 *     @OA\Property(
 *         property="edition",
 *         ref="#/components/schemas/Edition",
 *         title="Edition",
 *         description="Related edition object",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="prize",
 *         ref="#/components/schemas/Prize",
 *         title="Prize",
 *         description="Related prize object",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="rules",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/EditionLotteryRule"),
 *         title="Rules",
 *         description="Array of related lottery rules",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="luckies",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/EditionLotteryLucky"),
 *         title="Luckies",
 *         description="Array of related lottery luckies",
 *         nullable=true
 *     )
 * )
 */
class EditionLottery extends Model
{
    use HasFactory;
    use HasResourceMetadata;

    /**
     * Fields that can be searched
     */
    public static array $searchableFields = [
        // No searchable fields for this model
    ];

    /**
     * Fields that can be filtered
     */
    public static array $filterableFields = [
        'edition_id',
        'prize_id',
        'prize_quantity',
    ];

    /**
     * Fields that can be sorted
     */
    public static array $sortableFields = [
        'id_lottery',
        'edition_id',
        'prize_id',
        'prize_quantity',
        'inserted_at',
        'updated_at',
    ];

    /**
     * Field that will be used to display the edition lottery in the UI
     */
    public static ?string $displayField = null;

    protected $table = 'edition_lotteries';

    protected $primaryKey = 'id_lottery';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';
    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'edition_id',
        'prize_id',
        'prize_quantity',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
        'prize_quantity' => 'integer',
    ];

    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class, 'edition_id');
    }

    public function prize(): BelongsTo
    {
        return $this->belongsTo(Prize::class, 'prize_id');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(EditionLotteryRule::class, 'lottery_id', 'id_lottery');
    }

    public function luckies(): HasManyThrough
    {
        return $this->hasManyThrough(
            EditionLotteryLucky::class,
            EditionLotteryRule::class,
            'lottery_id',
            'lottery_rule_id',
            'id_lottery',
            'id_lottery_rule'
        );
    }
}
