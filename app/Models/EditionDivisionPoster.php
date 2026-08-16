<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Schema(
 *     schema="EditionDivisionPoster",
 *     required={"edition_division_id", "poster_kind"},
 *     @OA\Property(property="id_edition_division_poster", type="integer", format="int64", readOnly=true),
 *     @OA\Property(property="edition_division_id", type="integer", format="int64"),
 *     @OA\Property(property="edition_division_court_id", type="integer", format="int64", nullable=true),
 *     @OA\Property(property="poster_kind", type="string", enum={"MATCHES", "RANKING", "COMBINED", "BATTLES", "HOUSE", "SETUP"}),
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
 *                 "upload": {"method":"POST","path":"/api/v1/manager/edition-divisions/{editionDivision}/posters/{poster}/printed"}
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
class EditionDivisionPoster extends Model
{
    use HasFactory;

    protected $table = 'edition_division_posters';

    protected $primaryKey = 'id_edition_division_poster';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'edition_division_id',
        'edition_division_court_id',
        'poster_kind',
        'generated_path',
        'printed_path',
    ];

    protected $casts = [
        'edition_division_id' => 'integer',
        'edition_division_court_id' => 'integer',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    protected $appends = ['generated_url', 'printed_url'];

    public function editionDivision(): BelongsTo
    {
        return $this->belongsTo(EditionDivision::class, 'edition_division_id', 'id_edition_division');
    }

    public function editionDivisionCourt(): BelongsTo
    {
        return $this->belongsTo(EditionDivisionCourt::class, 'edition_division_court_id', 'id_edition_division_court');
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
