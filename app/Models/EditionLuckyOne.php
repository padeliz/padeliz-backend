<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="EditionLuckyOne",
 *     required={"lottery_id", "drawn_participation_id"},
 *     @OA\Property(property="id_lucky_one", type="integer", format="int64"),
 *     @OA\Property(property="lottery_id", type="integer", format="int64"),
 *     @OA\Property(property="drawn_participation_id", type="integer", format="int64"),
 *     @OA\Property(property="drawn_at", type="integer", format="int64"),
 *     @OA\Property(property="available_at", type="integer", format="int64", nullable=true),
 *     @OA\Property(property="announced_at", type="integer", format="int64", nullable=true),
 *     @OA\Property(property="accepted_at", type="integer", format="int64", nullable=true),
 *     @OA\Property(property="rejected_at", type="integer", format="int64", nullable=true),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true),
 *     @OA\Property(
 *         property="drawnParticipation",
 *         ref="#/components/schemas/EditionParticipation"
 *     )
 * )
 */

class EditionLuckyOne extends Model
{
    use HasFactory;

    protected $table = 'edition_lucky_ones';
    protected $primaryKey = 'id_lucky_one';
    public $timestamps = true;

    protected $dateFormat = 'U';

    const CREATED_AT = 'inserted_at';
    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'lottery_id',
        'drawn_participation_id',
        'drawn_at',
        'available_at',
        'announced_at',
        'accepted_at',
        'rejected_at',
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

    public function lottery()
    {
        return $this->belongsTo(EditionLottery::class, 'lottery_id');
    }

    public function participation()
    {
        return $this->belongsTo(EditionParticipation::class, 'drawn_participation_id');
    }
}
