<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Schedule\PlayingWindow;
use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Schema(
 *     schema="Event",
 *     required={"name", "date", "location_id", "arriving_time", "closing_time", "description", "registration_allowed_until", "cancellation_allowed_until"},
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
 *         description="Public URL for the edition cover image",
 *         x={
 *             "padeliz-upload": {
 *                 "mode": "single",
 *                 "max_files": 1,
 *                 "max_size_kb": 5120,
 *                 "form_field": "file",
 *                 "required": true,
 *                 "accept": {"0":"image/jpeg","1":"image/png","2":"image/gif","3":"image/webp"},
 *                 "upload": {"method":"POST","path":"/api/v1/manager/events/{id}/cover"}
 *             },
 *             "padeliz-image": {"role": "list"}
 *         }
 *     ),
 *     @OA\Property(
 *         property="date",
 *         type="string",
 *         format="date",
 *         pattern="^[0-9]{4}-[0-9]{2}-[0-9]{2}$",
 *         title="Date",
 *         description="PadelEvent date in YYYY-MM-DD format",
 *         example="2024-06-15"
 *     ),
 *     @OA\Property(
 *         property="arriving_time",
 *         type="string",
 *         format="time",
 *         pattern="^([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$",
 *         title="Arriving Time",
 *         description="Arriving time in HH:MM:SS format",
 *         example="09:00:00"
 *     ),
 *     @OA\Property(
 *         property="closing_time",
 *         type="string",
 *         format="time",
 *         pattern="^([01]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$",
 *         title="Closing Time",
 *         description="Closing time in HH:MM:SS format",
 *         example="18:00:00"
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
 */
class PadelEvent extends Model
{
    use HasFactory;
    use HasResourceMetadata;

    /**
     * Fields that can be searched
     */
    public static array $searchableFields = [
        'name',
    ];

    /**
     * Fields that can be filtered
     */
    public static array $filterableFields = [
        'location_id',
        'organizer_id',
    ];

    /**
     * Fields that can be sorted
     */
    public static array $sortableFields = [
        'id_event',
        'name',
        'date',
        'location_id',
        'organizer_id',
        'arriving_time',
        'closing_time',
        'finalized_at',
        'registration_allowed_until',
        'cancellation_allowed_until',
        'inserted_at',
        'updated_at',
    ];

    /**
     * Field that will be used to display the edition in the UI
     */
    public static ?string $displayField = 'name';

    public static array $listFields = [
        'cover_url',
    ];

    protected $table = 'events';

    protected $primaryKey = 'id_event';

    public $timestamps = true;

    protected $dateFormat = 'U'; // handle inserted_at and updated_at as Unix timestamps

    public const CREATED_AT = 'inserted_at';
    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'name',
        'description',
        'cover_path',
        'date',
        'location_id',
        'organizer_id',
        'include_club_host_on_pdf',
        'arriving_time',
        'closing_time',
        'finalized_at',
        'registration_allowed_until',
        'cancellation_allowed_until',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'finalized_at' => 'integer',
        'registration_allowed_until' => 'integer',
        'cancellation_allowed_until' => 'integer',
        'include_club_host_on_pdf' => 'boolean',
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    protected $appends = [
        'formatted_date',
        'formatted_arriving_time',
        'formatted_closing_time',
        'cover_url',
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
        return config('filesystems.user_uploads_disk', 'public');
    }

    public function getCoverUrlAttribute(): ?string
    {
        if ($this->cover_path === null || $this->cover_path === '') {
            return null;
        }

        return Storage::disk($this->storageDisk())->url($this->cover_path);
    }

    /**
     * Get the formatted date as Y-m-d
     */
    public function getFormattedDateAttribute()
    {
        if ($this->attributes['date']) {
            return $this->asDate($this->attributes['date'])->format('Y-m-d');
        }

        return null;
    }

    /**
     * Get the formatted arriving time as H:i:s
     */
    public function getFormattedArrivingTimeAttribute()
    {
        return PlayingWindow::toClockString($this->attributes['arriving_time'] ?? null);
    }

    /**
     * Get the formatted closing time as H:i:s
     */
    public function getFormattedClosingTimeAttribute()
    {
        return PlayingWindow::toClockString($this->attributes['closing_time'] ?? null);
    }
}
