<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlayerLevel\AssessmentReviewStatus;
use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @OA\Schema(
 *     schema="PlayerLevelAssessment",
 *     required={"player_user_id", "player_custom_field_submission_id", "review_status"},
 *     @OA\Property(property="id_player_level_assessment", type="integer", format="int64", readOnly=true, title="ID"),
 *     @OA\Property(property="player_user_id", type="integer", format="int64", title="Player"),
 *     @OA\Property(property="player_custom_field_submission_id", type="integer", format="int64", title="Submission"),
 *     @OA\Property(property="submitted_at", type="integer", format="timestamp", nullable=true, title="Submitted At"),
 *     @OA\Property(property="resubmitted_at", type="integer", format="timestamp", nullable=true, title="Resubmitted At"),
 *     @OA\Property(property="ready_for_review", type="boolean", title="Ready For Review"),
 *     @OA\Property(property="reviewed_at", type="integer", format="timestamp", nullable=true, title="Reviewed At"),
 *     @OA\Property(property="review_status", type="string", enum={"pending", "approved", "rejected", "discarded"}, title="Review Status"),
 *     @OA\Property(property="review_message", type="string", nullable=true, title="Review Message"),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true, title="Created At"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true, title="Updated At")
 * )
 */
class PlayerLevelAssessment extends Model
{
    use HasFactory;
    use HasResourceMetadata;

    public static array $searchableFields = [
        'review_message',
    ];

    public static array $filterableFields = [
        'player_user_id',
        'review_status',
        'ready_for_review',
    ];

    public static array $sortableFields = [
        'id_player_level_assessment',
        'player_user_id',
        'review_status',
        'ready_for_review',
        'submitted_at',
        'resubmitted_at',
        'reviewed_at',
        'inserted_at',
        'updated_at',
    ];

    public static ?string $displayField = null;

    protected $table = 'player_level_assessments';

    protected $primaryKey = 'id_player_level_assessment';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    /**
     * `pending_player_user_id` is a generated column enforcing one pending row per player; never written.
     */
    protected $fillable = [
        'player_user_id',
        'player_custom_field_submission_id',
        'submitted_at',
        'resubmitted_at',
        'ready_for_review',
        'reviewed_at',
        'review_status',
        'review_message',
    ];

    protected $casts = [
        'player_user_id' => 'integer',
        'player_custom_field_submission_id' => 'integer',
        'submitted_at' => 'integer',
        'resubmitted_at' => 'integer',
        'ready_for_review' => 'boolean',
        'reviewed_at' => 'integer',
        'review_status' => AssessmentReviewStatus::class,
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    /**
     * There is no delete route. If an assessment model is ever deleted, its submission and the
     * submission's custom field values go with it. The submission row itself is removed once the
     * assessment row is gone, because the assessment holds a restricting FK to it.
     */
    protected static function booted(): void
    {
        static::deleting(static function (PlayerLevelAssessment $assessment): void {
            PlayerCustomFieldValue::query()
                ->where('submission_id', $assessment->player_custom_field_submission_id)
                ->delete();
        });

        static::deleted(static function (PlayerLevelAssessment $assessment): void {
            PlayerCustomFieldSubmission::query()
                ->whereKey($assessment->player_custom_field_submission_id)
                ->delete();
        });
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_user_id', 'id_user');
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(
            PlayerCustomFieldSubmission::class,
            'player_custom_field_submission_id',
            'id_player_custom_field_submission'
        );
    }

    public function playerLevel(): HasOne
    {
        return $this->hasOne(
            PlayerLevel::class,
            'player_level_assessment_id',
            'id_player_level_assessment'
        );
    }

    public function profileImages(): HasMany
    {
        return $this->hasMany(
            PlayerProfileImage::class,
            'uploaded_via_level_assessment_id',
            'id_player_level_assessment'
        );
    }

    public function isPending(): bool
    {
        return $this->review_status === AssessmentReviewStatus::Pending;
    }
}
