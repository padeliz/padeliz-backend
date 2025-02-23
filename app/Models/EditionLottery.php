<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EditionLottery extends Model
{
    use HasFactory;

    protected $table = 'pm_edition_lotteries';
    protected $primaryKey = 'id_lottery';
    public $timestamps = false;

    protected $fillable = [
        'edition_id',
        'orderliness',
        'division_id',
        'prize_id',
        'total_draws_nr',
    ];

    public function edition()
    {
        return $this->belongsTo(Edition::class, 'edition_id');
    }

    public function division()
    {
        return $this->belongsTo(Division::class, 'division_id');
    }

    public function prize()
    {
        return $this->belongsTo(Prize::class, 'prize_id');
    }

    public function luckyOnes()
    {
        return $this->hasMany(EditionLuckyOne::class, 'lottery_id');
    }
}
