<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Club extends Model
{
    use HasFactory;

    protected $table = 'pm_clubs';
    protected $primaryKey = 'id_club';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'city',
    ];

    public function locations()
    {
        return $this->hasMany(Location::class, 'club_id');
    }

    public function prizesFromClubs()
    {
        return $this->hasMany(PrizeFromClub::class, 'club_id');
    }
}
