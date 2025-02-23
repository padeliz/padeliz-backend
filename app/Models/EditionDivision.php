<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EditionDivision extends Model
{
    use HasFactory;

    protected $table = 'pm_edition_divisions';
    protected $primaryKey = 'id_edition_division';
    public $timestamps = false;

    protected $fillable = [
        'edition_id',
        'division_id',
        'players_limit',
        'court',
        'playing_start_time',
        'playing_end_time',
        'player_opponents_nr',
        'player_matches_repeat',
        'include_scores',
        'has_demonstrative_match',
        'fixed_teams',
    ];

    protected $casts = [
        'playing_start_time' => 'datetime',
        'playing_end_time' => 'datetime',
    ];

    public function edition()
    {
        return $this->belongsTo(Edition::class, 'edition_id');
    }

    public function division()
    {
        return $this->belongsTo(Division::class, 'division_id');
    }
}
