<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

/**
 * @OA\Schema(
 *     schema="PlayerInviteEvent",
 *     required={"event"},
 *     @OA\Property(property="id_invite_event", type="integer", format="int64", readOnly=true),
 *     @OA\Property(property="invite_id", type="integer", format="int64", nullable=true),
 *     @OA\Property(property="player_user_id", type="integer", format="int64", nullable=true),
 *     @OA\Property(property="event", type="string", enum={"created", "redeem_started", "redeem_succeeded", "redeem_failed", "revoked"}),
 *     @OA\Property(property="result_code", type="string", maxLength=64, nullable=true),
 *     @OA\Property(property="provider", type="string", enum={"google", "apple", "sms", "dev"}, nullable=true),
 *     @OA\Property(property="provider_subject", type="string", maxLength=255, nullable=true),
 *     @OA\Property(property="contact_kind", type="string", enum={"email", "phone"}, nullable=true),
 *     @OA\Property(property="contact_value_hash", type="string", maxLength=64, nullable=true),
 *     @OA\Property(property="actor_player_user_id", type="integer", format="int64", nullable=true),
 *     @OA\Property(property="actor_manager_user_id", type="integer", format="int64", nullable=true),
 *     @OA\Property(property="ip", type="string", maxLength=45, nullable=true),
 *     @OA\Property(property="user_agent", type="string", maxLength=512, nullable=true),
 *     @OA\Property(property="meta", type="object", nullable=true),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true)
 * )
 */
class PlayerInviteEvent extends Model
{
    use HasFactory;

    public const EVENT_CREATED = 'created';
    public const EVENT_REDEEM_STARTED = 'redeem_started';
    public const EVENT_REDEEM_SUCCEEDED = 'redeem_succeeded';
    public const EVENT_REDEEM_FAILED = 'redeem_failed';
    public const EVENT_REVOKED = 'revoked';

    protected $table = 'player_invite_events';

    protected $primaryKey = 'id_invite_event';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';
    public const UPDATED_AT = null;

    protected $fillable = [
        'invite_id',
        'player_user_id',
        'event',
        'result_code',
        'provider',
        'provider_subject',
        'contact_kind',
        'contact_value_hash',
        'actor_player_user_id',
        'actor_manager_user_id',
        'ip',
        'user_agent',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'inserted_at' => 'integer',
    ];

    public function invite(): BelongsTo
    {
        return $this->belongsTo(PlayerInvite::class, 'invite_id', 'id_invite');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_user_id', 'id_user');
    }

    public function actorPlayer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_player_user_id', 'id_user');
    }

    public function actorManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_manager_user_id', 'id_user');
    }

    public static function log(
        string $event,
        ?PlayerInvite $invite = null,
        ?Request $request = null,
        ?string $resultCode = null,
        ?string $provider = null,
        ?string $providerSubject = null,
        ?string $contactKind = null,
        ?string $contactValue = null,
        ?int $actorPlayerUserId = null,
        ?int $actorManagerUserId = null,
        ?array $meta = null,
        ?int $playerId = null,
    ): self {
        return self::create([
            'invite_id' => $invite?->id_invite,
            'player_user_id' => $playerId ?? $invite?->player_user_id,
            'event' => $event,
            'result_code' => $resultCode,
            'provider' => $provider,
            'provider_subject' => $providerSubject,
            'contact_kind' => $contactKind,
            'contact_value_hash' => $contactValue !== null ? hash('sha256', $contactValue) : null,
            'actor_player_user_id' => $actorPlayerUserId,
            'actor_manager_user_id' => $actorManagerUserId,
            'ip' => $request?->ip(),
            'user_agent' => $request?->userAgent() !== null
                ? mb_substr($request->userAgent(), 0, 512)
                : null,
            'meta' => $meta,
        ]);
    }
}
