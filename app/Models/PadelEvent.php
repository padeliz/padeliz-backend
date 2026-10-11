<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\EventCalendarTiming;
use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @OA\Schema(
 *     schema="Event",
 *     required={"name", "event_starts_at", "event_ends_at", "location_id", "description", "registration_allowed_until", "cancellation_allowed_until"},
 *     @OA\Property(
 *         property="id_event",
 *         type="integer",
 *         format="int64",
 *         readOnly=true,
 *         title="ID",
 *         description="Unique Identifier"
 *     ),
 *     @OA\Property(
 *         property="location_id",
 *         type="integer",
 *         format="int64",
 *         minimum=1,
 *         title="Location",
 *         description="Location of the edition"
 *     ),
 *     @OA\Property(
 *         property="organizer_id",
 *         type="integer",
 *         format="int64",
 *         minimum=1,
 *         nullable=true,
 *         title="Organizer",
 *         description="Organizer whose logo brands the edition posters"
 *     ),
 *     @OA\Property(
 *         property="include_club_host_on_pdf",
 *         type="boolean",
 *         title="Include Club Host On PDF",
 *         description="Whether posters show the hosting club logo",
 *         example=true
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         maxLength=25,
 *         title="Name",
 *         description="PadelEvent name",
 *         example="Summer 2024"
 *     ),
 *     @OA\Property(
 *         property="slug",
 *         type="string",
 *         nullable=true,
 *         readOnly=true,
 *         title="Slug",
 *         description="Server-generated URL slug from organizer and event names"
 *     ),
 *     @OA\Property(
 *         property="description",
 *         type="string",
 *         title="Description",
 *         description="Rich HTML description of the edition",
 *         example="<p>Update this description.</p>",
 *         x={"padeliz-richtext": true}
 *     ),
 *     @OA\Property(
 *         property="cover_url",
 *         type="string",
 *         nullable=true,
 *         readOnly=true,
 *         title="Cover URL",
 *         description="Public URL for the primary edition cover image (lowest sort_order)",
 *         x={
 *             "padeliz-image": {"role": "list"}
 *         }
 *     ),
 *     @OA\Property(
 *         property="cover_images",
 *         type="array",
 *         readOnly=true,
 *         title="Cover Images",
 *         description="Edition cover images (max 2)",
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="id", type="integer"),
 *             @OA\Property(property="url", type="string"),
 *             @OA\Property(property="aspect_ratio", type="number", format="float"),
 *             @OA\Property(property="sort_order", type="integer")
 *         ),
 *         x={
 *             "padeliz-upload": {
 *                 "mode": "multi",
 *                 "max_files": 2,
 *                 "max_size_kb": 5120,
 *                 "form_field": "file",
 *                 "required": true,
 *                 "accept": {"0":"image/jpeg","1":"image/png","2":"image/gif","3":"image/webp"},
 *                 "upload": {"method":"POST","path":"/api/v1/manager/events/{id}/cover-images"},
 *                 "delete": {"method":"DELETE","path":"/api/v1/manager/events/{id}/cover-images/{childId}"}
 *             }
 *         }
 *     ),
 *     @OA\Property(
 *         property="event_starts_at",
 *         type="integer",
 *         format="timestamp",
 *         title="Event Starts At",
 *         description="Unix timestamp when the edition starts"
 *     ),
 *     @OA\Property(
 *         property="event_ends_at",
 *         type="integer",
 *         format="timestamp",
 *         title="Event Ends At",
 *         description="Unix timestamp when the edition ends"
 *     ),
 *     @OA\Property(
 *         property="event_nr_of_days",
 *         type="integer",
 *         readOnly=true,
 *         title="Event Number Of Days",
 *         description="Inclusive Bucharest calendar days from event_starts_at to event_ends_at"
 *     ),
 *     @OA\Property(
 *         property="finalized_at",
 *         type="integer",
 *         format="timestamp",
 *         nullable=true,
 *         title="Finalized At",
 *         description="Unix timestamp when the edition was finalized"
 *     ),
 *     @OA\Property(
 *         property="registration_allowed_until",
 *         type="integer",
 *         format="timestamp",
 *         title="Registration Allowed Until",
 *         description="Unix timestamp until which players may register for this edition"
 *     ),
 *     @OA\Property(
 *         property="cancellation_allowed_until",
 *         type="integer",
 *         format="timestamp",
 *         title="Cancellation Allowed Until",
 *         description="Unix timestamp until which players may self-cancel their registration"
 *     ),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true, title="Created At", description="Timestamp when the edition was created"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true, title="Updated At", description="Timestamp when the edition was last updated"),
 *     @OA\Property(
 *         property="location",
 *         ref="#/components/schemas/Location",
 *         title="Location",
 *         description="Related location object",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="organizer",
 *         ref="#/components/schemas/Organizer",
 *         title="Organizer",
 *         description="Related organizer object",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="divisions",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/EventDivision"),
 *         title="Divisions",
 *         description="Array of related edition divisions",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="participations",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/EventParticipation"),
 *         title="Participations",
 *         description="Array of related participations",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="lotteries",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/EventLottery"),
 *         title="Lotteries",
 *         description="Array of related edition lotteries",
 *         nullable=true
 *     )
 * )
 *
 * @property-read null|string $cover_url
 * @property-read int $event_nr_of_days
 */
class PadelEvent extends Model
{
    use HasFactory;
    use HasResourceMetadata;

    public static array $searchableFields = [
        'name',
    ];

    public static array $filterableFields = [
        'location_id',
        'organizer_id',
    ];

    public static array $sortableFields = [
        'id_event',
        'name',
        'event_starts_at',
        'event_ends_at',
        'location_id',
        'organizer_id',
        'finalized_at',
        'registration_allowed_until',
        'cancellation_allowed_until',
        'inserted_at',
        'updated_at',
    ];

    public static ?string $displayField = 'name';

    public static array $listFields = [
        'cover_url',
        'cover_images',
        'event_nr_of_days',
    ];

    protected $table = 'events';

    protected $primaryKey = 'id_event';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'name',
        'description',
        'event_starts_at',
        'event_ends_at',
        'location_id',
        'organizer_id',
        'include_club_host_on_pdf',
        'finalized_at',
        'registration_allowed_until',
        'cancellation_allowed_until',
    ];

    protected $casts = [
        'event_starts_at' => 'integer',
        'event_ends_at' => 'integer',
        'finalized_at' => 'integer',
        'registration_allowed_until' => 'integer',
        'cancellation_allowed_until' => 'integer',
        'include_club_host_on_pdf' => 'boolean',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    protected $appends = [
        'cover_url',
        'event_nr_of_days',
    ];

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function organizer()
    {
        return $this->belongsTo(Organizer::class, 'organizer_id', 'id_organizer');
    }

    public function divisions()
    {
        return $this->hasMany(EventDivision::class, 'event_id');
    }

    public function coverImages(): HasMany
    {
        return $this->hasMany(EventCoverImage::class, 'event_id', 'id_event')
            ->orderBy('sort_order')
            ->orderBy('id_event_cover_image');
    }

    public function participations()
    {
        return $this->hasMany(EventParticipation::class, 'event_id');
    }

    public function registrations()
    {
        return $this->hasMany(EventRegistration::class, 'event_id');
    }

    public function lotteries()
    {
        return $this->hasMany(EventLottery::class, 'event_id');
    }

    public function storageDisk(): string
    {
        return (string) config('filesystems.user_uploads_disk', 'public');
    }

    public function primaryCoverImage(): ?EventCoverImage
    {
        if ($this->relationLoaded('coverImages')) {
            $first = $this->coverImages->sortBy([
                ['sort_order', 'asc'],
                ['id_event_cover_image', 'asc'],
            ])->first();

            return $first instanceof EventCoverImage ? $first : null;
        }

        $first = $this->coverImages()->first();

        return $first instanceof EventCoverImage ? $first : null;
    }

    public function getCoverUrlAttribute(): ?string
    {
        $primary = $this->primaryCoverImage();

        return $primary?->url;
    }

    public function getEventNrOfDaysAttribute(): int
    {
        return EventCalendarTiming::eventNrOfDays(
            (int) $this->event_starts_at,
            (int) $this->event_ends_at
        );
    }
}
