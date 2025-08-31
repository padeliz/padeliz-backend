<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="EditionDivision",
 *     required={"edition_id", "division_id"},
 *     @OA\Property(property="id_edition_division", type="integer", format="int64"),
 *     @OA\Property(property="edition_id", type="integer", format="int64"),
 *     @OA\Property(property="division_id", type="integer", format="int64"),
 *     @OA\Property(property="players_limit", type="integer"),
 *     @OA\Property(property="court", type="string"),
 *     @OA\Property(property="playing_start_time", type="string", format="time"),
 *     @OA\Property(property="playing_end_time", type="string", format="time"),
 *     @OA\Property(property="player_opponents_nr", type="integer"),
 *     @OA\Property(property="player_matches_repeat", type="integer"),
 *     @OA\Property(property="include_scores", type="boolean"),
 *     @OA\Property(property="has_demonstrative_match", type="boolean"),
 *     @OA\Property(property="fixed_teams", type="boolean"),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true),
 *     @OA\Property(
 *         property="division",
 *         ref="#/components/schemas/Division"
 *     )
 * )
 */
class EditionDivision extends Model
{
    use HasFactory;

    protected $table = 'edition_divisions';

    protected $primaryKey = 'id_edition_division';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';
    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'edition_id',
        'division_id',
        'players_limit',
        'court',
        'playing_start_time',
        'playing_end_time',
        'player_opponents_nr',
        'player_matches_repeat',
        'include_scores',
        'has_demonstrative_match',
        'fixed_teams',
    ];

    protected $casts = [
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
        'playing_start_time' => 'datetime:H:i:s',
        'playing_end_time' => 'datetime:H:i:s',
    ];

    public function edition()
    {
        return $this->belongsTo(Edition::class, 'edition_id');
    }

    public function division()
    {
        return $this->belongsTo(Division::class, 'division_id');
    }
}
