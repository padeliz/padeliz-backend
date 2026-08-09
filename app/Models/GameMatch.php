<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent model for the `matches` table.
 * Named GameMatch because `Match` is a reserved keyword in PHP 8+.
 *
 * @OA\Schema(
 *     schema="GameMatch",
 *     required={"edition_division_id", "edition_division_court_id", "match_kind", "sequence_no"},
 *     @OA\Property(property="id_match", type="integer", format="int64", readOnly=true),
 *     @OA\Property(property="edition_division_id", type="integer", format="int64"),
 *     @OA\Property(property="edition_division_court_id", type="integer", format="int64"),
 *     @OA\Property(property="match_kind", type="string", enum={"REGULAR", "FINAL", "DEMONSTRATIVE"}),
 *     @OA\Property(property="sequence_no", type="integer"),
 *     @OA\Property(property="scheduled_start", type="string", format="time", nullable=true),
 *     @OA\Property(property="scheduled_end", type="string", format="time", nullable=true),
 *     @OA\Property(property="points_target", type="integer", nullable=true),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true)
 * )
 */
class GameMatch extends Model
{
    use HasFactory;

    protected $table = 'matches';

    protected $primaryKey = 'id_match';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';
    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'edition_division_id',
        'edition_division_court_id',
        'match_kind',
        'sequence_no',
        'scheduled_start',
        'scheduled_end',
        'points_target',
    ];

    protected $casts = [
        'sequence_no' => 'integer',
        'points_target' => 'integer',
        'scheduled_start' => 'datetime:H:i:s',
        'scheduled_end' => 'datetime:H:i:s',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function editionDivision(): BelongsTo
    {
        return $this->belongsTo(EditionDivision::class, 'edition_division_id', 'id_edition_division');
    }

    public function editionDivisionCourt(): BelongsTo
    {
        return $this->belongsTo(EditionDivisionCourt::class, 'edition_division_court_id', 'id_edition_division_court');
    }

    public function sides(): HasMany
    {
        return $this->hasMany(MatchSide::class, 'match_id', 'id_match');
    }
}
