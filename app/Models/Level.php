<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @OA\Schema(
 *     schema="Level",
 *     required={"name", "level_min", "level_max"},
 *     @OA\Property(property="id_level", type="integer", format="int64", readOnly=true, title="ID"),
 *     @OA\Property(property="name", type="string", maxLength=64, title="Name", example="Division 3 Mid"),
 *     @OA\Property(property="level_min", type="string", title="Level Min", description="Weakest value of the band", example="3.66"),
 *     @OA\Property(property="level_max", type="string", title="Level Max", description="Strongest value of the band", example="3.34"),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true, title="Created At"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true, title="Updated At")
 * )
 */
class Level extends Model
{
    use HasFactory;
    use HasResourceMetadata;

    public static array $searchableFields = [
        'name',
    ];

    public static array $filterableFields = [];

    public static array $sortableFields = [
        'id_level',
        'name',
        'level_min',
        'level_max',
        'inserted_at',
        'updated_at',
    ];

    public static ?string $displayField = 'name';

    protected $table = 'levels';

    protected $primaryKey = 'id_level';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'name',
        'level_min',
        'level_max',
    ];

    protected $casts = [
        'level_min' => 'decimal:2',
        'level_max' => 'decimal:2',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function divisions(): BelongsToMany
    {
        return $this->belongsToMany(
            Division::class,
            'division_levels',
            'level_id',
            'division_id',
            'id_level',
            'id_division'
        )->withPivot(['id_division_level', 'inserted_at', 'updated_at']);
    }

    /**
     * Bands run from weakest (level_min) down to strongest (level_max), e.g. 3.66 -> 3.34.
     */
    public function contains(float|string $level): bool
    {
        $value = (float) $level;

        return $value <= (float) $this->level_min && $value >= (float) $this->level_max;
    }
}
