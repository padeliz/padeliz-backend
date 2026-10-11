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
 *     schema="PlayerCustomFieldValueImport",
 *     required={"format", "original_filename"},
 *     @OA\Property(property="id_player_custom_field_value_import", type="integer", format="int64", readOnly=true, title="ID"),
 *     @OA\Property(property="imported_by_manager_user_id", type="integer", format="int64", nullable=true, readOnly=true, title="Imported By"),
 *     @OA\Property(property="format", type="string", maxLength=64, readOnly=true, title="Format"),
 *     @OA\Property(property="original_filename", type="string", maxLength=255, readOnly=true, title="Filename"),
 *     @OA\Property(property="event_id", type="integer", format="int64", nullable=true, title="PadelEvent"),
 *     @OA\Property(property="included_count", type="integer", readOnly=true, title="Included"),
 *     @OA\Property(property="values_new_count", type="integer", readOnly=true, title="Values New"),
 *     @OA\Property(property="values_replaced_count", type="integer", readOnly=true, title="Values Replaced"),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true, title="Created At"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true, title="Updated At"),
 *     @OA\Property(property="event", ref="#/components/schemas/Event", nullable=true),
 *     @OA\Property(property="importedByManager", ref="#/components/schemas/User", nullable=true, title="Importer"),
 *     @OA\Property(
 *         property="rows",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/PlayerCustomFieldValueImportRow")
 *     )
 * )
 */
class PlayerCustomFieldValueImport extends Model
{
    use HasFactory;
    use HasResourceMetadata;

    public static array $searchableFields = [
        'original_filename',
        'format',
    ];

    public static array $filterableFields = [
        'format',
        'imported_by_manager_user_id',
        'event_id',
    ];

    public static array $sortableFields = [
        'id_player_custom_field_value_import',
        'format',
        'included_count',
        'values_new_count',
        'values_replaced_count',
        'inserted_at',
        'updated_at',
    ];

    public static ?string $displayField = 'original_filename';

    protected $table = 'player_custom_field_value_imports';

    protected $primaryKey = 'id_player_custom_field_value_import';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'imported_by_manager_user_id',
        'format',
        'original_filename',
        'event_id',
        'included_count',
        'values_new_count',
        'values_replaced_count',
    ];

    protected $casts = [
        'imported_by_manager_user_id' => 'integer',
        'event_id' => 'integer',
        'included_count' => 'integer',
        'values_new_count' => 'integer',
        'values_replaced_count' => 'integer',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function importedByManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by_manager_user_id', 'id_user');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(PadelEvent::class, 'event_id', 'id_event');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(
            PlayerCustomFieldValueImportRow::class,
            'player_custom_field_value_import_id',
            'id_player_custom_field_value_import'
        )->orderBy('row_index');
    }
}
