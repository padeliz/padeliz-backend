<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="MatchesTeam",
 *     required={"player_1", "player_2"},
 *     @OA\Property(property="id_team", type="integer", format="int64"),
 *     @OA\Property(property="player_1", type="integer", format="int64"),
 *     @OA\Property(property="player_2", type="integer", format="int64"),
 *     @OA\Property(
 *         property="player1",
 *         ref="#/components/schemas/Player"
 *     ),
 *     @OA\Property(
 *         property="player2",
 *         ref="#/components/schemas/Player"
 *     )
 * )
 */

class MatchesTeam extends Model
{
    use HasFactory;

    protected $table = 'matches_teams';
    protected $primaryKey = 'id_team';
    public $timestamps = false;

    protected $fillable = [
        'player_1',
        'player_2',
        'inserted_at',
        'updated_at',
    ];

    public function player1()
    {
        return $this->belongsTo(Player::class, 'player_1', 'id_player');
    }

    public function player2()
    {
        return $this->belongsTo(Player::class, 'player_2', 'id_player');
    }
}
