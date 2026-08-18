<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @OA\Schema(
 *     schema="EditionLotteryRule",
 *     required={"lottery_prize_id", "rule_prize_quantity"},
 *     @OA\Property(property="id_lottery_rule", type="integer", format="int64", readOnly=true),
 *     @OA\Property(property="lottery_prize_id", type="integer", format="int64"),
 *     @OA\Property(property="rule_prize_quantity", type="integer"),
 *     @OA\Property(property="orderliness", type="integer", default=0),
 *     @OA\Property(property="edition_division_id", type="integer", format="int64", nullable=true),
 *     @OA\Property(property="gender", type="string", enum={"MALE", "FEMALE"}, nullable=true),
 *     @OA\Property(property="has_social_status", type="string", enum={"STUDENT", "ADULT"}, nullable=true),
 *     @OA\Property(property="legal_age", type="string", enum={"MINOR", "MAJOR"}, nullable=true),
 *     @OA\Property(property="is_younger_than", type="integer", nullable=true),
 *     @OA\Property(property="is_older_than", type="integer", nullable=true),
 *     @OA\Property(property="has_tshirt_size", type="string", enum={"XS", "S", "M", "L", "XL", "XXL"}, nullable=true),
 *     @OA\Property(property="is_guest", type="boolean", nullable=true),
 *     @OA\Property(property="is_padel_first_timer", type="boolean", nullable=true),
 *     @OA\Property(property="is_event_first_timer", type="boolean", nullable=true),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true),
 *     @OA\Property(
 *         property="edition_division",
 *         ref="#/components/schemas/EditionDivision",
 *         title="Edition Division",
 *         description="Related edition division object",
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
class EditionLotteryRule extends Model
{
    use HasFactory;

    public static array $searchableFields = [
        'gender',
        'has_social_status',
        'legal_age',
        'has_tshirt_size',
    ];

    public static array $filterableFields = [
        'lottery_prize_id',
        'edition_division_id',
        'gender',
        'has_social_status',
        'legal_age',
    ];

    public static array $sortableFields = [
        'id_lottery_rule',
        'lottery_prize_id',
        'rule_prize_quantity',
        'orderliness',
        'inserted_at',
        'updated_at',
    ];

    public static ?string $displayField = null;

    protected $table = 'edition_lottery_rules';

    protected $primaryKey = 'id_lottery_rule';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';
    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'lottery_prize_id',
        'rule_prize_quantity',
        'orderliness',
        'edition_division_id',
        'gender',
        'has_social_status',
        'legal_age',
        'is_younger_than',
        'is_older_than',
        'has_tshirt_size',
        'is_guest',
        'is_padel_first_timer',
        'is_event_first_timer',
    ];

    protected $casts = [
        'rule_prize_quantity' => 'integer',
        'orderliness' => 'integer',
        'is_younger_than' => 'integer',
        'is_older_than' => 'integer',
        'is_guest' => 'boolean',
        'is_padel_first_timer' => 'boolean',
        'is_event_first_timer' => 'boolean',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function lotteryPrize(): BelongsTo
    {
        return $this->belongsTo(EditionLotteryPrize::class, 'lottery_prize_id', 'id_lottery_prize');
    }

    public function editionDivision(): BelongsTo
    {
        return $this->belongsTo(EditionDivision::class, 'edition_division_id', 'id_edition_division');
    }

    public function luckies(): HasMany
    {
        return $this->hasMany(EditionLotteryLucky::class, 'lottery_rule_id', 'id_lottery_rule')
            ->orderBy('inserted_at')
            ->orderBy('id_lucky');
    }
}
