<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="EditionLottery",
 *     required={"edition_id", "orderliness", "total_draws_nr"},
 *     @OA\Property(property="id_lottery", type="integer", format="int64"),
 *     @OA\Property(property="edition_id", type="integer", format="int64"),
 *     @OA\Property(
 *         property="orderliness",
 *         type="integer",
 *         minimum=0,
 *         description="Orderliness score",
 *         example=5
 *     ),
 *     @OA\Property(property="division_id", type="integer", format="int64", nullable=true),
 *     @OA\Property(property="prize_id", type="integer", format="int64", nullable=true),
 *     @OA\Property(
 *         property="total_draws_nr",
 *         type="integer",
 *         minimum=1,
 *         description="Total number of draws",
 *         example=10
 *     ),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true),
 *     @OA\Property(
 *         property="prize",
 *         ref="#/components/schemas/Prize"
 *     ),
 *     @OA\Property(
 *         property="division",
 *         ref="#/components/schemas/Division"
 *     ),
 *     @OA\Property(
 *         property="luckyOnes",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/EditionLuckyOne")
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
        'division_id',
        'prize_id',
        'orderliness',
        'total_draws_nr',
    ];

    /**
     * Fields that can be sorted
     */
    public static array $sortableFields = [
        'edition_id',
        'division_id',
        'prize_id',
        'orderliness',
        'total_draws_nr',
        'inserted_at',
        'updated_at',
    ];

    protected $table = 'edition_lotteries';

    protected $primaryKey = 'id_lottery';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';
    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'edition_id',
        'division_id',
        'prize_id',
        'orderliness',
        'total_draws_nr',
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

    public function edition()
    {
        return $this->belongsTo(Edition::class, 'edition_id');
    }

    public function division()
    {
        return $this->belongsTo(Division::class, 'division_id');
    }

    public function prize()
    {
        return $this->belongsTo(Prize::class, 'prize_id');
    }

    public function luckyOnes()
    {
        return $this->hasMany(EditionLuckyOne::class, 'lottery_id');
    }
}
