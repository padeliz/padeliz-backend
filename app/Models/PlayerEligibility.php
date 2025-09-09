<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="PlayerEligibility",
 *     required={"edition_id", "player_id", "name", "being_student", "review_status"},
 *     @OA\Property(
 *         property="id_eligibility",
 *         type="integer",
 *         format="int64",
 *         readOnly=true,
 *         title="ID",
         description="Unique Identifier"
 *     ),
 *     @OA\Property(
 *         property="edition_id",
 *         type="integer",
 *         format="int64",
 *         minimum=1,
 *         title="Edition"
 *     ),
 *     @OA\Property(
 *         property="player_id",
 *         type="integer",
 *         format="int64",
 *         minimum=1,
 *         title="Player"
 *     ),
 *     @OA\Property(property="name", type="string", maxLength=255),
 *     @OA\Property(
 *         property="phone",
 *         type="string",
 *         maxLength=255,
 *         nullable=true,
 *         description="Phone number",
 *         example="+40712345678"
 *     ),
 *     @OA\Property(
 *         property="skill_division",
 *         type="number",
 *         format="float",
 *         nullable=true,
 *         minimum=0.0,
 *         maximum=9.9,
 *         description="Skill level from 0.0 to 9.9",
 *         example=4.5
 *     ),
 *     @OA\Property(property="first_name", type="string", maxLength=50, nullable=true),
 *     @OA\Property(property="middle_name", type="string", maxLength=50, nullable=true),
 *     @OA\Property(property="last_name", type="string", maxLength=50, nullable=true),
 *     @OA\Property(
 *         property="year_of_birth",
 *         type="integer",
 *         nullable=true,
 *         minimum=1900,
 *         maximum=9999,
 *         description="Year of birth",
 *         example=1990
 *     ),
 *     @OA\Property(property="being_student", type="boolean"),
 *     @OA\Property(property="playtomic_id", type="string", maxLength=10, nullable=true),
 *     @OA\Property(property="rankedin_id", type="string", maxLength=15, nullable=true),
 *     @OA\Property(property="instagram_username", type="string", maxLength=50, nullable=true),
 *     @OA\Property(property="facebook_username", type="string", maxLength=50, nullable=true),
 *     @OA\Property(
 *         property="reviewed_at",
 *         type="integer",
 *         format="timestamp",
 *         nullable=true,
 *         minimum=0,
 *         description="Unix timestamp when reviewed",
 *         example=1704067200
 *     ),
 *     @OA\Property(property="review_status", type="string", enum={"pending", "approved", "rejected"}),
 *     @OA\Property(property="review_message", type="string", nullable=true),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp"),
 *     @OA\Property(
 *         property="resubmitted_at",
 *         type="integer",
 *         format="timestamp",
 *         nullable=true,
 *         minimum=0,
 *         description="Unix timestamp when resubmitted",
 *         example=1704153600
 *     ),
 *     @OA\Property(
 *         property="player",
 *         ref="#/components/schemas/Player",
 *         description="Related player object (loaded when requested)",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="edition",
 *         ref="#/components/schemas/Edition",
 *         description="Related edition object (loaded when requested)",
 *         nullable=true
 *     )
 * )
 */
class PlayerEligibility extends Model
{
    use HasFactory;
    use HasResourceMetadata;

    /**
     * Fields that can be searched
     */
    public static array $searchableFields = [
        'name',
        'first_name',
        'middle_name',
        'last_name',
        'phone',
        'playtomic_id',
        'rankedin_id',
        'instagram_username',
        'facebook_username',
        'review_message',
    ];

    /**
     * Fields that can be filtered
     */
    public static array $filterableFields = [
        'edition_id',
        'player_id',
        'skill_division',
        'year_of_birth',
        'being_student',
        'review_status',
    ];

    /**
     * Fields that can be sorted
     */
    public static array $sortableFields = [
        'edition_id',
        'player_id',
        'name',
        'first_name',
        'last_name',
        'skill_division',
        'year_of_birth',
        'being_student',
        'review_status',
        'reviewed_at',
        'inserted_at',
        'updated_at',
        'resubmitted_at',
    ];

    protected $table = 'player_eligibilities';

    protected $primaryKey = 'id_eligibility';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';
    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'edition_id',
        'player_id',
        'name',
        'phone',
        'skill_division',
        'first_name',
        'middle_name',
        'last_name',
        'year_of_birth',
        'being_student',
        'playtomic_id',
        'rankedin_id',
        'instagram_username',
        'facebook_username',
        'reviewed_at',
        'review_status',
        'review_message',
        'resubmitted_at',
    ];

    protected $casts = [
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
        'reviewed_at' => 'integer',
        'being_student' => 'boolean',
        'year_of_birth' => 'integer',
        'skill_division' => 'float',
        'resubmitted_at' => 'integer',
    ];

    public function player()
    {
        return $this->belongsTo(Player::class, 'player_id', 'id_player');
    }

    public function edition()
    {
        return $this->belongsTo(Edition::class, 'edition_id', 'id_edition');
    }
}
