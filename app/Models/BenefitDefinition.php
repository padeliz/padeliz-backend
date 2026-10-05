<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Benefit\BenefitAmountType;
use App\Enums\Benefit\BenefitExclusivity;
use App\Enums\Benefit\BenefitScopeType;
use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @OA\Schema(
 *     schema="BenefitDefinition",
 *     required={"slug", "name", "amount_type", "amount_value", "scope_type"},
 *     @OA\Property(property="id_benefit", type="integer", format="int64", readOnly=true, title="ID"),
 *     @OA\Property(property="slug", type="string", maxLength=64, title="Slug"),
 *     @OA\Property(property="name", type="string", maxLength=128, title="Name"),
 *     @OA\Property(property="description", type="string", nullable=true, title="Description"),
 *     @OA\Property(property="is_active", type="boolean", title="Active"),
 *     @OA\Property(property="is_system", type="boolean", title="System"),
 *     @OA\Property(property="amount_type", type="string", enum={"fixed_ron", "percent"}, title="Amount Type"),
 *     @OA\Property(property="amount_value", type="integer", title="Amount Value"),
 *     @OA\Property(property="max_discount_ron", type="integer", nullable=true, title="Max Discount RON"),
 *     @OA\Property(property="scope_type", type="string", enum={"lifetime", "date_range", "event", "club"}, title="Scope Type"),
 *     @OA\Property(property="club_id", type="integer", format="int64", nullable=true, title="Club"),
 *     @OA\Property(property="valid_from", type="integer", format="timestamp", nullable=true, title="Valid From"),
 *     @OA\Property(property="valid_until", type="integer", format="timestamp", nullable=true, title="Valid Until"),
 *     @OA\Property(property="player_optional", type="boolean", title="Player Optional"),
 *     @OA\Property(property="stack_group", type="string", maxLength=64, nullable=true, title="Stack Group"),
 *     @OA\Property(property="exclusivity", type="string", enum={"stackable", "exclusive_in_group", "exclusive_global"}, title="Exclusivity"),
 *     @OA\Property(property="priority", type="integer", title="Priority"),
 *     @OA\Property(property="max_redemptions_per_player", type="integer", nullable=true, title="Max Redemptions Per Player"),
 *     @OA\Property(property="max_redemptions_global", type="integer", nullable=true, title="Max Redemptions Global"),
 *     @OA\Property(property="max_redemptions_per_event", type="integer", nullable=true, title="Max Redemptions Per PadelEvent"),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true)
 * )
 */
class BenefitDefinition extends Model
{
    use HasFactory;
    use HasResourceMetadata;

    public static array $searchableFields = [
        'slug',
        'name',
        'description',
    ];

    public static array $filterableFields = [
        'is_active',
        'is_system',
        'amount_type',
        'scope_type',
        'club_id',
        'exclusivity',
        'player_optional',
        'stack_group',
    ];

    public static array $sortableFields = [
        'id_benefit',
        'slug',
        'name',
        'is_active',
        'is_system',
        'amount_type',
        'amount_value',
        'scope_type',
        'priority',
        'exclusivity',
        'inserted_at',
        'updated_at',
    ];

    public static ?string $displayField = 'name';

    protected $table = 'benefit_definitions';

    protected $primaryKey = 'id_benefit';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'slug',
        'name',
        'description',
        'is_active',
        'is_system',
        'amount_type',
        'amount_value',
        'max_discount_ron',
        'scope_type',
        'club_id',
        'valid_from',
        'valid_until',
        'player_optional',
        'stack_group',
        'exclusivity',
        'priority',
        'max_redemptions_per_player',
        'max_redemptions_global',
        'max_redemptions_per_event',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_system' => 'boolean',
        'player_optional' => 'boolean',
        'amount_type' => BenefitAmountType::class,
        'amount_value' => 'integer',
        'max_discount_ron' => 'integer',
        'scope_type' => BenefitScopeType::class,
        'club_id' => 'integer',
        'valid_from' => 'integer',
        'valid_until' => 'integer',
        'exclusivity' => BenefitExclusivity::class,
        'priority' => 'integer',
        'max_redemptions_per_player' => 'integer',
        'max_redemptions_global' => 'integer',
        'max_redemptions_per_event' => 'integer',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function conditions(): HasMany
    {
        return $this->hasMany(BenefitCondition::class, 'benefit_definition_id', 'id_benefit');
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(
            PadelEvent::class,
            'benefit_events',
            'benefit_definition_id',
            'event_id',
            'id_benefit',
            'id_event'
        );
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(BenefitEntitlement::class, 'benefit_definition_id', 'id_benefit');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(BenefitRedemption::class, 'benefit_definition_id', 'id_benefit');
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class, 'club_id', 'id_club');
    }
}
