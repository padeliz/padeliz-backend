<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @OA\Schema(
 *     schema="EditionRegistration",
 *     required={"edition_id", "player_id", "is_minor", "is_student", "payment"},
 *     @OA\Property(
 *         property="id_registration",
 *         type="integer",
 *         format="int64",
 *         readOnly=true,
 *         title="ID",
 *         description="Unique Identifier"
 *     ),
 *     @OA\Property(
 *         property="edition_id",
 *         type="integer",
 *         format="int64",
 *         minimum=1,
 *         title="Edition",
 *         description="Foreign key - References Edition.id_edition"
 *     ),
 *     @OA\Property(
 *         property="player_id",
 *         type="integer",
 *         format="int64",
 *         minimum=1,
 *         title="Player",
 *         description="Player of the registration"
 *     ),
 *     @OA\Property(
 *         property="edition_division_id",
 *         type="integer",
 *         format="int64",
 *         nullable=true,
 *         minimum=1,
 *         title="Edition Division",
 *         description="Optional edition division of the registration"
 *     ),
 *     @OA\Property(
 *         property="is_minor",
 *         type="boolean",
 *         title="Minor",
 *         description="Whether the registrant is a minor"
 *     ),
 *     @OA\Property(
 *         property="is_student",
 *         type="boolean",
 *         title="Student",
 *         description="Whether the registrant is a student"
 *     ),
 *     @OA\Property(
 *         property="discount",
 *         type="integer",
 *         nullable=true,
 *         minimum=0,
 *         title="Discount",
 *         description="Discount amount in RON",
 *         example=10
 *     ),
 *     @OA\Property(
 *         property="payment",
 *         type="integer",
 *         minimum=0,
 *         title="Payment",
 *         description="Payment amount in RON",
 *         example=45
 *     ),
 *     @OA\Property(
 *         property="inserted_at",
 *         type="integer",
 *         format="timestamp",
 *         readOnly=true,
 *         title="Created At",
 *         description="Timestamp when the registration was created"
 *     ),
 *     @OA\Property(
 *         property="updated_at",
 *         type="integer",
 *         format="timestamp",
 *         nullable=true,
 *         readOnly=true,
 *         title="Updated At",
 *         description="Timestamp when the registration was last updated"
 *     ),
 *     @OA\Property(
 *         property="edition",
 *         ref="#/components/schemas/Edition",
 *         title="Edition",
 *         description="Related edition object",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="player",
 *         ref="#/components/schemas/Player",
 *         title="Player",
 *         description="Related player object",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="editionDivision",
 *         ref="#/components/schemas/EditionDivision",
 *         title="Edition Division",
 *         description="Related edition division object",
 *         nullable=true
 *     )
 * )
 */
class EditionRegistration extends Model
{
    use HasFactory;
    use HasResourceMetadata;

    public static array $searchableFields = [];

    public static array $filterableFields = [
        'edition_id',
        'player_id',
        'edition_division_id',
        'is_minor',
        'is_student',
        'discount',
        'payment',
    ];

    public static array $sortableFields = [
        'id_registration',
        'edition_id',
        'player_id',
        'edition_division_id',
        'is_minor',
        'is_student',
        'discount',
        'payment',
        'inserted_at',
        'updated_at',
    ];

    public static ?string $displayField = null;

    protected $table = 'edition_registrations';

    protected $primaryKey = 'id_registration';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';
    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'edition_id',
        'player_id',
        'edition_division_id',
        'is_minor',
        'is_student',
        'discount',
        'payment',
    ];

    protected $casts = [
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
        'is_minor' => 'boolean',
        'is_student' => 'boolean',
    ];

    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class, 'edition_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id');
    }

    public function editionDivision(): BelongsTo
    {
        return $this->belongsTo(EditionDivision::class, 'edition_division_id', 'id_edition_division');
    }

    /**
     * XOR uniqueness: either one NULL edition_division_id row, or one or more
     * specific edition divisions, not both, per (edition_id, player_id).
     *
     * @return array<string, array<int, string>>|null
     */
    public static function xorConflict(
        int $editionId,
        int $playerId,
        ?int $editionDivisionId,
        ?int $ignoreRegistrationId = null
    ): ?array {
        $query = static::query()
            ->where('edition_id', $editionId)
            ->where('player_id', $playerId);

        if ($ignoreRegistrationId !== null) {
            $query->where('id_registration', '!=', $ignoreRegistrationId);
        }

        $others = $query->get(['id_registration', 'edition_division_id']);

        if ($editionDivisionId === null) {
            if ($others->isNotEmpty()) {
                return [
                    'edition_division_id' => [
                        'A registration without an edition division cannot be combined with other registrations for this edition.',
                    ],
                ];
            }

            return null;
        }

        if ($others->contains(fn (self $row): bool => $row->edition_division_id === null)) {
            return [
                'edition_division_id' => [
                    'Cannot register for an edition division while an edition-wide registration exists.',
                ],
            ];
        }

        if ($others->contains(fn (self $row): bool => (int) $row->edition_division_id === $editionDivisionId)) {
            return [
                'edition_division_id' => [
                    'This player is already registered for this edition division.',
                ],
            ];
        }

        return null;
    }
}
