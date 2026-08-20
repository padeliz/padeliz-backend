<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @OA\Schema(
 *     schema="PlayerProfileImageImportRow",
 *     required={"player_profile_image_import_id", "row_index", "original_filename", "player_id", "action"},
 *     @OA\Property(property="id_player_profile_image_import_row", type="integer", format="int64", readOnly=true, title="ID"),
 *     @OA\Property(property="player_profile_image_import_id", type="integer", format="int64", readOnly=true, title="Import"),
 *     @OA\Property(property="row_index", type="integer", readOnly=true, title="Row"),
 *     @OA\Property(property="original_filename", type="string", maxLength=255, readOnly=true, title="Filename"),
 *     @OA\Property(property="player_id", type="integer", format="int64", readOnly=true, title="Player"),
 *     @OA\Property(property="player_profile_image_id", type="integer", format="int64", readOnly=true, title="Profile Image"),
 *     @OA\Property(property="is_pdf_primary", type="boolean", readOnly=true, title="PDF Primary"),
 *     @OA\Property(property="action", type="string", readOnly=true, title="Action"),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true, title="Created At")
 * )
 */
class PlayerProfileImageImportRow extends Model
{
    use HasResourceMetadata;

    public const ACTION_UPLOADED = 'uploaded';

    public static array $searchableFields = [
        'original_filename',
    ];

    public static array $filterableFields = [
        'player_profile_image_import_id',
        'player_id',
        'action',
    ];

    public static array $sortableFields = [
        'id_player_profile_image_import_row',
        'row_index',
        'action',
        'inserted_at',
    ];

    public static ?string $displayField = 'original_filename';

    protected $table = 'player_profile_image_import_rows';

    protected $primaryKey = 'id_player_profile_image_import_row';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'player_profile_image_import_id',
        'row_index',
        'original_filename',
        'player_id',
        'player_profile_image_id',
        'is_pdf_primary',
        'action',
    ];

    protected $casts = [
        'player_profile_image_import_id' => 'integer',
        'row_index' => 'integer',
        'player_id' => 'integer',
        'player_profile_image_id' => 'integer',
        'is_pdf_primary' => 'boolean',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function playerProfileImageImport(): BelongsTo
    {
        return $this->belongsTo(
            PlayerProfileImageImport::class,
            'player_profile_image_import_id',
            'id_player_profile_image_import'
        );
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id', 'id_player');
    }

    public function profileImage(): BelongsTo
    {
        return $this->belongsTo(PlayerProfileImage::class, 'player_profile_image_id');
    }
}
