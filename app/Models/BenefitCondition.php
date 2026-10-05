<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Benefit\BenefitConditionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BenefitCondition extends Model
{
    use HasFactory;

    protected $table = 'benefit_conditions';

    protected $primaryKey = 'id_benefit_condition';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'benefit_definition_id',
        'condition_type',
        'parameters',
    ];

    protected $casts = [
        'benefit_definition_id' => 'integer',
        'condition_type' => BenefitConditionType::class,
        'parameters' => 'array',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function benefitDefinition(): BelongsTo
    {
        return $this->belongsTo(BenefitDefinition::class, 'benefit_definition_id', 'id_benefit');
    }
}
