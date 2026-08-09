<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @OA\Schema(
 *     schema="EditionDivisionPoster",
 *     required={"edition_division_id", "poster_kind", "filename"},
 *     @OA\Property(property="id_edition_division_poster", type="integer", format="int64", readOnly=true),
 *     @OA\Property(property="edition_division_id", type="integer", format="int64"),
 *     @OA\Property(property="poster_kind", type="string", enum={"MATCHES", "RANKING", "COMBINED", "BATTLES", "HOUSE", "SETUP"}),
 *     @OA\Property(property="filename", type="string", maxLength=255),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true)
 * )
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
        'poster_kind',
        'filename',
    ];

    protected $casts = [
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function editionDivision(): BelongsTo
    {
        return $this->belongsTo(EditionDivision::class, 'edition_division_id', 'id_edition_division');
    }
}
