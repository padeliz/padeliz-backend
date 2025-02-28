<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="EditionLottery",
 *     required={"edition_id", "prize_id"},
 *     @OA\Property(property="id_lottery", type="integer", format="int64"),
 *     @OA\Property(property="edition_id", type="integer", format="int64"),
 *     @OA\Property(property="division_id", type="integer", format="int64", nullable=true),
 *     @OA\Property(property="prize_id", type="integer", format="int64"),
 *     @OA\Property(property="orderliness", type="integer"),
 *     @OA\Property(property="total_draws_nr", type="integer"),
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

    protected $table = 'edition_lotteries';
    protected $primaryKey = 'id_lottery';
    public $timestamps = false;

    protected $fillable = [
        'edition_id',
        'division_id',
        'prize_id',
        'orderliness',
        'total_draws_nr',
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
