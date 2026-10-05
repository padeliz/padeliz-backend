<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Schema(
 *     schema="Organizer",
 *     required={"name"},
 *     @OA\Property(property="id_organizer", type="integer", format="int64", readOnly=true),
 *     @OA\Property(property="name", type="string", maxLength=60),
 *     @OA\Property(property="logo_path", type="string", maxLength=512, nullable=true, readOnly=true),
 *     @OA\Property(
 *         property="url",
 *         type="string",
 *         nullable=true,
 *         readOnly=true,
 *         x={
 *             "padeliz-upload": {
 *                 "mode": "single",
 *                 "max_files": 1,
 *                 "max_size_kb": 5120,
 *                 "form_field": "file",
 *                 "accept": {"0":"image/jpeg","1":"image/png","2":"image/gif","3":"image/webp"},
 *                 "upload": {"method":"POST","path":"/api/v1/manager/organizers/{id}/logo"},
 *                 "delete": {"method":"DELETE","path":"/api/v1/manager/organizers/{id}/logo"}
 *             },
 *             "padeliz-image": {"role": "list"}
 *         }
 *     ),
 *     @OA\Property(property="show_event_label", type="boolean"),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true)
 * )
 *
 * @property-read null|string $url
 */
class Organizer extends Model
{
    use HasFactory;
    use HasResourceMetadata;

    public static array $searchableFields = [
        'name',
    ];

    public static array $filterableFields = [
        'show_event_label',
    ];

    public static array $sortableFields = [
        'id_organizer',
        'name',
        'show_event_label',
        'inserted_at',
        'updated_at',
    ];

    public static ?string $displayField = 'name';

    public static array $listFields = [
        'url',
    ];

    protected $table = 'organizers';

    protected $primaryKey = 'id_organizer';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';
    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'name',
        'logo_path',
        'show_event_label',
    ];

    protected $casts = [
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
        'show_event_label' => 'boolean',
    ];

    protected $appends = ['url'];

    public function events(): HasMany
    {
        return $this->hasMany(PadelEvent::class, 'organizer_id', 'id_organizer');
    }

    public function storageDisk(): string
    {
        return config('filesystems.user_uploads_disk', 'public');
    }

    public function getUrlAttribute(): ?string
    {
        if ($this->logo_path === null || $this->logo_path === '') {
            return null;
        }

        return Storage::disk($this->storageDisk())->url($this->logo_path);
    }
}
