<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="Division",
 *     required={"name", "color", "is_students_only", "its_first_edition_id"},
 *     @OA\Property(
 *         property="id_division",
 *         type="integer",
 *         format="int64",
 *         readOnly=true,
 *         description="Primary key - Division ID"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         maxLength=25,
 *         description="Division name",
 *         example="Advanced"
 *     ),
 *     @OA\Property(
 *         property="color",
 *         type="string",
 *         maxLength=7,
 *         pattern="^#[0-9A-F]{6}$",
 *         description="Hex color code (e.g., #FF0000)",
 *         example="#FF0000"
 *     ),
 *     @OA\Property(property="is_students_only", type="boolean"),
 *     @OA\Property(
 *         property="its_first_edition_id",
 *         type="integer",
 *         minimum=1,
 *         description="ID of the first edition for this division",
 *         example=1
 *     ),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true),
 *     @OA\Property(
 *         property="editionDivisions",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/EditionDivision"),
 *         description="Array of related edition divisions",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="participations",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/EditionParticipation"),
 *         description="Array of related participations",
 *         nullable=true
 *     )
 * )
 */
class Division extends Model
{
    use HasFactory;
    use HasResourceMetadata;

    /**
     * Fields that can be searched
     */
    public static array $searchableFields = [
        'name',
    ];

    /**
     * Fields that can be filtered
     */
    public static array $filterableFields = [
        'is_students_only',
        'its_first_edition_id',
    ];

    /**
     * Fields that can be sorted
     */
    public static array $sortableFields = [
        'name',
        'color',
        'is_students_only',
        'its_first_edition_id',
        'inserted_at',
        'updated_at',
    ];

    protected $table = 'divisions';

    protected $primaryKey = 'id_division';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';
    public const UPDATED_AT = 'updated_at';

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
