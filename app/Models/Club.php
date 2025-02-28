<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="Club",
 *     required={"name", "city"},
 *     @OA\Property(property="id_club", type="integer", format="int64"),
 *     @OA\Property(property="name", type="string", maxLength=255),
 *     @OA\Property(property="city", type="string", maxLength=255),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(
 *         property="locations",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/Location")
 *     )
 * )
 */
class Club extends Model
{
    use HasFactory;

    protected $table = 'clubs';
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

    public function prizes()
    {
        return $this->hasMany(PrizeFromClub::class, 'club_id');
    }
}
