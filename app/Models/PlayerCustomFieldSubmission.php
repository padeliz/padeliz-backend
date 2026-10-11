<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @OA\Schema(
 *     schema="PlayerCustomFieldSubmission",
 *     required={"player_user_id", "submitted_via"},
 *     @OA\Property(property="id_player_custom_field_submission", type="integer", format="int64", readOnly=true, title="ID"),
 *     @OA\Property(property="player_user_id", type="integer", format="int64", title="Player"),
 *     @OA\Property(property="submitted_via", type="string", enum={"app", "manager", "csv"}, title="Submitted Via"),
 *     @OA\Property(property="submitted_by_player_user_id", type="integer", format="int64", nullable=true, title="Submitted By Player"),
 *     @OA\Property(property="submitted_by_manager_user_id", type="integer", format="int64", nullable=true, title="Submitted By Manager"),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true, title="Created At"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true, title="Updated At")
 * )
 */
class PlayerCustomFieldSubmission extends Model
{
    use HasFactory;
    use HasResourceMetadata;

    public const SUBMITTED_VIA_APP = 'app';

    public const SUBMITTED_VIA_MANAGER = 'manager';

    public const SUBMITTED_VIA_CSV = 'csv';

    public static array $searchableFields = [];

    public static array $filterableFields = [
        'player_user_id',
        'submitted_via',
    ];

    public static array $sortableFields = [
        'id_player_custom_field_submission',
        'player_user_id',
        'submitted_via',
        'inserted_at',
        'updated_at',
    ];

    public static ?string $displayField = null;

    protected $table = 'player_custom_field_submissions';

    protected $primaryKey = 'id_player_custom_field_submission';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'player_user_id',
        'submitted_via',
        'submitted_by_player_user_id',
        'submitted_by_manager_user_id',
    ];

    protected $casts = [
        'player_user_id' => 'integer',
        'submitted_by_player_user_id' => 'integer',
        'submitted_by_manager_user_id' => 'integer',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_user_id', 'id_user');
    }

    public function submittedByPlayer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_player_user_id', 'id_user');
    }

    public function submittedByManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_manager_user_id', 'id_user');
    }

    public function values(): HasMany
    {
        return $this->hasMany(
            PlayerCustomFieldValue::class,
            'submission_id',
            'id_player_custom_field_submission'
        );
    }

    public function assessment(): HasOne
    {
        return $this->hasOne(
            PlayerLevelAssessment::class,
            'player_custom_field_submission_id',
            'id_player_custom_field_submission'
        );
    }
}
