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
        'player_1_id',
        'player_2_id',
        'player_1_is_collecting_points',
        'player_2_is_collecting_points',
        'picture_path',
    ];

    protected $casts = [
        'player_1_id' => 'integer',
        'player_2_id' => 'integer',
        'player_1_is_collecting_points' => 'boolean',
        'player_2_is_collecting_points' => 'boolean',
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
        return $this->belongsTo(Player::class, 'player_1_id', 'id_player');
    }

    public function player2(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_2_id', 'id_player');
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
