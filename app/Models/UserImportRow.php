<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @OA\Schema(
 *     schema="UserImportRow",
 *     required={"user_import_id", "row_index", "action"},
 *     @OA\Property(property="id_user_import_row", type="integer", format="int64", readOnly=true, title="ID"),
 *     @OA\Property(property="user_import_id", type="integer", format="int64", minimum=1, readOnly=true, title="Import"),
 *     @OA\Property(property="row_index", type="integer", readOnly=true, title="Row"),
 *     @OA\Property(property="first_name", type="string", maxLength=50, nullable=true, readOnly=true, title="First Name"),
 *     @OA\Property(property="middle_name", type="string", maxLength=50, nullable=true, readOnly=true, title="Middle Name"),
 *     @OA\Property(property="last_name", type="string", maxLength=50, nullable=true, readOnly=true, title="Last Name"),
 *     @OA\Property(property="email", type="string", maxLength=255, nullable=true, readOnly=true, title="Email"),
 *     @OA\Property(property="phone", type="string", maxLength=30, nullable=true, readOnly=true, title="Phone"),
 *     @OA\Property(property="user_id", type="integer", format="int64", nullable=true, minimum=1, readOnly=true, title="Player"),
 *     @OA\Property(property="action", type="string", enum={"created", "attached"}, readOnly=true, title="Action"),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true, title="Created At"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true, title="Updated At")
 * )
 */
class UserImportRow extends Model
{
    use HasResourceMetadata;

    public const ACTION_CREATED = 'created';

    public const ACTION_ATTACHED = 'attached';

    public static array $searchableFields = [
        'first_name',
        'last_name',
        'email',
        'phone',
    ];

    public static array $filterableFields = [
        'user_import_id',
        'user_id',
        'action',
    ];

    public static array $sortableFields = [
        'id_user_import_row',
        'row_index',
        'action',
        'inserted_at',
        'updated_at',
    ];

    public static ?string $displayField = 'first_name';

    protected $table = 'user_import_rows';

    protected $primaryKey = 'id_user_import_row';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'user_import_id',
        'row_index',
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'phone',
        'user_id',
        'action',
    ];

    protected $casts = [
        'user_import_id' => 'integer',
        'row_index' => 'integer',
        'user_id' => 'integer',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function userImport(): BelongsTo
    {
        return $this->belongsTo(UserImport::class, 'user_import_id', 'id_user_import');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id_user');
    }
}
