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
 *     schema="PlayerCustomFieldValue",
 *     required={"player_id", "player_custom_field_id", "submitted_via"},
 *     @OA\Property(property="id_player_custom_field_value", type="integer", format="int64", readOnly=true, title="ID"),
 *     @OA\Property(property="player_id", type="integer", format="int64", title="Player"),
 *     @OA\Property(property="player_custom_field_id", type="integer", format="int64", title="Field"),
 *     @OA\Property(property="event_id", type="integer", format="int64", nullable=true, title="PadelEvent"),
 *     @OA\Property(property="value_text", type="string", nullable=true, title="Text Value"),
 *     @OA\Property(property="value_integer", type="integer", nullable=true, title="Integer Value"),
 *     @OA\Property(property="value_boolean", type="boolean", nullable=true, title="Boolean Value"),
 *     @OA\Property(property="value_date", type="string", format="date", nullable=true, title="Date Value"),
 *     @OA\Property(property="value", description="Computed display value", nullable=true),
 *     @OA\Property(property="submitted_by_player_id", type="integer", format="int64", nullable=true, title="Submitted By"),
 *     @OA\Property(property="submitted_via", type="string", enum={"manager", "csv"}, title="Submitted Via"),
 *     @OA\Property(property="created_via_import_id", type="integer", format="int64", nullable=true, title="Import"),
 *     @OA\Property(property="invalidated_at", type="integer", format="timestamp", nullable=true, title="Invalidated At"),
 *     @OA\Property(property="invalidated_by_player_id", type="integer", format="int64", nullable=true, title="Invalidated By"),
 *     @OA\Property(property="invalidation_reason", type="string", maxLength=512, nullable=true, title="Invalidation Reason"),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true, title="Created At"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true, title="Updated At"),
 *     @OA\Property(property="field", ref="#/components/schemas/PlayerCustomField", nullable=true),
 *     @OA\Property(property="event", ref="#/components/schemas/Event", nullable=true),
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

    public const SUBMITTED_VIA_MANAGER = 'manager';

    public const SUBMITTED_VIA_CSV = 'csv';

    public static array $searchableFields = [];

    public static array $filterableFields = [
        'player_id',
        'player_custom_field_id',
        'event_id',
        'submitted_via',
    ];

    public static array $sortableFields = [
        'id_player_custom_field_value',
        'player_id',
        'player_custom_field_id',
        'event_id',
        'inserted_at',
        'updated_at',
        'invalidated_at',
    ];

    public static ?string $displayField = null;

    protected $table = 'player_custom_field_values';

    protected $primaryKey = 'id_player_custom_field_value';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'player_id',
        'player_custom_field_id',
        'event_id',
        'value_text',
        'value_integer',
        'value_boolean',
        'value_date',
        'submitted_by_player_id',
        'submitted_via',
        'created_via_import_id',
        'invalidated_at',
        'invalidated_by_player_id',
        'invalidation_reason',
    ];

    protected $casts = [
        'player_id' => 'integer',
        'player_custom_field_id' => 'integer',
        'event_id' => 'integer',
        'value_integer' => 'integer',
        'value_boolean' => 'boolean',
        'submitted_by_player_id' => 'integer',
        'created_via_import_id' => 'integer',
        'invalidated_at' => 'integer',
        'invalidated_by_player_id' => 'integer',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id', 'id_player');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(PlayerCustomField::class, 'player_custom_field_id', 'id_player_custom_field');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(PadelEvent::class, 'event_id', 'id_event');
    }

    public function submittedByPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'submitted_by_player_id', 'id_player');
    }

    public function createdViaImport(): BelongsTo
    {
        return $this->belongsTo(
            PlayerCustomFieldValueImport::class,
            'created_via_import_id',
            'id_player_custom_field_value_import'
        );
    }

    public function invalidatedByPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'invalidated_by_player_id', 'id_player');
    }

    public function selectedOptions(): HasMany
    {
        return $this->hasMany(
            PlayerCustomFieldValueOption::class,
            'player_custom_field_value_id',
            'id_player_custom_field_value'
        );
    }

    public function isInvalidated(): bool
    {
        return $this->invalidated_at !== null;
    }
}
