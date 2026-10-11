<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @OA\Schema(
 *     schema="PlayerCustomFieldValueImportRow",
 *     required={"player_custom_field_value_import_id", "row_index", "player_user_id", "action"},
 *     @OA\Property(property="id_player_custom_field_value_import_row", type="integer", format="int64", readOnly=true),
 *     @OA\Property(property="player_custom_field_value_import_id", type="integer", format="int64"),
 *     @OA\Property(property="row_index", type="integer"),
 *     @OA\Property(property="full_name", type="string", nullable=true),
 *     @OA\Property(property="email", type="string", nullable=true),
 *     @OA\Property(property="phone", type="string", nullable=true),
 *     @OA\Property(property="player_user_id", type="integer", format="int64"),
 *     @OA\Property(property="action", type="string", enum={"imported"}),
 *     @OA\Property(property="answers_json", type="object"),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true),
 *     @OA\Property(property="player", ref="#/components/schemas/User", nullable=true)
 * )
 */
class PlayerCustomFieldValueImportRow extends Model
{
    use HasFactory;
    use HasResourceMetadata;

    public const ACTION_IMPORTED = 'imported';

    public static array $searchableFields = [
        'full_name',
        'email',
        'phone',
    ];

    public static array $filterableFields = [
        'player_custom_field_value_import_id',
        'player_user_id',
        'action',
    ];

    public static array $sortableFields = [
        'id_player_custom_field_value_import_row',
        'row_index',
        'player_user_id',
        'inserted_at',
    ];

    public static ?string $displayField = 'full_name';

    protected $table = 'player_custom_field_value_import_rows';

    protected $primaryKey = 'id_player_custom_field_value_import_row';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'player_custom_field_value_import_id',
        'row_index',
        'full_name',
        'email',
        'phone',
        'player_user_id',
        'action',
        'answers_json',
    ];

    protected $casts = [
        'player_custom_field_value_import_id' => 'integer',
        'row_index' => 'integer',
        'player_user_id' => 'integer',
        'answers_json' => 'array',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(
            PlayerCustomFieldValueImport::class,
            'player_custom_field_value_import_id',
            'id_player_custom_field_value_import'
        );
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_user_id', 'id_user');
    }
}
