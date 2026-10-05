<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Schema(
 *     schema="EventDivisionPoster",
 *     required={"poster_kind"},
 *     @OA\Property(property="id_event_division_poster", type="integer", format="int64", readOnly=true),
 *     @OA\Property(property="event_id", type="integer", format="int64", nullable=true),
 *     @OA\Property(property="event_division_id", type="integer", format="int64", nullable=true),
 *     @OA\Property(property="event_division_court_id", type="integer", format="int64", nullable=true),
 *     @OA\Property(property="poster_kind", type="string", enum={"MATCHES", "RANKING", "COMBINED", "BATTLES", "HOUSE", "SETUP", "ATTENDANCE"}),
 *     @OA\Property(property="generated_path", type="string", maxLength=512, nullable=true),
 *     @OA\Property(property="printed_path", type="string", maxLength=512, nullable=true, readOnly=true),
 *     @OA\Property(property="generated_url", type="string", nullable=true, readOnly=true),
 *     @OA\Property(
 *         property="printed_url",
 *         type="string",
 *         nullable=true,
 *         readOnly=true,
 *         x={
 *             "padeliz-upload": {
 *                 "mode": "single",
 *                 "max_files": 1,
 *                 "max_size_kb": 10240,
 *                 "form_field": "image",
 *                 "accept": {"0":"image/jpeg","1":"image/png","2":"image/gif","3":"image/webp"},
 *                 "upload": {"method":"POST","path":"/api/v1/manager/event-divisions/{eventDivision}/posters/{poster}/printed"}
 *             }
 *         }
 *     ),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true)
 * )
 *
 * @property-read null|string $generated_url
 * @property-read null|string $printed_url
 */
class EventDivisionPoster extends Model
{
    use HasFactory;

    protected $table = 'event_division_posters';

    protected $primaryKey = 'id_event_division_poster';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'event_id',
        'event_division_id',
        'event_division_court_id',
        'poster_kind',
        'generated_path',
        'printed_path',
    ];

    protected $casts = [
        'event_id' => 'integer',
        'event_division_id' => 'integer',
        'event_division_court_id' => 'integer',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    protected $appends = ['generated_url', 'printed_url'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(PadelEvent::class, 'event_id', 'id_event');
    }

    public function eventDivision(): BelongsTo
    {
        return $this->belongsTo(EventDivision::class, 'event_division_id', 'id_event_division');
    }

    public function eventDivisionCourt(): BelongsTo
    {
        return $this->belongsTo(EventDivisionCourt::class, 'event_division_court_id', 'id_event_division_court');
    }

    public function storageDisk(): string
    {
        return config('filesystems.user_uploads_disk', 'public');
    }

    public function getGeneratedUrlAttribute(): ?string
    {
        if ($this->generated_path === null || $this->generated_path === '') {
            return null;
        }

        return Storage::disk($this->storageDisk())->url($this->generated_path);
    }

    public function getPrintedUrlAttribute(): ?string
    {
        if ($this->printed_path === null || $this->printed_path === '') {
            return null;
        }

        return Storage::disk($this->storageDisk())->url($this->printed_path);
    }
}
