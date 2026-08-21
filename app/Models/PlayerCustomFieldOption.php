<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @OA\Schema(
 *     schema="PlayerCustomFieldOption",
 *     required={"player_custom_field_id", "code", "label"},
 *     @OA\Property(property="id_player_custom_field_option", type="integer", format="int64", readOnly=true, title="ID"),
 *     @OA\Property(property="player_custom_field_id", type="integer", format="int64", title="Field"),
 *     @OA\Property(property="code", type="string", maxLength=64, title="Code"),
 *     @OA\Property(property="label", type="string", maxLength=255, title="Label"),
 *     @OA\Property(property="sort_order", type="integer", title="Sort Order"),
 *     @OA\Property(property="is_active", type="boolean", title="Active"),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true, title="Created At"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true, title="Updated At")
 * )
 */
class PlayerCustomFieldOption extends Model
{
    use HasFactory;
    use HasResourceMetadata;

    public static array $searchableFields = [
        'code',
        'label',
    ];

    public static array $filterableFields = [
        'player_custom_field_id',
        'is_active',
    ];

    public static array $sortableFields = [
        'id_player_custom_field_option',
        'player_custom_field_id',
        'code',
        'label',
        'sort_order',
        'is_active',
        'inserted_at',
        'updated_at',
    ];

    public static ?string $displayField = 'label';

    protected $table = 'player_custom_field_options';

    protected $primaryKey = 'id_player_custom_field_option';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'player_custom_field_id',
        'code',
        'label',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'player_custom_field_id' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function field(): BelongsTo
    {
        return $this->belongsTo(PlayerCustomField::class, 'player_custom_field_id', 'id_player_custom_field');
    }

    public function valueOptions(): HasMany
    {
        return $this->hasMany(PlayerCustomFieldValueOption::class, 'option_id', 'id_player_custom_field_option');
    }
}
