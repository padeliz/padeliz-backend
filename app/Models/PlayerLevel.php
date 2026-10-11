<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @OA\Schema(
 *     schema="PlayerLevel",
 *     required={"player_user_id", "level"},
 *     @OA\Property(property="id_player_level", type="integer", format="int64", readOnly=true, title="ID"),
 *     @OA\Property(property="player_user_id", type="integer", format="int64", title="Player"),
 *     @OA\Property(property="level", type="string", title="Level", description="Two-decimal level inside a seeded band", example="3.50"),
 *     @OA\Property(property="player_level_assessment_id", type="integer", format="int64", nullable=true, title="Assessment"),
 *     @OA\Property(property="event_participation_id", type="integer", format="int64", nullable=true, title="Participation"),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true, title="Created At"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true, title="Updated At")
 * )
 */
class PlayerLevel extends Model
{
    use HasFactory;
    use HasResourceMetadata;

    public static array $searchableFields = [];

    public static array $filterableFields = [
        'player_user_id',
        'level',
    ];

    public static array $sortableFields = [
        'id_player_level',
        'player_user_id',
        'level',
        'inserted_at',
        'updated_at',
    ];

    public static ?string $displayField = 'level';

    protected $table = 'player_levels';

    protected $primaryKey = 'id_player_level';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'player_user_id',
        'level',
        'player_level_assessment_id',
        'event_participation_id',
    ];

    protected $casts = [
        'player_user_id' => 'integer',
        'level' => 'decimal:2',
        'player_level_assessment_id' => 'integer',
        'event_participation_id' => 'integer',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_user_id', 'id_user');
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(
            PlayerLevelAssessment::class,
            'player_level_assessment_id',
            'id_player_level_assessment'
        );
    }

    public function participation(): BelongsTo
    {
        return $this->belongsTo(
            EventParticipation::class,
            'event_participation_id',
            'id_participation'
        );
    }

    /**
     * Exactly one of assessment / participation sources the level.
     */
    public function hasExactlyOneSource(): bool
    {
        return ($this->player_level_assessment_id === null) !== ($this->event_participation_id === null);
    }
}
