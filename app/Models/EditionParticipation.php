<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="EditionParticipation",
 *     required={"edition_id", "player_id", "division_id"},
 *     @OA\Property(property="id_participation", type="integer", format="int64"),
 *     @OA\Property(property="edition_id", type="integer", format="int64"),
 *     @OA\Property(property="player_id", type="integer", format="int64"),
 *     @OA\Property(property="division_id", type="integer", format="int64"),
 *     @OA\Property(property="is_minor", type="boolean"),
 *     @OA\Property(property="is_student", type="boolean"),
 *     @OA\Property(property="discount", type="number", nullable=true),
 *     @OA\Property(property="payment", type="number", nullable=true),
 *     @OA\Property(property="absence_reason", type="string", nullable=true),
 *     @OA\Property(property="ban_reason", type="string", nullable=true),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true),
 *     @OA\Property(
 *         property="player",
 *         ref="#/components/schemas/Player"
 *     ),
 *     @OA\Property(
 *         property="division",
 *         ref="#/components/schemas/Division"
 *     )
 * )
 */
class EditionParticipation extends Model
{
    use HasFactory;

    protected $table = 'edition_participations';

    protected $primaryKey = 'id_participation';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';
    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'edition_id',
        'player_id',
        'division_id',
        'is_minor',
        'is_student',
        'discount',
        'payment',
        'absence_reason',
        'ban_reason',
    ];

    protected $casts = [
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
        'is_minor' => 'boolean',
        'is_student' => 'boolean',
    ];

    public function edition()
    {
        return $this->belongsTo(Edition::class, 'edition_id');
    }

    public function player()
    {
        return $this->belongsTo(Player::class, 'player_id');
    }

    public function division()
    {
        return $this->belongsTo(Division::class, 'division_id');
    }
}
