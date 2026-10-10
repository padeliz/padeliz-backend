<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @OA\Schema(
 *     schema="PlayerIdentity",
 *     required={"player_id", "provider", "provider_subject", "linked_at"},
 *     @OA\Property(property="id_identity", type="integer", format="int64", readOnly=true),
 *     @OA\Property(property="player_id", type="integer", format="int64"),
 *     @OA\Property(property="provider", type="string", enum={"google", "apple", "sms", "dev"}),
 *     @OA\Property(property="provider_subject", type="string", maxLength=255),
 *     @OA\Property(property="email_id", type="integer", format="int64", nullable=true),
 *     @OA\Property(property="phone_id", type="integer", format="int64", nullable=true),
 *     @OA\Property(property="display_name", type="string", maxLength=255, nullable=true),
 *     @OA\Property(property="avatar_url", type="string", maxLength=512, nullable=true),
 *     @OA\Property(property="is_avatar_primary", type="boolean"),
 *     @OA\Property(property="linked_at", type="integer", format="timestamp"),
 *     @OA\Property(property="last_login_at", type="integer", format="timestamp", nullable=true),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true)
 * )
 */
class PlayerIdentity extends Model
{
    use HasFactory;
    use HasResourceMetadata;

    public const PROVIDER_GOOGLE = 'google';
    public const PROVIDER_APPLE = 'apple';
    public const PROVIDER_SMS = 'sms';
    public const PROVIDER_DEV = 'dev';

    /**
     * Fields that can be searched
     */
    public static array $searchableFields = [
        'display_name',
        'provider_subject',
    ];

    /**
     * Fields that can be filtered
     */
    public static array $filterableFields = [
        'player_id',
        'provider',
        'is_avatar_primary',
    ];

    /**
     * Fields that can be sorted
     */
    public static array $sortableFields = [
        'id_identity',
        'player_id',
        'provider',
        'display_name',
        'is_avatar_primary',
        'linked_at',
        'last_login_at',
        'inserted_at',
        'updated_at',
    ];

    /**
     * Field that will be used to display the identity in the UI
     */
    public static ?string $displayField = 'display_name';

    protected $table = 'player_identities';

    protected $primaryKey = 'id_identity';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';
    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'player_id',
        'provider',
        'provider_subject',
        'email_id',
        'phone_id',
        'display_name',
        'avatar_url',
        'is_avatar_primary',
        'linked_at',
        'last_login_at',
    ];

    protected $casts = [
        'linked_at' => 'integer',
        'last_login_at' => 'integer',
        'is_avatar_primary' => 'boolean',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    protected static function booted(): void
    {
        static::created(function (PlayerIdentity $identity): void {
            $identity->promoteFirstGoogleAvatar();
        });
    }

    /**
     * The first linked Google identity becomes the primary App avatar automatically (the player can switch it later).
     */
    public function promoteFirstGoogleAvatar(): void
    {
        if ($this->provider !== self::PROVIDER_GOOGLE) {
            return;
        }

        if ($this->avatar_url === null || $this->avatar_url === '') {
            return;
        }

        $hasPrimary = static::query()
            ->where('player_id', $this->player_id)
            ->where('is_avatar_primary', true)
            ->whereKeyNot($this->getKey())
            ->exists();

        if ($hasPrimary || $this->is_avatar_primary) {
            return;
        }

        $this->is_avatar_primary = true;
        $this->save();
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id', 'id_player');
    }

    public function email(): BelongsTo
    {
        return $this->belongsTo(PlayerEmail::class, 'email_id', 'id_player_email');
    }

    public function phone(): BelongsTo
    {
        return $this->belongsTo(PlayerPhone::class, 'phone_id', 'id_player_phone');
    }

    public function touchLastLogin(): void
    {
        $this->last_login_at = time();
        $this->save();
    }
}
