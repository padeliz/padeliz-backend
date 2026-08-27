<?php

declare(strict_types=1);

namespace App\Models\Challenge;

use App\Enums\Challenge\ChallengeSessionStatus;
use App\Enums\Challenge\ChallengeSessionType;
use App\Traits\HasResourceMetadata;
use Database\Factories\ChallengeSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="ChallengeSession",
 *     required={"type", "status", "title", "bottom_text", "started_at"},
 *     @OA\Property(property="id_challenge_session", type="integer", format="int64", readOnly=true),
 *     @OA\Property(property="type", type="string", enum={"king_of_the_court", "smash_contest"}),
 *     @OA\Property(property="status", type="string", enum={"active", "completed"}, readOnly=true),
 *     @OA\Property(property="title", type="string"),
 *     @OA\Property(property="bottom_text", type="string"),
 *     @OA\Property(property="started_at", type="integer", format="timestamp", readOnly=true),
 *     @OA\Property(property="completed_at", type="integer", format="timestamp", nullable=true, readOnly=true),
 *     @OA\Property(property="state_sequence", type="integer", readOnly=true),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true)
 * )
 */
class ChallengeSession extends Model
{
    use HasFactory;
    use HasResourceMetadata;

    protected $table = 'challenge_sessions';

    protected $primaryKey = 'id_challenge_session';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'type',
        'status',
        'title',
        'bottom_text',
        'started_at',
        'completed_at',
        'state_json',
        'state_sequence',
        'inserted_at',
        'updated_at',
    ];

    protected $casts = [
        'type' => ChallengeSessionType::class,
        'status' => ChallengeSessionStatus::class,
        'started_at' => 'integer',
        'completed_at' => 'integer',
        'state_json' => 'array',
        'state_sequence' => 'integer',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public static array $searchableFields = [
        'title',
    ];

    public static array $filterableFields = [
        'type',
        'status',
    ];

    public static array $sortableFields = [
        'id_challenge_session',
        'title',
        'type',
        'status',
        'started_at',
        'completed_at',
        'inserted_at',
    ];

    public static ?string $displayField = 'title';

    public static array $listFields = [
        'title',
        'type',
        'status',
        'started_at',
        'completed_at',
    ];

    public function isActive(): bool
    {
        return $this->status === ChallengeSessionStatus::Active;
    }

    public function isCompleted(): bool
    {
        return $this->status === ChallengeSessionStatus::Completed;
    }

    public function isKingOfTheCourt(): bool
    {
        return $this->type === ChallengeSessionType::KingOfTheCourt;
    }

    public function isSmashContest(): bool
    {
        return $this->type === ChallengeSessionType::SmashContest;
    }

    protected static function newFactory(): ChallengeSessionFactory
    {
        return ChallengeSessionFactory::new();
    }
}
