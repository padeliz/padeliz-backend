<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="PrizeFromClub",
 *     required={"club_id", "prize_id"},
 *     @OA\Property(property="id_prize_from_club", type="integer", format="int64"),
 *     @OA\Property(property="club_id", type="integer", format="int64"),
 *     @OA\Property(property="prize_id", type="integer", format="int64"),
 *     @OA\Property(property="image", type="string", nullable=true),
 *     @OA\Property(property="box_1_text", type="string", nullable=true),
 *     @OA\Property(property="box_1_bg_color", type="string", nullable=true),
 *     @OA\Property(property="box_1_text_color", type="string", nullable=true),
 *     @OA\Property(property="box_2_text", type="string", nullable=true),
 *     @OA\Property(property="box_2_bg_color", type="string", nullable=true),
 *     @OA\Property(property="box_2_text_color", type="string", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(
 *         property="club",
 *         ref="#/components/schemas/Club"
 *     ),
 *     @OA\Property(
 *         property="prize",
 *         ref="#/components/schemas/Prize"
 *     )
 * )
 */
class PrizeFromClub extends Model
{
    use HasFactory;

    protected $table = 'pm_prizes_from_clubs';
    protected $primaryKey = 'id_club_prize';
    public $timestamps = false;

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

    public function club()
    {
        return $this->belongsTo(Club::class, 'club_id');
    }

    public function prize()
    {
        return $this->belongsTo(Prize::class, 'prize_id');
    }
}
