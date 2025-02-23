<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prize extends Model
{
    use HasFactory;

    protected $table = 'pm_prizes';
    protected $primaryKey = 'id_prize';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'image',
        'box_1_text',
        'box_1_bg_color',
        'box_1_text_color',
        'box_2_text',
        'box_2_bg_color',
        'box_2_text_color',
    ];

    public function prizesFromClubs()
    {
        return $this->hasMany(PrizeFromClub::class, 'prize_id');
    }

    public function lotteries()
    {
        return $this->hasMany(EditionLottery::class, 'prize_id');
    }
}
