<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="Location",
 *     required={"name", "club_id"},
 *     @OA\Property(property="id_location", type="integer", format="int64"),
 *     @OA\Property(property="club_id", type="integer", format="int64"),
 *     @OA\Property(property="name", type="string", maxLength=255),
 *     @OA\Property(property="address", type="string", maxLength=255, nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(
 *         property="club",
 *         ref="#/components/schemas/Club"
 *     )
 * )
 */
class Location extends Model
{
    use HasFactory;

    protected $table = 'locations';
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
