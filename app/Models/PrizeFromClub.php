<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrizeFromClub extends Model
{
    use HasFactory;

    protected $table = 'pm_prizes_from_clubs';
    protected $primaryKey = 'id_club_prize';
    public $timestamps = false;

    protected $fillable = [
        'club_id',
        'prize_id',
        'image',
        'box_1_text',
        'box_1_bg_color',
        'box_1_text_color',
        'box_2_text',
        'box_2_bg_color',
        'box_2_text_color',
    ];

    public function club()
    {
        return $this->belongsTo(Club::class, 'club_id');
    }

    public function prize()
    {
        return $this->belongsTo(Prize::class, 'prize_id');
    }
}
