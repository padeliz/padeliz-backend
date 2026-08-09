<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @OA\Schema(
 *     schema="Player",
 *     required={"first_name"},
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
 *         readOnly=true,
 *         title="Name",
 *         description="Computed full name from first, middle, and last name"
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
 *         readOnly=true,
 *         title="Created At",
 *         description="Timestamp when the player was created"
 *     ),
 *     @OA\Property(
 *         property="updated_at",
 *         type="integer",
 *         format="timestamp",
 *         nullable=true,
 *         readOnly=true,
 *         title="Updated At",
 *         description="Timestamp when the player was last updated"
 *     ),
 *     @OA\Property(
 *         property="emails",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/PlayerEmail"),
 *         title="Emails",
 *         description="Player email addresses"
 *     ),
 *     @OA\Property(
 *         property="phones",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/PlayerPhone"),
 *         title="Phones",
 *         description="Player phone numbers"
 *     ),
 *     @OA\Property(
 *         property="uploaded_profile_images",
 *         type="array",
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="id", type="integer"),
 *             @OA\Property(property="url", type="string")
 *         ),
 *         title="Profile Images",
 *         description="Uploaded profile images (id and public URL)"
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
    use HasResourceMetadata;

    /**
     * Eager-load profile images wherever players are serialized (bounded, max 3 per player).
     *
     * @var list<string>
     */
    protected $with = [
        'profileImages',
        'emails',
        'phones',
    ];

    /**
     * Fields that can be searched
     */
    public static array $searchableFields = [
        'first_name',
        'last_name',
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
        'id_player',
        'skill_division',
        'year_of_birth',
        'being_student',
        'inserted_at',
        'updated_at',
    ];

    /**
     * Field that will be used to display the player in the UI
     */
    public static ?string $displayField = 'name';

    protected $table = 'players';

    protected $primaryKey = 'id_player';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';
    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
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
        'user_types',
        'remember_token',
    ];

    protected $hidden = [
        'remember_token',
    ];

    protected $appends = [
        'name',
    ];

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

    /**
     * Computed full name from first / middle / last parts.
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                return collect([$this->first_name, $this->middle_name, $this->last_name])
                    ->filter(static fn ($part) => $part !== null && $part !== '')
                    ->implode(' ');
            }
        );
    }

    public function emails(): HasMany
    {
        return $this->hasMany(PlayerEmail::class, 'player_id', 'id_player');
    }

    public function phones(): HasMany
    {
        return $this->hasMany(PlayerPhone::class, 'player_id', 'id_player');
    }

    public function identities(): HasMany
    {
        return $this->hasMany(PlayerIdentity::class, 'player_id', 'id_player');
    }

    public function invites(): HasMany
    {
        return $this->hasMany(PlayerInvite::class, 'player_id', 'id_player');
    }

    public function participations(): HasMany
    {
        return $this->hasMany(EditionParticipation::class, 'player_id');
    }

    public function profileImages(): HasMany
    {
        return $this->hasMany(PlayerProfileImage::class, 'player_id', 'id_player')
            ->orderBy('sort_order')
            ->orderBy('id');
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
