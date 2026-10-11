<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @OA\Schema(
 *     schema="UserImport",
 *     required={"format", "original_filename"},
 *     @OA\Property(property="id_user_import", type="integer", format="int64", readOnly=true, title="ID"),
 *     @OA\Property(property="imported_by_manager_user_id", type="integer", format="int64", nullable=true, minimum=1, readOnly=true, title="Imported By"),
 *     @OA\Property(property="format", type="string", maxLength=32, readOnly=true, title="Format"),
 *     @OA\Property(property="original_filename", type="string", maxLength=255, readOnly=true, title="Filename"),
 *     @OA\Property(property="included_count", type="integer", readOnly=true, title="Included"),
 *     @OA\Property(property="created_count", type="integer", readOnly=true, title="Created"),
 *     @OA\Property(property="attached_count", type="integer", readOnly=true, title="Attached"),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true, title="Created At"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true, title="Updated At"),
 *     @OA\Property(property="importedByManager", ref="#/components/schemas/User", nullable=true, title="Importer"),
 *     @OA\Property(
 *         property="rows",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/UserImportRow"),
 *         title="Rows",
 *         description="Import row snapshots"
 *     )
 * )
 */
class UserImport extends Model
{
    use HasResourceMetadata;

    public static array $searchableFields = [
        'original_filename',
        'format',
    ];

    public static array $filterableFields = [
        'format',
        'imported_by_manager_user_id',
    ];

    public static array $sortableFields = [
        'id_user_import',
        'format',
        'included_count',
        'created_count',
        'attached_count',
        'inserted_at',
        'updated_at',
    ];

    public static ?string $displayField = 'original_filename';

    protected $table = 'user_imports';

    protected $primaryKey = 'id_user_import';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'imported_by_manager_user_id',
        'format',
        'original_filename',
        'included_count',
        'created_count',
        'attached_count',
    ];

    protected $casts = [
        'imported_by_manager_user_id' => 'integer',
        'included_count' => 'integer',
        'created_count' => 'integer',
        'attached_count' => 'integer',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function importedByManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by_manager_user_id', 'id_user');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(UserImportRow::class, 'user_import_id', 'id_user_import')
            ->orderBy('row_index');
    }
}
