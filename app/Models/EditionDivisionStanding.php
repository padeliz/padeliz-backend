<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @OA\Schema(
 *     schema="EditionDivisionStanding",
 *     required={"edition_division_id", "participation_id"},
 *     @OA\Property(property="id_standing", type="integer", format="int64", readOnly=true),
 *     @OA\Property(property="edition_division_id", type="integer", format="int64"),
 *     @OA\Property(property="participation_id", type="integer", format="int64"),
 *     @OA\Property(property="points_total", type="integer", nullable=true),
 *     @OA\Property(property="wins", type="number", format="float", nullable=true),
 *     @OA\Property(property="rank", type="integer", nullable=true),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true)
 * )
 */
class EditionDivisionStanding extends Model
{
    use HasFactory;

    protected $table = 'edition_division_standings';

    protected $primaryKey = 'id_standing';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'edition_division_id',
        'participation_id',
        'points_total',
        'wins',
        'rank',
    ];

    protected $casts = [
        'points_total' => 'integer',
        'wins' => 'float',
        'rank' => 'integer',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function editionDivision(): BelongsTo
    {
        return $this->belongsTo(EditionDivision::class, 'edition_division_id', 'id_edition_division');
    }

    public function participation(): BelongsTo
    {
        return $this->belongsTo(EditionParticipation::class, 'participation_id', 'id_participation');
    }
}
