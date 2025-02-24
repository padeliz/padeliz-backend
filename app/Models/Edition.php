<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="Edition",
 *     required={"name", "date", "location_id"},
 *     @OA\Property(property="id_edition", type="integer", format="int64"),
 *     @OA\Property(property="location_id", type="integer", format="int64"),
 *     @OA\Property(property="name", type="string", maxLength=255),
 *     @OA\Property(property="date", type="string", format="date"),
 *     @OA\Property(property="entry_fee", type="number"),
 *     @OA\Property(property="students_entry_fee", type="number"),
 *     @OA\Property(property="arriving_time", type="string", format="time"),
 *     @OA\Property(property="closing_time", type="string", format="time"),
 *     @OA\Property(
 *         property="location",
 *         ref="#/components/schemas/Location"
 *     ),
 *     @OA\Property(
 *         property="divisions",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/EditionDivision")
 *     ),
 *     @OA\Property(
 *         property="participations",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/EditionParticipation")
 *     )
 * )
 */

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
