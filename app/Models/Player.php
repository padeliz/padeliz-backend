<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasResourceMetadata;
use App\Traits\HasUploadedFiles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @OA\Schema(
 *     schema="Player",
 *     required={"name"},
 *     @OA\Property(
 *         property="id_player",
 *         type="integer",
 *         format="int64",
 *         readOnly=true,
 *         title="ID",
 *         description="Unique Identifier"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         maxLength=250,
 *         title="Full Name",
 *         description="Player's full name"
 *     ),
 *     @OA\Property(
 *         property="google_id",
 *         type="string",
 *         title="Google ID",
 *         description="Google account identifier"
 *     ),
 *     @OA\Property(
 *         property="google_name",
 *         type="string",
 *         title="Google Name",
 *         description="Name from Google account"
 *     ),
 *     @OA\Property(
 *         property="google_email",
 *         type="string",
 *         title="Google Email",
 *         description="Email from Google account"
 *     ),
 *     @OA\Property(
 *         property="google_avatar",
 *         type="string",
 *         title="Google Avatar",
 *         description="Avatar URL from Google account"
 *     ),
 *     @OA\Property(
 *         property="phone",
 *         type="string",
 *         maxLength=255,
 *         nullable=true,
 *         title="Phone",
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
 *         title="Skill Level",
 *         description="Skill level from 0.0 to 9.9",
 *         example=4.5
 *     ),
 *     @OA\Property(
 *         property="first_name",
 *         type="string",
 *         maxLength=50,
 *         nullable=true,
 *         title="First Name",
 *         description="Player's first name"
 *     ),
 *     @OA\Property(
 *         property="middle_name",
 *         type="string",
 *         maxLength=50,
 *         nullable=true,
 *         title="Middle Name",
 *         description="Player's middle name"
 *     ),
 *     @OA\Property(
 *         property="last_name",
 *         type="string",
 *         maxLength=50,
 *         nullable=true,
 *         title="Last Name",
 *         description="Player's last name"
 *     ),
 *     @OA\Property(
 *         property="year_of_birth",
 *         type="integer",
 *         nullable=true,
 *         minimum=1900,
 *         maximum=9999,
 *         title="Birth Year",
 *         description="Year of birth"
 *     ),
 *     @OA\Property(
 *         property="being_student",
 *         type="boolean",
 *         default=false,
 *         title="Student",
 *         description="Whether the player is a student"
 *     ),
 *     @OA\Property(
 *         property="playtomic_id",
 *         type="string",
 *         maxLength=10,
 *         nullable=true,
 *         title="Playtomic ID",
 *         description="Playtomic platform identifier"
 *     ),
 *     @OA\Property(
 *         property="rankedin_id",
 *         type="string",
 *         maxLength=15,
 *         nullable=true,
 *         title="Rankedin ID",
 *         description="Rankedin platform identifier"
 *     ),
 *     @OA\Property(
 *         property="user_types",
 *         type="array",
 *         nullable=true,
 *         @OA\Items(type="string", enum={"player", "manager", "admin"}),
 *         title="User Types",
 *         description="Array of user roles",
 *         example={"player", "manager"}
 *     ),
 *     @OA\Property(
 *         property="instagram_username",
 *         type="string",
 *         maxLength=50,
 *         nullable=true,
 *         title="Instagram",
 *         description="Instagram username"
 *     ),
 *     @OA\Property(
 *         property="facebook_username",
 *         type="string",
 *         maxLength=50,
 *         nullable=true,
 *         title="Facebook",
 *         description="Facebook username"
 *     ),
 *     @OA\Property(
 *         property="inserted_at",
 *         type="integer",
 *         format="timestamp",
 *         title="Created At",
 *         description="Timestamp when the player was created"
 *     ),
 *     @OA\Property(
 *         property="updated_at",
 *         type="integer",
 *         format="timestamp",
 *         nullable=true,
 *         title="Updated At",
 *         description="Timestamp when the player was last updated"
 *     ),
 *     @OA\Property(
 *         property="uploaded_profile_urls",
 *         type="array",
 *         @OA\Items(type="string"),
 *         title="Profile Images",
 *         description="Array of uploaded profile image URLs"
 *     ),
 *     @OA\Property(
 *         property="participations",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/EditionParticipation"),
 *         title="Participations",
 *         description="Array of edition participations"
 *     )
 * )
 */
class Player extends Authenticatable
{
    use HasFactory;
    use HasApiTokens;
    use HasUploadedFiles;
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
        'google_email',
        'playtomic_id',
        'rankedin_id',
    ];

    /**
     * Fields that can be filtered
     */
    public static array $filterableFields = [
        'skill_division',
        'year_of_birth',
        'being_student',
        'user_types',
    ];

    /**
     * Fields that can be sorted
     */
    public static array $sortableFields = [
        'name',
        'first_name',
        'last_name',
        'skill_division',
        'year_of_birth',
        'being_student',
        'inserted_at',
        'updated_at',
    ];

    protected $table = 'players';

    protected $primaryKey = 'id_player';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';
    public const UPDATED_AT = 'updated_at';

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
        'rankedin_id',
        'user_types',
    ];

    protected $hidden = [
        'google_id',
        'remember_token',
    ];

    protected $appends = ['uploaded_profile_urls'];

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
        'user_types' => 'array',
    ];

    public function participations()
    {
        return $this->hasMany(EditionParticipation::class, 'player_id');
    }

    public function getUploadedProfileUrlsAttribute(): array
    {
        return $this->getUploadedUrls('images');
    }

    /**
     * Check if the player has a specific user type
     */
    public function hasUserType(string $userType): bool
    {
        return in_array($userType, $this->user_types ?? []);
    }

    /**
     * Check if the player is a manager
     */
    public function isManager(): bool
    {
        return $this->hasUserType('manager');
    }

    /**
     * Check if the player is an admin
     */
    public function isAdmin(): bool
    {
        return $this->hasUserType('admin');
    }

    /**
     * Check if the player is a regular player
     */
    public function isPlayer(): bool
    {
        return $this->hasUserType('player');
    }

    /**
     * Add a user type to the player
     */
    public function addUserType(string $userType): void
    {
        $userTypes = $this->user_types ?? [];
        if (! in_array($userType, $userTypes)) {
            $userTypes[] = $userType;
            $this->user_types = $userTypes;
            $this->save();
        }
    }

    /**
     * Remove a user type from the player
     */
    public function removeUserType(string $userType): void
    {
        $userTypes = $this->user_types ?? [];
        $userTypes = array_filter($userTypes, fn ($type) => $type !== $userType);
        $this->user_types = array_values($userTypes);
        $this->save();
    }
}
