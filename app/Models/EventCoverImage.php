<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property-read string $url
 */
class EventCoverImage extends Model
{
    use HasFactory;

    public const MAX_PER_EVENT = 2;

    protected $table = 'event_cover_images';

    protected $primaryKey = 'id_event_cover_image';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'event_id',
        'cover_path',
        'aspect_ratio',
        'sort_order',
    ];

    protected $casts = [
        'event_id' => 'integer',
        'aspect_ratio' => 'float',
        'sort_order' => 'integer',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    protected $appends = ['url'];

    protected static function booted(): void
    {
        static::deleting(function (EventCoverImage $image): void {
            if ($image->cover_path !== null && $image->cover_path !== '') {
                Storage::disk($image->storageDisk())->delete($image->cover_path);
            }
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(PadelEvent::class, 'event_id', 'id_event');
    }

    public function storageDisk(): string
    {
        return (string) config('filesystems.user_uploads_disk', 'public');
    }

    public function getUrlAttribute(): string
    {
        $url = Storage::disk($this->storageDisk())->url($this->cover_path);
        if (preg_match('#^https?://#i', $url) === 1) {
            return $url;
        }

        return url($url);
    }

    /**
     * @return array{id: int, url: string, aspect_ratio: float, sort_order: int}
     */
    public function toApiArray(): array
    {
        return [
            'id' => (int) $this->id_event_cover_image,
            'url' => $this->url,
            'aspect_ratio' => (float) $this->aspect_ratio,
            'sort_order' => (int) $this->sort_order,
        ];
    }
}
