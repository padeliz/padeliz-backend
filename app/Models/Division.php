<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="Division",
 *     required={"name", "color"},
 *     @OA\Property(property="id_division", type="integer", format="int64"),
 *     @OA\Property(property="name", type="string", maxLength=255),
 *     @OA\Property(property="color", type="string", maxLength=7),
 *     @OA\Property(property="is_students_only", type="boolean"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */

class Division extends Model
{
    use HasFactory;

    protected $table = 'divisions';
    protected $primaryKey = 'id_division';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'color',
        'is_students_only',
        'its_first_edition_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'is_students_only' => 'boolean',
    ];

    public function editionDivisions()
    {
        return $this->hasMany(EditionDivision::class, 'division_id');
    }

    public function participations()
    {
        return $this->hasMany(EditionParticipation::class, 'division_id');
    }
}
