<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @OA\Schema(
 *     schema="PlayerInvite",
 *     required={"player_user_id", "token_hash", "expires_at"},
 *     @OA\Property(property="id_invite", type="integer", format="int64", readOnly=true),
 *     @OA\Property(property="player_user_id", type="integer", format="int64"),
 *     @OA\Property(property="token_hash", type="string", maxLength=64),
 *     @OA\Property(property="expires_at", type="integer", format="timestamp"),
 *     @OA\Property(property="accepted_at", type="integer", format="timestamp", nullable=true),
 *     @OA\Property(property="revoked_at", type="integer", format="timestamp", nullable=true),
 *     @OA\Property(property="created_by_manager_user_id", type="integer", format="int64", nullable=true),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true)
 * )
 */
class PlayerInvite extends Model
{
    use HasFactory;

    protected $table = 'player_invites';

    protected $primaryKey = 'id_invite';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';
    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'player_user_id',
        'token_hash',
        'expires_at',
        'accepted_at',
        'revoked_at',
        'created_by_manager_user_id',
    ];

    protected $casts = [
        'expires_at' => 'integer',
        'accepted_at' => 'integer',
        'revoked_at' => 'integer',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_user_id', 'id_user');
    }

    public function createdByManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_manager_user_id', 'id_user');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PlayerInviteEvent::class, 'invite_id', 'id_invite');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', time());
    }

    public function isActive(): bool
    {
        return $this->accepted_at === null
            && $this->revoked_at === null
            && $this->expires_at > time();
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
