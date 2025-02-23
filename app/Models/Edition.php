<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Edition extends Model
{
    use HasFactory;

    protected $table = 'pm_editions';
    protected $primaryKey = 'id_edition';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'date',
        'location_id',
        'entry_fee',
        'students_entry_fee',
        'arriving_time',
        'closing_time',
    ];

    protected $casts = [
        'date' => 'date',
        'arriving_time' => 'datetime',
        'closing_time' => 'datetime',
    ];

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function divisions()
    {
        return $this->hasMany(EditionDivision::class, 'edition_id');
    }

    public function participations()
    {
        return $this->hasMany(EditionParticipation::class, 'edition_id');
    }
}
