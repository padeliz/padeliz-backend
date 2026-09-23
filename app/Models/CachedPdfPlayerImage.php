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

    /**
     * Read-only lookup of an already-cached circular avatar URL.
     */
    public static function urlForPlayer(int $playerId, bool $collecting): ?string
    {
        $cached = static::query()
            ->where('player_id', $playerId)
            ->where('player_is_collecting_points', $collecting)
            ->first();

        return $cached?->url;
    }

    /**
     * Batch lookup keyed by "{playerId}:{0|1}".
     *
     * @param  list<array{player_id: int, collecting: bool}>  $pairs
     * @return array<string, null|string>
     */
    public static function urlsByPlayerCollectingPairs(array $pairs): array
    {
        if ($pairs === []) {
            return [];
        }

        $playerIds = array_values(array_unique(array_map(
            static fn (array $row): int => (int) $row['player_id'],
            $pairs
        )));

        $images = static::query()
            ->whereIn('player_id', $playerIds)
            ->get();

        $byKey = [];
        foreach ($images as $image) {
            $flag = $image->player_is_collecting_points ? '1' : '0';
            $byKey[$image->player_id . ':' . $flag] = $image->url;
        }

        $result = [];
        foreach ($pairs as $pair) {
            $playerId = (int) $pair['player_id'];
            $key = $playerId . ':' . ($pair['collecting'] ? '1' : '0');
            $result[$key] = $byKey[$key] ?? null;
        }

        return $result;
    }

    /**
     * PlayerResource preference: collecting=true URL if present, else any cached URL.
     */
    public static function urlForPlayerPreferCollecting(int $playerId): ?string
    {
        $collecting = static::urlForPlayer($playerId, true);
        if ($collecting !== null) {
            return $collecting;
        }

        return static::urlForPlayer($playerId, false);
    }
}
