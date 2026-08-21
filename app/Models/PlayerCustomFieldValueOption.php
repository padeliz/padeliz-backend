<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @OA\Schema(
 *     schema="PlayerCustomFieldValueOption",
 *     required={"player_custom_field_value_id", "option_label_snapshot"},
 *     @OA\Property(property="id_player_custom_field_value_option", type="integer", format="int64", readOnly=true),
 *     @OA\Property(property="player_custom_field_value_id", type="integer", format="int64"),
 *     @OA\Property(property="option_id", type="integer", format="int64", nullable=true),
 *     @OA\Property(property="option_label_snapshot", type="string", maxLength=255),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true)
 * )
 */
class PlayerCustomFieldValueOption extends Model
{
    use HasFactory;

    protected $table = 'player_custom_field_value_options';

    protected $primaryKey = 'id_player_custom_field_value_option';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'player_custom_field_value_id',
        'option_id',
        'option_label_snapshot',
    ];

    protected $casts = [
        'player_custom_field_value_id' => 'integer',
        'option_id' => 'integer',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function value(): BelongsTo
    {
        return $this->belongsTo(
            PlayerCustomFieldValue::class,
            'player_custom_field_value_id',
            'id_player_custom_field_value'
        );
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(PlayerCustomFieldOption::class, 'option_id', 'id_player_custom_field_option');
    }
}
