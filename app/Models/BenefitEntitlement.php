<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Benefit\BenefitEntitlementSourceType;
use App\Enums\Benefit\BenefitEntitlementStatus;
use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @OA\Schema(
 *     schema="BenefitEntitlement",
 *     required={"benefit_definition_id", "player_id", "source_type", "status"},
 *     @OA\Property(property="id_entitlement", type="integer", format="int64", readOnly=true, title="ID"),
 *     @OA\Property(property="benefit_definition_id", type="integer", format="int64", title="Benefit Definition"),
 *     @OA\Property(property="player_id", type="integer", format="int64", title="Player"),
 *     @OA\Property(property="remaining_uses", type="integer", nullable=true, title="Remaining Uses"),
 *     @OA\Property(property="valid_from", type="integer", format="timestamp", nullable=true, title="Valid From"),
 *     @OA\Property(property="valid_until", type="integer", format="timestamp", nullable=true, title="Valid Until"),
 *     @OA\Property(property="source_type", type="string", enum={"manager_grant", "referral_reward", "system"}, title="Source Type"),
 *     @OA\Property(property="source_id", type="integer", format="int64", nullable=true, title="Source ID"),
 *     @OA\Property(property="status", type="string", enum={"active", "consumed", "expired", "revoked"}, title="Status"),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true)
 * )
 */
class BenefitEntitlement extends Model
{
    use HasFactory;
    use HasResourceMetadata;

    public static array $searchableFields = [];

    public static array $filterableFields = [
        'benefit_definition_id',
        'player_id',
        'source_type',
        'status',
    ];

    public static array $sortableFields = [
        'id_entitlement',
        'benefit_definition_id',
        'player_id',
        'remaining_uses',
        'valid_from',
        'valid_until',
        'source_type',
        'status',
        'inserted_at',
        'updated_at',
    ];

    public static ?string $displayField = null;

    protected $table = 'benefit_entitlements';

    protected $primaryKey = 'id_entitlement';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'benefit_definition_id',
        'player_id',
        'remaining_uses',
        'valid_from',
        'valid_until',
        'source_type',
        'source_id',
        'status',
    ];

    protected $casts = [
        'benefit_definition_id' => 'integer',
        'player_id' => 'integer',
        'remaining_uses' => 'integer',
        'valid_from' => 'integer',
        'valid_until' => 'integer',
        'source_type' => BenefitEntitlementSourceType::class,
        'source_id' => 'integer',
        'status' => BenefitEntitlementStatus::class,
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function benefitDefinition(): BelongsTo
    {
        return $this->belongsTo(BenefitDefinition::class, 'benefit_definition_id', 'id_benefit');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id', 'id_player');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(BenefitRedemption::class, 'entitlement_id', 'id_entitlement');
    }
}
