<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasFactory;

    protected $table = 'pm_locations';
    protected $primaryKey = 'id_location';
    public $timestamps = false;

    protected $fillable = [
        'club_id',
        'name',
        'address',
        'total_indoors_courts',
        'total_outdoor_courts',
    ];

    public function club()
    {
        return $this->belongsTo(Club::class, 'club_id');
    }

    public function editions()
    {
        return $this->hasMany(Edition::class, 'location_id');
    }
}
