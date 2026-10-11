<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @OA\Schema(
 *     schema="PlayerProfileImageImport",
 *     required={"original_filename"},
 *     @OA\Property(property="id_player_profile_image_import", type="integer", format="int64", readOnly=true, title="ID"),
 *     @OA\Property(property="imported_by_manager_user_id", type="integer", format="int64", nullable=true, readOnly=true, title="Imported By"),
 *     @OA\Property(property="original_filename", type="string", maxLength=255, readOnly=true, title="Filename"),
 *     @OA\Property(property="included_count", type="integer", readOnly=true, title="Included"),
 *     @OA\Property(property="uploaded_count", type="integer", readOnly=true, title="Uploaded"),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true, title="Created At"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true, title="Updated At"),
 *     @OA\Property(property="importedByManager", ref="#/components/schemas/User", nullable=true, title="Importer"),
 *     @OA\Property(
 *         property="rows",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/PlayerProfileImageImportRow"),
 *         title="Rows",
 *         description="Import row snapshots"
 *     )
 * )
 */
class PlayerProfileImageImport extends Model
{
    use HasResourceMetadata;

    public static array $searchableFields = [
        'original_filename',
    ];

    public static array $filterableFields = [
        'imported_by_manager_user_id',
    ];

    public static array $sortableFields = [
        'id_player_profile_image_import',
        'original_filename',
        'included_count',
        'uploaded_count',
        'inserted_at',
        'updated_at',
    ];

    public static ?string $displayField = 'original_filename';

    protected $table = 'player_profile_image_imports';

    protected $primaryKey = 'id_player_profile_image_import';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'imported_by_manager_user_id',
        'original_filename',
        'included_count',
        'uploaded_count',
    ];

    protected $casts = [
        'imported_by_manager_user_id' => 'integer',
        'included_count' => 'integer',
        'uploaded_count' => 'integer',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function importedByManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by_manager_user_id', 'id_user');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(
            PlayerProfileImageImportRow::class,
            'player_profile_image_import_id',
            'id_player_profile_image_import'
        )->orderBy('row_index');
    }
}
