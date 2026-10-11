<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlayerCustomFieldValueReviewStatus;
use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @OA\Schema(
 *     schema="PlayerCustomFieldValue",
 *     required={"player_user_id", "player_custom_field_id", "submitted_via"},
 *     @OA\Property(property="id_player_custom_field_value", type="integer", format="int64", readOnly=true, title="ID"),
 *     @OA\Property(property="player_user_id", type="integer", format="int64", title="Player"),
 *     @OA\Property(property="player_custom_field_id", type="integer", format="int64", title="Field"),
 *     @OA\Property(property="submission_id", type="integer", format="int64", title="Submission"),
 *     @OA\Property(property="value_text", type="string", nullable=true, title="Text Value"),
 *     @OA\Property(property="value_integer", type="integer", nullable=true, title="Integer Value"),
 *     @OA\Property(property="value_boolean", type="boolean", nullable=true, title="Boolean Value"),
 *     @OA\Property(property="value_date", type="string", format="date", nullable=true, title="Date Value"),
 *     @OA\Property(property="value_json", type="object", nullable=true, title="JSON Value"),
 *     @OA\Property(property="value", description="Computed display value", nullable=true),
 *     @OA\Property(property="submitted_by_player_user_id", type="integer", format="int64", nullable=true, title="Submitted By Player"),
 *     @OA\Property(property="submitted_by_manager_user_id", type="integer", format="int64", nullable=true, title="Submitted By Manager"),
 *     @OA\Property(property="submitted_via", type="string", enum={"app", "manager", "csv"}, title="Submitted Via"),
 *     @OA\Property(property="created_via_import_id", type="integer", format="int64", nullable=true, title="Import"),
 *     @OA\Property(property="review_status", type="string", enum={"pending", "validated", "rejected", "discarded"}, title="Review Status"),
 *     @OA\Property(property="reviewed_at", type="integer", format="timestamp", nullable=true, title="Reviewed At"),
 *     @OA\Property(property="reviewed_by_manager_user_id", type="integer", format="int64", nullable=true, title="Reviewed By Manager"),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true, title="Created At"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true, title="Updated At"),
 *     @OA\Property(property="field", ref="#/components/schemas/PlayerCustomField", nullable=true),
 *     @OA\Property(property="submission", ref="#/components/schemas/PlayerCustomFieldSubmission", nullable=true),
 *     @OA\Property(
 *         property="selected_options",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/PlayerCustomFieldValueOption")
 *     )
 * )
 */
class PlayerCustomFieldValue extends Model
{
    use HasFactory;
    use HasResourceMetadata;

    public const SUBMITTED_VIA_APP = 'app';

    public const SUBMITTED_VIA_MANAGER = 'manager';

    public const SUBMITTED_VIA_CSV = 'csv';

    public static array $searchableFields = [];

    public static array $filterableFields = [
        'player_user_id',
        'player_custom_field_id',
        'submission_id',
        'submitted_via',
        'review_status',
    ];

    public static array $sortableFields = [
        'id_player_custom_field_value',
        'player_user_id',
        'player_custom_field_id',
        'submission_id',
        'inserted_at',
        'updated_at',
        'review_status',
        'reviewed_at',
    ];

    public static ?string $displayField = null;

    protected $table = 'player_custom_field_values';

    protected $primaryKey = 'id_player_custom_field_value';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'player_user_id',
        'player_custom_field_id',
        'submission_id',
        'value_text',
        'value_integer',
        'value_boolean',
        'value_date',
        'value_json',
        'submitted_by_player_user_id',
        'submitted_by_manager_user_id',
        'submitted_via',
        'created_via_import_id',
        'review_status',
        'reviewed_at',
        'reviewed_by_manager_user_id',
    ];

    protected $casts = [
        'player_user_id' => 'integer',
        'player_custom_field_id' => 'integer',
        'submission_id' => 'integer',
        'value_integer' => 'integer',
        'value_boolean' => 'boolean',
        'value_json' => 'array',
        'submitted_by_player_user_id' => 'integer',
        'submitted_by_manager_user_id' => 'integer',
        'created_via_import_id' => 'integer',
        'review_status' => PlayerCustomFieldValueReviewStatus::class,
        'reviewed_at' => 'integer',
        'reviewed_by_manager_user_id' => 'integer',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_user_id', 'id_user');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(PlayerCustomField::class, 'player_custom_field_id', 'id_player_custom_field');
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(
            PlayerCustomFieldSubmission::class,
            'submission_id',
            'id_player_custom_field_submission'
        );
    }

    public function submittedByPlayer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_player_user_id', 'id_user');
    }

    public function submittedByManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_manager_user_id', 'id_user');
    }

    public function createdViaImport(): BelongsTo
    {
        return $this->belongsTo(
            PlayerCustomFieldValueImport::class,
            'created_via_import_id',
            'id_player_custom_field_value_import'
        );
    }

    public function reviewedByManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_manager_user_id', 'id_user');
    }

    /**
     * @param  Builder<PlayerCustomFieldValue>  $query
     * @return Builder<PlayerCustomFieldValue>
     */
    public function scopeValidated(Builder $query): Builder
    {
        return $query->where('review_status', PlayerCustomFieldValueReviewStatus::Validated->value);
    }

    /**
     * Orders validated rows newest-first: the first one is the current value for a field.
     *
     * @param  Builder<PlayerCustomFieldValue>  $query
     * @return Builder<PlayerCustomFieldValue>
     */
    public function scopeLatestValidatedFirst(Builder $query): Builder
    {
        return $query->validated()
            ->orderByDesc('reviewed_at')
            ->orderByDesc('id_player_custom_field_value');
    }

    public function selectedOptions(): HasMany
    {
        return $this->hasMany(
            PlayerCustomFieldValueOption::class,
            'player_custom_field_value_id',
            'id_player_custom_field_value'
        );
    }

    public function isValidated(): bool
    {
        return $this->review_status === PlayerCustomFieldValueReviewStatus::Validated;
    }
}
