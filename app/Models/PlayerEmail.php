<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @OA\Schema(
 *     schema="PlayerEmail",
 *     required={"player_id", "email", "purposes"},
 *     @OA\Property(property="id_player_email", type="integer", format="int64", readOnly=true),
 *     @OA\Property(property="player_id", type="integer", format="int64"),
 *     @OA\Property(property="email", type="string", maxLength=255),
 *     @OA\Property(property="purposes", type="array", @OA\Items(type="string")),
 *     @OA\Property(property="is_primary", type="boolean", default=false),
 *     @OA\Property(property="verified_at", type="integer", format="timestamp", nullable=true),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true)
 * )
 */
class PlayerEmail extends Model
{
    use HasFactory;

    protected $table = 'player_emails';

    protected $primaryKey = 'id_player_email';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';
    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'player_id',
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

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id', 'id_player');
    }

    public function identities(): HasMany
    {
        return $this->hasMany(PlayerIdentity::class, 'email_id', 'id_player_email');
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
