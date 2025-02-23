<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MatchesTeam extends Model
{
    use HasFactory;

    protected $table = 'pm_matches_teams';
    protected $primaryKey = 'id_team';
    public $timestamps = false;

    protected $fillable = [
        'player_1',
        'player_2',
        'inserted_at',
        'updated_at',
    ];
}
