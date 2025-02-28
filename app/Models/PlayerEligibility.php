<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="PlayerEligibility",
 *     @OA\Property(property="id_eligibility", type="integer", format="int64"),
 *     @OA\Property(property="edition_id", type="integer", format="int64"),
 *     @OA\Property(property="player_id", type="integer", format="int64"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="phone", type="string", nullable=true),
 *     @OA\Property(property="skill_division", type="number", format="float", nullable=true),
 *     @OA\Property(property="first_name", type="string", nullable=true),
 *     @OA\Property(property="middle_name", type="string", nullable=true),
 *     @OA\Property(property="last_name", type="string", nullable=true),
 *     @OA\Property(property="year_of_birth", type="integer", nullable=true),
 *     @OA\Property(property="being_student", type="boolean"),
 *     @OA\Property(property="playtomic_id", type="string", nullable=true),
 *     @OA\Property(property="rankedin_id", type="string", nullable=true),
 *     @OA\Property(property="instagram_username", type="string", nullable=true),
 *     @OA\Property(property="facebook_username", type="string", nullable=true),
 *     @OA\Property(property="reviewed_at", type="integer", format="timestamp", nullable=true),
 *     @OA\Property(property="review_status", type="string", enum={"pending", "approved", "rejected"}),
 *     @OA\Property(property="review_message", type="string", nullable=true),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp"),
 *     @OA\Property(property="resubmitted_at", type="integer", format="timestamp", nullable=true)
 * )
 */
class PlayerEligibility extends Model
{
    use HasFactory;

    protected $table = 'player_eligibilities';
    protected $primaryKey = 'id_eligibility';
    public $timestamps = true;
    protected $dateFormat = 'U';

    const CREATED_AT = 'inserted_at';
    const UPDATED_AT = 'updated_at';

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
        'resubmitted_at'
    ];

    protected $casts = [
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
        'reviewed_at' => 'integer',
        'being_student' => 'boolean',
        'year_of_birth' => 'integer',
        'skill_division' => 'float',
        'resubmitted_at' => 'integer'
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
