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
 *     schema="UserPhone",
 *     required={"user_id", "phone", "purposes"},
 *     @OA\Property(property="id_user_phone", type="integer", format="int64", readOnly=true),
 *     @OA\Property(property="user_id", type="integer", format="int64"),
 *     @OA\Property(property="phone", type="string", maxLength=30),
 *     @OA\Property(property="purposes", type="array", @OA\Items(type="string")),
 *     @OA\Property(property="is_primary", type="boolean", default=false),
 *     @OA\Property(property="verified_at", type="integer", format="timestamp", nullable=true),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true)
 * )
 */
class UserPhone extends Model
{
    use HasFactory;
    use HasResourceMetadata;

    /**
     * Fields that can be searched
     */
    public static array $searchableFields = [
        'phone',
    ];

    /**
     * Fields that can be filtered
     */
    public static array $filterableFields = [
        'user_id',
        'is_primary',
    ];

    /**
     * Fields that can be sorted
     */
    public static array $sortableFields = [
        'id_user_phone',
        'user_id',
        'phone',
        'is_primary',
        'verified_at',
        'inserted_at',
        'updated_at',
    ];

    /**
     * Field that will be used to display the phone in the UI
     */
    public static ?string $displayField = 'phone';

    protected $table = 'user_phones';

    protected $primaryKey = 'id_user_phone';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';
    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'user_id',
        'phone',
        'purposes',
        'is_primary',
        'verified_at',
    ];

    protected $casts = [
        'purposes' => 'array',
        'is_primary' => 'boolean',
        'verified_at' => 'integer',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id_user');
    }

    public function identities(): HasMany
    {
        return $this->hasMany(UserIdentity::class, 'phone_id', 'id_user_phone');
    }
}
