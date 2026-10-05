<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Schema(
 *     schema="Sponsor",
 *     required={"name"},
 *     @OA\Property(property="id_sponsor", type="integer", format="int64", readOnly=true, title="ID"),
 *     @OA\Property(property="name", type="string", maxLength=60, title="Name"),
 *     @OA\Property(property="club_id", type="integer", format="int64", nullable=true, minimum=1, title="Club"),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true, title="Created At"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true, title="Updated At"),
 *     @OA\Property(property="club", ref="#/components/schemas/Club", nullable=true, title="Club"),
 *     @OA\Property(property="player_ids", type="array", @OA\Items(type="integer", format="int64", minimum=1), title="Players", description="Full-replace list of player IDs"),
 *     @OA\Property(property="players", type="array", @OA\Items(ref="#/components/schemas/Player"), nullable=true, title="Players"),
 *     @OA\Property(property="sponsorPrizes", type="array", @OA\Items(ref="#/components/schemas/SponsorPrize"), nullable=true, title="Sponsor Prizes")
 * )
 */
class Sponsor extends Model
{
    use HasFactory;
    use HasResourceMetadata;

    public static array $searchableFields = [
        'name',
    ];

    public static array $filterableFields = [
        'club_id',
    ];

    public static array $sortableFields = [
        'id_sponsor',
        'name',
        'club_id',
        'inserted_at',
        'updated_at',
    ];

    public static ?string $displayField = 'name';

    protected $table = 'sponsors';

    protected $primaryKey = 'id_sponsor';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'name',
        'club_id',
    ];

    protected $casts = [
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class, 'club_id', 'id_club');
    }

    public function players(): BelongsToMany
    {
        return $this->belongsToMany(
            Player::class,
            'sponsor_players',
            'sponsor_id',
            'player_id',
            'id_sponsor',
            'id_player'
        )->withPivot(['id_sponsor_player', 'inserted_at', 'updated_at']);
    }

    public function sponsorPrizes(): HasMany
    {
        return $this->hasMany(SponsorPrize::class, 'sponsor_id', 'id_sponsor');
    }

    public function lotteryPrizes(): HasMany
    {
        return $this->hasMany(EventLotteryPrize::class, 'sponsor_id', 'id_sponsor');
    }

    /**
     * @param  list<int|string>  $playerIds
     */
    public function syncPlayerIds(array $playerIds): void
    {
        $now = time();
        $desired = array_values(array_unique(array_filter(
            array_map('intval', $playerIds),
            static fn (int $id): bool => $id > 0
        )));

        $query = DB::table('sponsor_players')->where('sponsor_id', $this->id_sponsor);
        if ($desired === []) {
            $query->delete();

            return;
        }

        $query->whereNotIn('player_id', $desired)->delete();

        $existing = DB::table('sponsor_players')
            ->where('sponsor_id', $this->id_sponsor)
            ->pluck('player_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        foreach ($desired as $playerId) {
            if (in_array($playerId, $existing, true)) {
                continue;
            }

            DB::table('sponsor_players')->insert([
                'sponsor_id' => $this->id_sponsor,
                'player_id' => $playerId,
                'inserted_at' => $now,
                'updated_at' => null,
            ]);
        }
    }
}
