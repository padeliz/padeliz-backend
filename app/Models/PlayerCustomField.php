<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlayerCustomFieldType;
use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @OA\Schema(
 *     schema="PlayerCustomField",
 *     required={"code", "label", "type"},
 *     @OA\Property(property="id_player_custom_field", type="integer", format="int64", readOnly=true, title="ID"),
 *     @OA\Property(property="code", type="string", maxLength=64, title="Code"),
 *     @OA\Property(property="label", type="string", maxLength=255, title="Label"),
 *     @OA\Property(property="help_text", type="string", maxLength=512, nullable=true, title="Help Text"),
 *     @OA\Property(property="sort_order", type="integer", title="Sort Order"),
 *     @OA\Property(
 *         property="type",
 *         type="string",
 *         enum={"text", "integer", "boolean", "date", "single_select", "multi_select"},
 *         title="Type"
 *     ),
 *     @OA\Property(property="rules_json", type="object", nullable=true, readOnly=true, title="Rules"),
 *     @OA\Property(property="is_required", type="boolean", title="Required"),
 *     @OA\Property(property="archived_at", type="integer", format="timestamp", nullable=true, readOnly=true, title="Archived At"),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true, title="Created At"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true, title="Updated At"),
 *     @OA\Property(
 *         property="options",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/PlayerCustomFieldOption"),
 *         title="Options"
 *     )
 * )
 */
class PlayerCustomField extends Model
{
    use HasFactory;
    use HasResourceMetadata;

    public static array $searchableFields = [
        'code',
        'label',
    ];

    public static array $filterableFields = [
        'type',
    ];

    public static array $sortableFields = [
        'id_player_custom_field',
        'code',
        'label',
        'sort_order',
        'type',
        'inserted_at',
        'updated_at',
    ];

    public static ?string $displayField = 'label';

    protected $table = 'player_custom_fields';

    protected $primaryKey = 'id_player_custom_field';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'code',
        'label',
        'help_text',
        'sort_order',
        'type',
        'rules_json',
        'is_required',
        'archived_at',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'rules_json' => 'array',
        'is_required' => 'boolean',
        'archived_at' => 'integer',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
        'type' => PlayerCustomFieldType::class,
    ];

    public function options(): HasMany
    {
        return $this->hasMany(PlayerCustomFieldOption::class, 'player_custom_field_id', 'id_player_custom_field')
            ->orderBy('sort_order')
            ->orderBy('id_player_custom_field_option');
    }

    public function values(): HasMany
    {
        return $this->hasMany(PlayerCustomFieldValue::class, 'player_custom_field_id', 'id_player_custom_field');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }
}
