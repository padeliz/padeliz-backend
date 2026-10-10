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
 *     schema="PlayerPhone",
 *     required={"player_id", "phone", "purposes"},
 *     @OA\Property(property="id_player_phone", type="integer", format="int64", readOnly=true),
 *     @OA\Property(property="player_id", type="integer", format="int64"),
 *     @OA\Property(property="phone", type="string", maxLength=30),
 *     @OA\Property(property="purposes", type="array", @OA\Items(type="string")),
 *     @OA\Property(property="is_primary", type="boolean", default=false),
 *     @OA\Property(property="verified_at", type="integer", format="timestamp", nullable=true),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true)
 * )
 */
class PlayerPhone extends Model
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
        'player_id',
        'is_primary',
    ];

    /**
     * Fields that can be sorted
     */
    public static array $sortableFields = [
        'id_player_phone',
        'player_id',
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

    protected $table = 'player_phones';

    protected $primaryKey = 'id_player_phone';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';
    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'player_id',
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

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id', 'id_player');
    }

    public function identities(): HasMany
    {
        return $this->hasMany(PlayerIdentity::class, 'phone_id', 'id_player_phone');
    }
}
