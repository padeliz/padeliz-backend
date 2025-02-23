<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
