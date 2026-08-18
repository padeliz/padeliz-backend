<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @OA\Schema(
 *     schema="EditionLottery",
 *     required={"edition_id", "name"},
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
 *         property="name",
 *         type="string",
 *         maxLength=60,
 *         title="Name",
 *         description="Display name of the lottery"
 *     ),
 *     @OA\Property(
 *         property="display_order",
 *         type="integer",
 *         title="Display Order",
 *         example=0
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
 *         property="lotteryPrizes",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/EditionLotteryPrize"),
 *         title="Lottery Prizes",
 *         description="Prize lines of this lottery",
 *         nullable=true
 *     )
 * )
 */
class EditionLottery extends Model
{
    use HasFactory;
    use HasResourceMetadata;

    public static array $searchableFields = [
        'name',
    ];

    public static array $filterableFields = [
        'edition_id',
        'name',
        'display_order',
    ];

    public static array $sortableFields = [
        'id_lottery',
        'edition_id',
        'name',
        'display_order',
        'inserted_at',
        'updated_at',
    ];

    public static ?string $displayField = 'name';

    protected $table = 'edition_lotteries';

    protected $primaryKey = 'id_lottery';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'edition_id',
        'name',
        'display_order',
    ];

    protected $casts = [
        'display_order' => 'integer',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class, 'edition_id');
    }

    public function lotteryPrizes(): HasMany
    {
        return $this->hasMany(EditionLotteryPrize::class, 'lottery_id', 'id_lottery');
    }
}
