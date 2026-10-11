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
 *     schema="UserEmail",
 *     required={"user_id", "email", "purposes"},
 *     @OA\Property(property="id_user_email", type="integer", format="int64", readOnly=true),
 *     @OA\Property(property="user_id", type="integer", format="int64"),
 *     @OA\Property(property="email", type="string", maxLength=255),
 *     @OA\Property(property="purposes", type="array", @OA\Items(type="string")),
 *     @OA\Property(property="is_primary", type="boolean", default=false),
 *     @OA\Property(property="verified_at", type="integer", format="timestamp", nullable=true),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true)
 * )
 */
class UserEmail extends Model
{
    use HasFactory;
    use HasResourceMetadata;

    /**
     * Fields that can be searched
     */
    public static array $searchableFields = [
        'email',
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
        'id_user_email',
        'user_id',
        'email',
        'is_primary',
        'verified_at',
        'inserted_at',
        'updated_at',
    ];

    /**
     * Field that will be used to display the email in the UI
     */
    public static ?string $displayField = 'email';

    protected $table = 'user_emails';

    protected $primaryKey = 'id_user_email';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';
    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'user_id',
        'email',
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
        return $this->hasMany(UserIdentity::class, 'email_id', 'id_user_email');
    }

    public function hasPurpose(string $purpose): bool
    {
        return in_array($purpose, $this->purposes ?? [], true);
    }

    public function addPurpose(string $purpose): void
    {
        $purposes = $this->purposes ?? [];
        if (! in_array($purpose, $purposes, true)) {
            $purposes[] = $purpose;
            $this->purposes = $purposes;
        }
    }
}
