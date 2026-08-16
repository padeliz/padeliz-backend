<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

/**
 * @property-read string $url
 */
class EligibilityPortraitPhoto extends Model
{
    use HasFactory;

    public const MAX_PER_ELIGIBILITY = 3;

    public const MAX_FILE_KILOBYTES = 5120;

    protected $table = 'eligibility_portrait_photos';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'eligibility_id',
        'portrait_photo_path',
        'sort_order',
    ];

    protected $casts = [
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
        'sort_order' => 'integer',
        'eligibility_id' => 'integer',
    ];

    protected $appends = ['url'];

    protected static function booted(): void
    {
        static::deleting(function (EligibilityPortraitPhoto $row): void {
            if ($row->portrait_photo_path !== null && $row->portrait_photo_path !== '') {
                Storage::disk($row->storageDisk())->delete($row->portrait_photo_path);
            }
        });
    }

    public function eligibility(): BelongsTo
    {
        return $this->belongsTo(Eligibility::class, 'eligibility_id', 'id_eligibility');
    }

    public function storageDisk(): string
    {
        return config('filesystems.user_uploads_disk', 'public');
    }

    public function getUrlAttribute(): string
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($this->storageDisk());

        return $disk->url($this->portrait_photo_path);
    }
}
