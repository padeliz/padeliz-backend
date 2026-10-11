<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @OA\Schema(
 *     schema="MediaSource",
 *     required={"code", "label"},
 *     @OA\Property(property="id_media_source", type="integer", format="int64", readOnly=true, title="ID"),
 *     @OA\Property(property="code", type="string", maxLength=32, title="Code", example="instagram"),
 *     @OA\Property(property="label", type="string", maxLength=64, title="Label", example="Instagram"),
 *     @OA\Property(property="sort_order", type="integer", title="Sort Order"),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true, title="Created At"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true, title="Updated At")
 * )
 */
class MediaSource extends Model
{
    use HasFactory;
    use HasResourceMetadata;

    public const CODE_FRIENDS = 'friends';

    public static array $searchableFields = [
        'code',
        'label',
    ];

    public static array $filterableFields = [];

    public static array $sortableFields = [
        'id_media_source',
        'code',
        'label',
        'sort_order',
        'inserted_at',
        'updated_at',
    ];

    public static ?string $displayField = 'label';

    protected $table = 'media_sources';

    protected $primaryKey = 'id_media_source';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'code',
        'label',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function players(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'player_media_sources',
            'media_source_id',
            'player_user_id',
            'id_media_source',
            'id_user'
        )->withPivot(['id_player_media_source', 'inserted_at', 'updated_at']);
    }
}
