<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="EditionParticipation",
 *     required={"edition_id", "player_id", "division_id", "is_minor", "is_student", "payment"},
 *     @OA\Property(
 *         property="id_participation",
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
 *         description="Foreign key - References Player.id_player"
 *     ),
 *     @OA\Property(
 *         property="division_id",
 *         type="integer",
 *         format="int64",
 *         minimum=1,
 *         title="Division",
 *         description="Foreign key - References Division.id_division"
 *     ),
 *     @OA\Property(
 *         property="is_minor",
 *         type="boolean",
 *         title="Minor",
 *         description="Whether the participant is a minor"
 *     ),
 *     @OA\Property(
 *         property="is_student",
 *         type="boolean",
 *         title="Student",
 *         description="Whether the participant is a student"
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
 *         property="absence_reason",
 *         type="string",
 *         enum={"SICK", "FORGOT", "EXAMS", "RECONSIDERED"},
 *         nullable=true,
 *         title="Absence Reason",
 *         description="Reason for absence from the edition"
 *     ),
 *     @OA\Property(
 *         property="ban_reason",
 *         type="string",
 *         enum={"LEFT SUDDENLY", "AGGRESSIVE PLAYER", "LIBIDINOUS", ""},
 *         nullable=true,
 *         title="Ban Reason",
 *         description="Reason for banning the participant"
 *     ),
 *     @OA\Property(
 *         property="inserted_at",
 *         type="integer",
 *         format="timestamp",
 *         title="Created At",
 *         description="Timestamp when the participation was created"
 *     ),
 *     @OA\Property(
 *         property="updated_at",
 *         type="integer",
 *         format="timestamp",
 *         nullable=true,
 *         title="Updated At",
 *         description="Timestamp when the participation was last updated"
 *     ),
 *     @OA\Property(
 *         property="edition",
 *         ref="#/components/schemas/Edition",
 *         title="Edition",
 *         description="Related edition object (loaded when requested)",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="player",
 *         ref="#/components/schemas/Player",
 *         title="Player",
 *         description="Related player object (loaded when requested)",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="division",
 *         ref="#/components/schemas/Division",
 *         title="Division",
 *         description="Related division object (loaded when requested)",
 *         nullable=true
 *     )
 * )
 */
class EditionParticipation extends Model
{
    use HasFactory;
    use HasResourceMetadata;

    /**
     * Fields that can be searched
     */
    public static array $searchableFields = [
        'absence_reason',
        'ban_reason',
    ];

    /**
     * Fields that can be filtered
     */
    public static array $filterableFields = [
        'edition_id',
        'player_id',
        'division_id',
        'is_minor',
        'is_student',
        'discount',
        'payment',
    ];

    /**
     * Fields that can be sorted
     */
    public static array $sortableFields = [
        'edition_id',
        'player_id',
        'division_id',
        'is_minor',
        'is_student',
        'discount',
        'payment',
        'inserted_at',
        'updated_at',
    ];

    protected $table = 'edition_participations';

    protected $primaryKey = 'id_participation';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';
    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'edition_id',
        'player_id',
        'division_id',
        'is_minor',
        'is_student',
        'discount',
        'payment',
        'absence_reason',
        'ban_reason',
    ];

    protected $casts = [
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
        'is_minor' => 'boolean',
        'is_student' => 'boolean',
    ];

    public function edition()
    {
        return $this->belongsTo(Edition::class, 'edition_id');
    }

    public function player()
    {
        return $this->belongsTo(Player::class, 'player_id');
    }

    public function division()
    {
        return $this->belongsTo(Division::class, 'division_id');
    }
}
