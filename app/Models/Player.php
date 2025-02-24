<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="Player",
 *     required={"name", "email", "phone"},
 *     @OA\Property(property="id_player", type="integer", format="int64"),
 *     @OA\Property(property="name", type="string", maxLength=255),
 *     @OA\Property(property="email", type="string", format="email"),
 *     @OA\Property(property="phone", type="string", maxLength=255),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(
 *         property="participations",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/EditionParticipation")
 *     )
 * )
 */

class Player extends Model
{
    use HasFactory;

    protected $table = 'pm_players';
    protected $primaryKey = 'id_player';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'inserted_at',
        'updated_at',
    ];

    public function participations()
    {
        return $this->hasMany(EditionParticipation::class, 'player_id');
    }
}
