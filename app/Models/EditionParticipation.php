<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EditionParticipation extends Model
{
    use HasFactory;

    protected $table = 'pm_edition_participations';
    protected $primaryKey = 'id_participation';
    public $timestamps = false;

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
