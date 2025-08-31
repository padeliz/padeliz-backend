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
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true)
 * )
 */

class Division extends Model
{
    use HasFactory;

    protected $table = 'divisions';
    protected $primaryKey = 'id_division';
    public $timestamps = true;

    protected $dateFormat = 'U';

    const CREATED_AT = 'inserted_at';
    const UPDATED_AT = 'updated_at';

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
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
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
