<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="Prize",
 *     required={"name"},
 *     @OA\Property(property="id_prize", type="integer", format="int64"),
 *     @OA\Property(property="name", type="string", maxLength=255),
 *     @OA\Property(property="image", type="string", nullable=true),
 *     @OA\Property(property="box_1_text", type="string", nullable=true),
 *     @OA\Property(property="box_1_bg_color", type="string", nullable=true),
 *     @OA\Property(property="box_1_text_color", type="string", nullable=true),
 *     @OA\Property(property="box_2_text", type="string", nullable=true),
 *     @OA\Property(property="box_2_bg_color", type="string", nullable=true),
 *     @OA\Property(property="box_2_text_color", type="string", nullable=true),
 *     @OA\Property(
 *         property="clubPrizes",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/PrizeFromClub")
 *     )
 * )
 */

class Prize extends Model
{
    use HasFactory;

    protected $table = 'prizes';
    protected $primaryKey = 'id_prize';
    public $timestamps = false;

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

    public function clubPrizes()
    {
        return $this->hasMany(PrizeFromClub::class, 'prize_id', 'id_prize');
    }

    public function lotteries()
    {
        return $this->hasMany(EditionLottery::class, 'prize_id');
    }
}
