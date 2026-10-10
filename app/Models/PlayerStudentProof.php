<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlayerLevel\AssessmentReviewStatus;
use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @OA\Schema(
 *     schema="PlayerStudentProof",
 *     required={"player_id", "student_until", "review_status"},
 *     @OA\Property(property="id_player_student_proof", type="integer", format="int64", readOnly=true, title="ID"),
 *     @OA\Property(property="player_id", type="integer", format="int64", title="Player"),
 *     @OA\Property(property="student_until", type="string", format="date", title="Student Until"),
 *     @OA\Property(property="sort_order", type="integer", title="Sort Order"),
 *     @OA\Property(property="ready_for_review", type="boolean", title="Ready For Review"),
 *     @OA\Property(property="reviewed_at", type="integer", format="timestamp", nullable=true, title="Reviewed At"),
 *     @OA\Property(property="review_status", type="string", enum={"pending", "approved", "rejected", "discarded"}, title="Review Status"),
 *     @OA\Property(property="review_message", type="string", nullable=true, title="Review Message"),
 *     @OA\Property(property="submitted_at", type="integer", format="timestamp", nullable=true, title="Submitted At"),
 *     @OA\Property(property="files", type="array", @OA\Items(ref="#/components/schemas/PlayerStudentProofFile")),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true, title="Created At"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true, title="Updated At")
 * )
 */
class PlayerStudentProof extends Model
{
    use HasFactory;
    use HasResourceMetadata;

    /** Maximum number of files a single proof may hold. */
    public const MAX_FILES = 3;

    /** Maximum allowed file size in kilobytes (Laravel max validation rule). */
    public const MAX_FILE_KILOBYTES = 30720;

    public static array $searchableFields = [
        'review_message',
    ];

    public static array $filterableFields = [
        'player_id',
        'review_status',
        'ready_for_review',
    ];

    public static array $sortableFields = [
        'id_player_student_proof',
        'player_id',
        'student_until',
        'review_status',
        'ready_for_review',
        'reviewed_at',
        'submitted_at',
        'inserted_at',
        'updated_at',
    ];

    public static ?string $displayField = null;

    protected $table = 'player_student_proofs';

    protected $primaryKey = 'id_player_student_proof';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    /**
     * `pending_player_id` is a generated column enforcing one pending row per player; never written.
     */
    protected $fillable = [
        'player_id',
        'student_until',
        'sort_order',
        'ready_for_review',
        'reviewed_at',
        'review_status',
        'review_message',
        'submitted_at',
    ];

    protected $casts = [
        'player_id' => 'integer',
        'sort_order' => 'integer',
        'ready_for_review' => 'boolean',
        'reviewed_at' => 'integer',
        'review_status' => AssessmentReviewStatus::class,
        'submitted_at' => 'integer',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    protected static function booted(): void
    {
        static::deleting(function (PlayerStudentProof $proof): void {
            // The FK cascade removes child rows without model events, so delete each file here.
            $proof->files()->get()->each(static fn (PlayerStudentProofFile $file) => $file->delete());
        });
    }

    public function files(): HasMany
    {
        return $this->hasMany(PlayerStudentProofFile::class, 'student_proof_id', 'id_player_student_proof')
            ->orderBy('sort_order')
            ->orderBy('id_player_student_proof_file');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id', 'id_player');
    }

    /**
     * Approved proof whose validity has not lapsed; drives the student_proof_active benefit condition.
     */
    public function scopeActive(Builder $query, ?int $now = null): Builder
    {
        $today = Carbon::createFromTimestamp($now ?? time(), (string) config('app.timezone', 'UTC'))
            ->format('Y-m-d');

        return $query
            ->where('review_status', AssessmentReviewStatus::Approved->value)
            ->whereDate('student_until', '>=', $today);
    }

    /**
     * True when the player currently holds an approved, unexpired student proof.
     */
    public static function hasActiveFor(Player|int $player, ?int $now = null): bool
    {
        $playerId = $player instanceof Player ? (int) $player->id_player : $player;

        return static::query()
            ->where('player_id', $playerId)
            ->active($now)
            ->exists();
    }
}
