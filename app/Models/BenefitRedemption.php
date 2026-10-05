<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Benefit\BenefitSelectionSource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BenefitRedemption extends Model
{
    use HasFactory;

    protected $table = 'benefit_redemptions';

    protected $primaryKey = 'id_redemption';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'checkout_item_id',
        'benefit_definition_id',
        'entitlement_id',
        'referral_attribution_id',
        'amount_ron',
        'benefit_name',
        'benefit_snapshot_hash',
        'selection_source',
        'reason',
    ];

    protected $casts = [
        'checkout_item_id' => 'integer',
        'benefit_definition_id' => 'integer',
        'entitlement_id' => 'integer',
        'referral_attribution_id' => 'integer',
        'amount_ron' => 'integer',
        'selection_source' => BenefitSelectionSource::class,
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function checkoutItem(): BelongsTo
    {
        return $this->belongsTo(EventRegistrationCheckoutItem::class, 'checkout_item_id', 'id_checkout_item');
    }

    public function benefitDefinition(): BelongsTo
    {
        return $this->belongsTo(BenefitDefinition::class, 'benefit_definition_id', 'id_benefit');
    }

    public function entitlement(): BelongsTo
    {
        return $this->belongsTo(BenefitEntitlement::class, 'entitlement_id', 'id_entitlement');
    }

    public function referralAttribution(): BelongsTo
    {
        return $this->belongsTo(EventReferralAttribution::class, 'referral_attribution_id', 'id_attribution');
    }
}
