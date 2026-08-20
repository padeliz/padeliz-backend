<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Cached RANKING circular player avatar for PDF generation. Not Manager CRUD.
 *
 * @property-read null|string $url
 */
class CachedPdfPlayerImage extends Model
{
    protected $table = 'cached_pdf_player_images';

    protected $primaryKey = 'id_cached_pdf_player_image';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'player_id',
        'player_is_collecting_points',
        'picture_path',
        'source_player_profile_image_id',
    ];

    protected $casts = [
        'player_id' => 'integer',
        'player_is_collecting_points' => 'boolean',
        'source_player_profile_image_id' => 'integer',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    protected $appends = ['url'];

    protected static function booted(): void
    {
        static::deleting(function (CachedPdfPlayerImage $row): void {
            if ($row->picture_path !== null && $row->picture_path !== '') {
                Storage::disk($row->storageDisk())->delete($row->picture_path);
            }
        });
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id', 'id_player');
    }

    public function sourcePlayerProfileImage(): BelongsTo
    {
        return $this->belongsTo(PlayerProfileImage::class, 'source_player_profile_image_id');
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
