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
class PlayerProfileImage extends Model
{
    use HasFactory;

    public const MAX_PER_PLAYER = 3;

    public const UPLOADED_VIA_APP = 'app';

    public const UPLOADED_VIA_MANAGER = 'manager';

    public const UPLOADED_VIA_IMPORT = 'import';

    protected $table = 'player_profile_images';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'player_id',
        'profile_path',
        'sort_order',
        'uploaded_via',
        'uploaded_via_import_id',
        'original_filename',
        'is_pdf_primary',
    ];

    protected $casts = [
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
        'sort_order' => 'integer',
        'player_id' => 'integer',
        'uploaded_via_import_id' => 'integer',
        'is_pdf_primary' => 'boolean',
    ];

    protected $appends = ['url'];

    protected static function booted(): void
    {
        static::deleting(function (PlayerProfileImage $image): void {
            if ($image->profile_path !== null && $image->profile_path !== '') {
                Storage::disk($image->storageDisk())->delete($image->profile_path);
            }
        });
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id', 'id_player');
    }

    public function storageDisk(): string
    {
        return config('filesystems.user_uploads_disk', 'public');
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->storageDisk())->url($this->profile_path);
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'is_pdf_primary' => (bool) $this->is_pdf_primary,
            'uploaded_via' => $this->uploaded_via,
            'uploaded_via_import_id' => $this->uploaded_via_import_id,
            'original_filename' => $this->original_filename,
        ];
    }
}
