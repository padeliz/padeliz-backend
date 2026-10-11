<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @OA\Schema(
 *     schema="PlayerMediaSource",
 *     required={"player_user_id", "media_source_id"},
 *     @OA\Property(property="id_player_media_source", type="integer", format="int64", readOnly=true, title="ID"),
 *     @OA\Property(property="player_user_id", type="integer", format="int64", title="Player"),
 *     @OA\Property(property="media_source_id", type="integer", format="int64", title="Media Source"),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true, title="Created At"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true, title="Updated At")
 * )
 */
class PlayerMediaSource extends Model
{
    use HasFactory;

    protected $table = 'player_media_sources';

    protected $primaryKey = 'id_player_media_source';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'player_user_id',
        'media_source_id',
    ];

    protected $casts = [
        'player_user_id' => 'integer',
        'media_source_id' => 'integer',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function player(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_user_id', 'id_user');
    }

    public function mediaSource(): BelongsTo
    {
        return $this->belongsTo(MediaSource::class, 'media_source_id', 'id_media_source');
    }
}
