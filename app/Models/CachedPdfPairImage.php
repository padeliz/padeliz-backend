<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Cached MATCHES couple composite for PDF generation. Not Manager CRUD.
 *
 * @property-read null|string $url
 */
class CachedPdfPairImage extends Model
{
    protected $table = 'cached_pdf_pair_images';

    protected $primaryKey = 'id_cached_pdf_pair_image';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'player_1_user_id',
        'player_2_user_id',
        'player_1_is_collecting_points',
        'player_2_is_collecting_points',
        'picture_path',
        'source_player_1_profile_image_id',
        'source_player_2_profile_image_id',
    ];

    protected $casts = [
        'player_1_user_id' => 'integer',
        'player_2_user_id' => 'integer',
        'player_1_is_collecting_points' => 'boolean',
        'player_2_is_collecting_points' => 'boolean',
        'source_player_1_profile_image_id' => 'integer',
        'source_player_2_profile_image_id' => 'integer',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    protected $appends = ['url'];

    protected static function booted(): void
    {
        static::deleting(function (CachedPdfPairImage $pair): void {
            if ($pair->picture_path !== null && $pair->picture_path !== '') {
                Storage::disk($pair->storageDisk())->delete($pair->picture_path);
            }
        });
    }

    public function player1(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_1_user_id', 'id_user');
    }

    public function player2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_2_user_id', 'id_user');
    }

    public function sourcePlayer1ProfileImage(): BelongsTo
    {
        return $this->belongsTo(PlayerProfileImage::class, 'source_player_1_profile_image_id');
    }

    public function sourcePlayer2ProfileImage(): BelongsTo
    {
        return $this->belongsTo(PlayerProfileImage::class, 'source_player_2_profile_image_id');
    }

    public function storageDisk(): string
    {
        return config('filesystems.user_uploads_disk', 'public');
    }

    public function getUrlAttribute(): ?string
    {
        if ($this->picture_path === null || $this->picture_path === '') {
            return null;
        }

        return Storage::disk($this->storageDisk())->url($this->picture_path);
    }
}
