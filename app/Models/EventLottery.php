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
 *     schema="EventLottery",
 *     required={"event_id", "name"},
 *     @OA\Property(
 *         property="id_lottery",
 *         type="integer",
 *         format="int64",
 *         readOnly=true,
 *         title="ID",
 *         description="Unique Identifier"
 *     ),
 *     @OA\Property(
 *         property="event_id",
 *         type="integer",
 *         format="int64",
 *         minimum=1,
 *         title="PadelEvent",
 *         description="PadelEvent of the lottery"
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
 *         property="event",
 *         ref="#/components/schemas/Event",
 *         title="PadelEvent",
 *         description="Related edition object",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="lotteryPrizes",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/EventLotteryPrize"),
 *         title="Lottery Prizes",
 *         description="Prize lines of this lottery",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="has_luckies",
 *         type="boolean",
 *         title="Has Luckies",
 *         description="True when any rule under this lottery has at least one lucky"
 *     )
 * )
 */
class EventLottery extends Model
{
    use HasFactory;
    use HasResourceMetadata;

    public static array $searchableFields = [
        'name',
    ];

    public static array $filterableFields = [
        'event_id',
        'name',
        'display_order',
    ];

    public static array $sortableFields = [
        'id_lottery',
        'event_id',
        'name',
        'display_order',
        'inserted_at',
        'updated_at',
    ];

    public static ?string $displayField = 'name';

    protected $table = 'event_lotteries';

    protected $primaryKey = 'id_lottery';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'event_id',
        'name',
        'display_order',
    ];

    protected $casts = [
        'display_order' => 'integer',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(PadelEvent::class, 'event_id');
    }

    public function lotteryPrizes(): HasMany
    {
        return $this->hasMany(EventLotteryPrize::class, 'lottery_id', 'id_lottery');
    }

    public function lotteryRules(): HasManyThrough
    {
        return $this->hasManyThrough(
            EventLotteryRule::class,
            EventLotteryPrize::class,
            'lottery_id',
            'lottery_prize_id',
            'id_lottery',
            'id_lottery_prize'
        );
    }
}
