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
 *         title="ID",
 *         description="Unique Identifier"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         maxLength=25,
 *         title="Name",
 *         description="Name of the skill division",
 *         example="Advanced"
 *     ),
 *     @OA\Property(
 *         property="color",
 *         type="string",
 *         maxLength=7,
 *         pattern="^#[0-9A-F]{6}$",
 *         title="Color",
 *         description="Hex color code for the division",
 *         example="#FF0000"
 *     ),
 *     @OA\Property(
 *         property="is_students_only",
 *         type="boolean",
 *         title="Students Only",
 *         description="Whether this division is restricted to students only"
 *     ),
 *     @OA\Property(
 *         property="its_first_edition_id",
 *         type="integer",
 *         minimum=1,
 *         title="First Edition",
 *         description="First edition for this division",
 *         example=1
 *     ),
 *     @OA\Property(
 *         property="inserted_at",
 *         type="integer",
 *         format="timestamp",
 *         readOnly=true,
 *         title="Created At",
 *         description="Timestamp when the division was created"
 *     ),
 *     @OA\Property(
 *         property="updated_at",
 *         type="integer",
 *         format="timestamp",
 *         nullable=true,
 *         readOnly=true,
 *         title="Updated At",
 *         description="Timestamp when the division was last updated"
 *     ),
 *     @OA\Property(
 *         property="editionDivisions",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/EditionDivision"),
 *         title="Edition Divisions",
 *         description="Array of related edition divisions",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="participations",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/EditionParticipation"),
 *         title="Participations",
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
        'id_division',
        'name',
        'color',
        'is_students_only',
        'its_first_edition_id',
        'inserted_at',
        'updated_at',
    ];

    /**
     * Field that will be used to display the division in the UI
     */
    public static ?string $displayField = 'name';

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
