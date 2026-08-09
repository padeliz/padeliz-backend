<?php

declare(strict_types=1);

namespace App\Models;

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
 *     @OA\Property(property="linked_at", type="integer", format="timestamp"),
 *     @OA\Property(property="last_login_at", type="integer", format="timestamp", nullable=true),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true)
 * )
 */
class PlayerIdentity extends Model
{
    use HasFactory;

    public const PROVIDER_GOOGLE = 'google';
    public const PROVIDER_APPLE = 'apple';
    public const PROVIDER_SMS = 'sms';
    public const PROVIDER_DEV = 'dev';

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
        'linked_at',
        'last_login_at',
    ];

    protected $casts = [
        'linked_at' => 'integer',
        'last_login_at' => 'integer',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

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
