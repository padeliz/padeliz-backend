<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\HasUploadedFiles;

/**
 * @OA\Schema(
 *     schema="Player",
 *     required={"name", "phone"},
 *     @OA\Property(property="id_player", type="integer", format="int64"),
 *     @OA\Property(property="name", type="string", maxLength=255),
 *     @OA\Property(property="phone", type="string", maxLength=255),
 *     @OA\Property(property="google_id", type="string"),
 *     @OA\Property(property="google_name", type="string"),
 *     @OA\Property(property="google_email", type="string"),
 *     @OA\Property(property="google_avatar", type="string", nullable=true),
 *     @OA\Property(property="skill_division", type="number", format="float", nullable=true, maximum=9.9),
 *     @OA\Property(property="first_name", type="string", maxLength=50, nullable=true),
 *     @OA\Property(property="middle_name", type="string", maxLength=50, nullable=true),
 *     @OA\Property(property="last_name", type="string", maxLength=50, nullable=true),
 *     @OA\Property(property="year_of_birth", type="integer", nullable=true, minimum=1900, maximum=9999),
 *     @OA\Property(property="being_student", type="boolean", default=false),
 *     @OA\Property(property="playtomic_id", type="string", maxLength=10, nullable=true),
 *     @OA\Property(property="rankedin_id", type="string", maxLength=15, nullable=true),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp"),
 *     @OA\Property(property="uploaded_profile_url", type="string", nullable=true),
 *     @OA\Property(
 *         property="participations",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/EditionParticipation")
 *     )
 * )
 */
class Player extends Authenticatable
{
    use HasFactory, HasApiTokens, HasUploadedFiles;

    protected $table = 'players';
    protected $primaryKey = 'id_player';
    public $timestamps = true;

    protected $dateFormat = 'U';

    const CREATED_AT = 'inserted_at';
    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'name',
        'phone',
        'google_id',
        'google_name',
        'google_email',
        'google_avatar',
        'skill_division',
        'first_name',
        'middle_name',
        'last_name',
        'year_of_birth',
        'being_student',
        'playtomic_id',
        'rankedin_id'
    ];

    protected $hidden = [
        'google_id',
        'remember_token'
    ];

    protected $appends = ['uploaded_profile_url'];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
        'being_student' => 'boolean',
        'year_of_birth' => 'integer',
        'skill_division' => 'float',
    ];

    public function participations()
    {
        return $this->hasMany(EditionParticipation::class, 'player_id');
    }

    public function getUploadedProfileUrlAttribute(): ?string
    {
        return $this->getFileUrl('avatar');
    }
}
