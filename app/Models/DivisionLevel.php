<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @OA\Schema(
 *     schema="DivisionLevel",
 *     required={"division_id", "level_id"},
 *     @OA\Property(property="id_division_level", type="integer", format="int64", readOnly=true, title="ID"),
 *     @OA\Property(property="division_id", type="integer", format="int64", title="Division"),
 *     @OA\Property(property="level_id", type="integer", format="int64", title="Level"),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true, title="Created At"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true, title="Updated At")
 * )
 */
/**
 * Pivot so `Division::levels()->sync()` stamps int unix timestamps (`dateFormat = 'U'`)
 * instead of datetime strings the int columns reject.
 */
class DivisionLevel extends Pivot
{
    use HasFactory;

    protected $table = 'division_levels';

    protected $primaryKey = 'id_division_level';

    public $incrementing = true;

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'division_id',
        'level_id',
    ];

    protected $casts = [
        'division_id' => 'integer',
        'level_id' => 'integer',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'division_id', 'id_division');
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class, 'level_id', 'id_level');
    }
}
