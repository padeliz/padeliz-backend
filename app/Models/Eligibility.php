<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @OA\Schema(
 *     schema="Eligibility",
 *     required={"player_id", "submitted_at", "review_status"},
 *     @OA\Property(
 *         property="id_eligibility",
 *         type="integer",
 *         format="int64",
 *         readOnly=true,
 *         title="ID",
 *         description="Unique Identifier"
 *     ),
 *     @OA\Property(property="edition_ids", type="array", @OA\Items(type="integer"), nullable=true),
 *     @OA\Property(property="media_source", type="array", @OA\Items(type="integer"), nullable=true),
 *     @OA\Property(property="friends_source", type="array", @OA\Items(type="string"), nullable=true),
 *     @OA\Property(property="racket_experience", type="array", @OA\Items(type="string"), nullable=true),
 *     @OA\Property(
 *         property="player_id",
 *         type="integer",
 *         format="int64",
 *         minimum=1,
 *         title="Player",
 *         description="Player of the eligibility"
 *     ),
 *     @OA\Property(
 *         property="phone",
 *         type="string",
 *         maxLength=100,
 *         nullable=true,
 *         title="Phone",
 *         description="Phone number",
 *         example="+40712345678"
 *     ),
 *     @OA\Property(property="first_name", type="string", maxLength=50, nullable=true, title="First Name", description="Player's first name"),
 *     @OA\Property(property="middle_name", type="string", maxLength=50, nullable=true, title="Middle Name", description="Player's middle name"),
 *     @OA\Property(property="last_name", type="string", maxLength=50, nullable=true, title="Last Name", description="Player's last name"),
 *     @OA\Property(property="year_of_birth", type="integer", format="year", nullable=true),
 *     @OA\Property(property="birth_month", type="integer", nullable=true),
 *     @OA\Property(property="birth_day", type="integer", nullable=true),
 *     @OA\Property(property="being_student", type="boolean", nullable=true, title="Student", description="Whether the player is a student"),
 *     @OA\Property(property="playtomic_id", type="string", maxLength=10, nullable=true, title="Playtomic ID", description="Playtomic platform identifier"),
 *     @OA\Property(property="rankedin_id", type="string", maxLength=15, nullable=true, title="Rankedin ID", description="Rankedin platform identifier"),
 *     @OA\Property(property="instagram_username", type="string", maxLength=50, nullable=true, title="Instagram Username", description="Instagram username"),
 *     @OA\Property(property="facebook_username", type="string", maxLength=50, nullable=true, title="Facebook Username", description="Facebook username"),
 *     @OA\Property(
 *         property="reviewed_at",
 *         type="integer",
 *         format="timestamp",
 *         nullable=true,
 *         minimum=0,
 *         title="Reviewed At",
 *         description="Unix timestamp when reviewed",
 *         example=1704067200
 *     ),
 *     @OA\Property(property="ready_for_review", type="boolean"),
 *     @OA\Property(property="review_status", type="string", enum={"pending", "approved", "rejected", "discarded"}),
 *     @OA\Property(property="review_message", type="string", nullable=true, title="Review Message", description="Review message"),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true, title="Created At", description="Timestamp when the eligibility was created"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true, title="Updated At", description="Timestamp when the eligibility was last updated"),
 *     @OA\Property(property="resubmitted_at", type="integer", format="timestamp", nullable=true),
 *     @OA\Property(
 *         property="player",
 *         ref="#/components/schemas/Player",
 *         title="Player",
 *         description="Related player object",
 *         nullable=true
 *     ),
 *     @OA\Property(property="submitted_at", type="integer", format="timestamp"),
 *     @OA\Property(
 *         property="student_proofs",
 *         type="array",
 *         readOnly=true,
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="id", type="integer"),
 *             @OA\Property(property="url", type="string")
 *         ),
 *         description="Student proof files (id and public URL)",
 *         x={
 *             "padeliz-upload": {
 *                 "mode": "multi",
 *                 "max_files": 3,
 *                 "max_size_kb": 30720,
 *                 "form_field": "file",
 *                 "accept": {"0":"image/jpeg","1":"image/png","2":"image/gif","3":"image/webp","4":"application/pdf"},
 *                 "upload": {"method":"POST","path":"/api/v1/manager/eligibilities/{id}/student-proofs"},
 *                 "delete": {"method":"DELETE","path":"/api/v1/manager/eligibilities/{id}/student-proofs/{childId}"}
 *             }
 *         }
 *     ),
 *     @OA\Property(
 *         property="portrait_photos",
 *         type="array",
 *         readOnly=true,
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="id", type="integer"),
 *             @OA\Property(property="url", type="string")
 *         ),
 *         description="Portrait photos for eligibility (id and public URL)",
 *         x={
 *             "padeliz-upload": {
 *                 "mode": "multi",
 *                 "max_files": 3,
 *                 "max_size_kb": 5120,
 *                 "form_field": "file",
 *                 "accept": {"0":"image/jpeg","1":"image/png","2":"image/gif","3":"image/webp"},
 *                 "upload": {"method":"POST","path":"/api/v1/manager/eligibilities/{id}/portrait-photos"},
 *                 "delete": {"method":"DELETE","path":"/api/v1/manager/eligibilities/{id}/portrait-photos/{childId}"}
 *             }
 *         }
 *     ),
 * )
 */
class Eligibility extends Model
{
    use HasFactory;
    use HasResourceMetadata;

    /**
     * Fields that can be searched
     */
    public static array $searchableFields = [
        'first_name',
        'middle_name',
        'last_name',
        'phone',
        'playtomic_id',
        'rankedin_id',
        'instagram_username',
        'facebook_username',
        'review_message',
    ];

    /**
     * Fields that can be filtered
     */
    public static array $filterableFields = [
        'player_id',
        'year_of_birth',
        'being_student',
        'review_status',
        'ready_for_review',
    ];

    /**
     * Fields that can be sorted
     */
    public static array $sortableFields = [
        'id_eligibility',
        'player_id',
        'first_name',
        'last_name',
        'year_of_birth',
        'being_student',
        'review_status',
        'reviewed_at',
        'submitted_at',
        'inserted_at',
        'updated_at',
        'resubmitted_at',
    ];

    /**
     * Field that will be used to display the player eligibility in the UI
     */
    public static ?string $displayField = null;

    protected $table = 'eligibilities';

    protected $primaryKey = 'id_eligibility';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';
    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'player_id',
        'edition_ids',
        'media_source',
        'friends_source',
        'racket_experience',
        'phone',
        'first_name',
        'middle_name',
        'last_name',
        'year_of_birth',
        'birth_month',
        'birth_day',
        'being_student',
        'playtomic_id',
        'rankedin_id',
        'instagram_username',
        'facebook_username',
        'submitted_at',
        'reviewed_at',
        'ready_for_review',
        'review_status',
        'review_message',
        'resubmitted_at',
    ];

    protected $casts = [
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
        'reviewed_at' => 'integer',
        'submitted_at' => 'integer',
        'being_student' => 'boolean',
        'year_of_birth' => 'integer',
        'birth_month' => 'integer',
        'birth_day' => 'integer',
        'resubmitted_at' => 'integer',
        'ready_for_review' => 'boolean',
        'edition_ids' => 'array',
        'media_source' => 'array',
        'friends_source' => 'array',
        'racket_experience' => 'array',
    ];

    public function player()
    {
        return $this->belongsTo(Player::class, 'player_id', 'id_player');
    }

    public function studentProofs(): HasMany
    {
        return $this->hasMany(EligibilityStudentProof::class, 'eligibility_id', 'id_eligibility')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function portraitPhotos(): HasMany
    {
        return $this->hasMany(EligibilityPortraitPhoto::class, 'eligibility_id', 'id_eligibility')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function recomputeReadyForReview(): void
    {
        $hasSource = $this->media_source !== null || $this->friends_source !== null;

        $this->ready_for_review = $hasSource
            && $this->first_name !== null
            && $this->last_name !== null
            && $this->phone !== null
            && $this->year_of_birth !== null
            && ($this->submitted_at !== null || $this->resubmitted_at !== null);
    }
}
