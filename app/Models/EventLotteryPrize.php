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
 *     schema="EventLotteryPrize",
 *     required={"lottery_id", "prize_id", "prize_quantity"},
 *     @OA\Property(property="id_lottery_prize", type="integer", format="int64", readOnly=true, title="ID"),
 *     @OA\Property(property="lottery_id", type="integer", format="int64", minimum=1, title="Lottery"),
 *     @OA\Property(property="prize_id", type="integer", format="int64", minimum=1, title="Prize"),
 *     @OA\Property(property="prize_quantity", type="integer", minimum=1, title="Prize Quantity", example=3),
 *     @OA\Property(property="sponsor_id", type="integer", format="int64", nullable=true, minimum=1, title="Sponsor"),
 *     @OA\Property(property="display_order", type="integer", title="Display Order", example=0),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true, title="Created At"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true, title="Updated At"),
 *     @OA\Property(property="lottery", ref="#/components/schemas/EventLottery", nullable=true, title="Lottery"),
 *     @OA\Property(property="prize", ref="#/components/schemas/Prize", nullable=true, title="Prize"),
 *     @OA\Property(property="sponsor", ref="#/components/schemas/Sponsor", nullable=true, title="Sponsor"),
 *     @OA\Property(property="rules", type="array", @OA\Items(ref="#/components/schemas/EventLotteryRule"), nullable=true, title="Rules")
 * )
 */
class EventLotteryPrize extends Model
{
    use HasFactory;
    use HasResourceMetadata;

    public static array $searchableFields = [];

    public static array $filterableFields = [
        'lottery_id',
        'prize_id',
        'sponsor_id',
        'prize_quantity',
        'display_order',
    ];

    public static array $sortableFields = [
        'id_lottery_prize',
        'lottery_id',
        'prize_id',
        'sponsor_id',
        'prize_quantity',
        'display_order',
        'inserted_at',
        'updated_at',
    ];

    public static ?string $displayField = null;

    protected $table = 'event_lottery_prizes';

    protected $primaryKey = 'id_lottery_prize';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'lottery_id',
        'prize_id',
        'prize_quantity',
        'sponsor_id',
        'display_order',
    ];

    protected $casts = [
        'prize_quantity' => 'integer',
        'display_order' => 'integer',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function lottery(): BelongsTo
    {
        return $this->belongsTo(EventLottery::class, 'lottery_id', 'id_lottery');
    }

    public function prize(): BelongsTo
    {
        return $this->belongsTo(Prize::class, 'prize_id', 'id_prize');
    }

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class, 'sponsor_id', 'id_sponsor');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(EventLotteryRule::class, 'lottery_prize_id', 'id_lottery_prize');
    }
}
