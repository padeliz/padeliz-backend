<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Registration\CheckoutItemStatus;
use App\Enums\Registration\PaymentAttemptStatus;
use App\Enums\Registration\RegistrationCancelReason;
use App\Enums\Registration\RegistrationDeclineReason;
use App\Enums\Registration\RegistrationLifecycleStatus;
use App\Enums\Registration\RegistrationPaymentMethod;
use App\Enums\Registration\RegistrationPaymentStatus;
use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @OA\Schema(
 *     schema="EventRegistration",
 *     required={"event_id", "player_user_id", "is_minor", "is_student", "payment"},
 *     @OA\Property(
 *         property="id_registration",
 *         type="integer",
 *         format="int64",
 *         readOnly=true,
 *         title="ID",
 *         description="Unique Identifier"
 *     ),
 *     @OA\Property(
 *         property="event_id",
 *         type="integer",
 *         format="int64",
 *         minimum=1,
 *         title="PadelEvent",
 *         description="Foreign key - References PadelEvent.id_event"
 *     ),
 *     @OA\Property(
 *         property="player_user_id",
 *         type="integer",
 *         format="int64",
 *         minimum=1,
 *         title="Player",
 *         description="Player of the registration"
 *     ),
 *     @OA\Property(
 *         property="event_division_id",
 *         type="integer",
 *         format="int64",
 *         nullable=true,
 *         minimum=1,
 *         title="PadelEvent Division",
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
 *         property="payment",
 *         type="integer",
 *         minimum=0,
 *         title="Payment",
 *         description="Payment amount in RON",
 *         example=45
 *     ),
 *     @OA\Property(
 *         property="payment_due_at",
 *         type="integer",
 *         format="timestamp",
 *         nullable=true,
 *         title="Payment Due At",
 *         description="Unix timestamp when unpaid registration payment is due"
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
 *         property="event",
 *         ref="#/components/schemas/Event",
 *         title="PadelEvent",
 *         description="Related edition object",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="player",
 *         ref="#/components/schemas/User",
 *         title="Player",
 *         description="Related player object",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="eventDivision",
 *         ref="#/components/schemas/EventDivision",
 *         title="PadelEvent Division",
 *         description="Related edition division object",
 *         nullable=true
 *     )
 * )
 */
class EventRegistration extends Model
{
    use HasFactory;
    use HasResourceMetadata;

    public static array $searchableFields = [];

    public static array $filterableFields = [
        'event_id',
        'player_user_id',
        'event_division_id',
        'is_minor',
        'is_student',
        'payment',
        'lifecycle_status',
        'payment_status',
        'payment_method',
    ];

    public static array $sortableFields = [
        'id_registration',
        'event_id',
        'player_user_id',
        'event_division_id',
        'is_minor',
        'is_student',
        'payment',
        'lifecycle_status',
        'payment_status',
        'paid_at',
        'payment_due_at',
        'inserted_at',
        'updated_at',
    ];

    public static ?string $displayField = null;

    /**
     * Preferred Manager list column order (after PK). Not an allowlist.
     *
     * @var list<string>
     */
    public static array $listFields = [
        'player.profile_image_url',
        'player_user_id',
        'event_division_id',
    ];

    protected $table = 'event_registrations';

    protected $primaryKey = 'id_registration';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';
    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'event_id',
        'player_user_id',
        'event_division_id',
        'is_minor',
        'is_student',
        'payment',
        'lifecycle_status',
        'payment_status',
        'payment_method',
        'paid_at',
        'payment_due_at',
        'cancelled_at',
        'declined_at',
        'cancel_reason',
        'decline_reason',
    ];

    protected $casts = [
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
        'paid_at' => 'integer',
        'payment_due_at' => 'integer',
        'cancelled_at' => 'integer',
        'declined_at' => 'integer',
        'is_minor' => 'boolean',
        'is_student' => 'boolean',
        'lifecycle_status' => RegistrationLifecycleStatus::class,
        'payment_status' => RegistrationPaymentStatus::class,
        'payment_method' => RegistrationPaymentMethod::class,
        'cancel_reason' => RegistrationCancelReason::class,
        'decline_reason' => RegistrationDeclineReason::class,
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(PadelEvent::class, 'event_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player_user_id');
    }

    public function eventDivision(): BelongsTo
    {
        return $this->belongsTo(EventDivision::class, 'event_division_id', 'id_event_division');
    }

    public function checkoutItems(): HasMany
    {
        return $this->hasMany(EventRegistrationCheckoutItem::class, 'registration_id', 'id_registration');
    }

    public function latestCheckoutItem(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(EventRegistrationCheckoutItem::class, 'registration_id', 'id_registration')
            ->latestOfMany('id_checkout_item');
    }

    public function redemptionTotal(): int
    {
        return (int) BenefitRedemption::query()
            ->whereHas('checkoutItem', function ($query): void {
                $query->where('registration_id', $this->id_registration)
                    ->where('item_status', CheckoutItemStatus::Active->value);
            })
            ->sum('amount_ron');
    }

    public function amountDue(): int
    {
        return max(0, (int) $this->payment - $this->redemptionTotal());
    }

    public function isPaymentOverdue(?int $now = null): bool
    {
        if ($this->payment_due_at === null) {
            return false;
        }

        if ($this->payment_status === RegistrationPaymentStatus::Paid) {
            return false;
        }

        return (int) $this->payment_due_at <= ($now ?? time());
    }

    public function feesAreLocked(): bool
    {
        $status = $this->payment_status;
        if (! $status instanceof RegistrationPaymentStatus) {
            $status = RegistrationPaymentStatus::from((string) $status);
        }

        return $status->locksFees();
    }

    public function scopeActive($query)
    {
        return $query->where('lifecycle_status', RegistrationLifecycleStatus::Active->value);
    }

    /**
     * Manager ops queue: active registrations awaiting bank-proof review.
     */
    public function scopeQueueAwaitingProof($query)
    {
        return $query->active()
            ->where('payment_status', RegistrationPaymentStatus::AwaitingProofReview->value);
    }

    /**
     * Manager ops queue: refund_pending (including cancelled lifecycle).
     */
    public function scopeQueueRefundPending($query)
    {
        return $query->where('payment_status', RegistrationPaymentStatus::RefundPending->value);
    }

    /**
     * Manager ops queue: abandoned Netopia checkouts (unpaid with pending/failed attempts on linked checkout).
     */
    public function scopeQueueAbandonedNetopia($query)
    {
        return $query->active()
            ->where('payment_status', RegistrationPaymentStatus::Unpaid->value)
            ->whereHas('checkoutItems', static function ($items): void {
                $items->where('item_status', CheckoutItemStatus::Active->value)
                    ->whereHas('checkout', static function ($checkout): void {
                        $checkout->whereHas('paymentAttempts', static function ($attempts): void {
                            $attempts->whereIn('status', [
                                PaymentAttemptStatus::Pending->value,
                                PaymentAttemptStatus::Failed->value,
                            ]);
                        });
                    });
            });
    }

    public static function hasDeclinedForEdition(int $editionId, int $playerId): bool
    {
        return static::query()
            ->where('event_id', $editionId)
            ->where('player_user_id', $playerId)
            ->where('lifecycle_status', RegistrationLifecycleStatus::Declined->value)
            ->exists();
    }

    /**
     * XOR uniqueness among active rows: either one NULL event_division_id row,
     * or one or more specific edition divisions, not both, per (event_id, player_user_id).
     *
     * @return null|array<string, array<int, string>>
     */
    public static function xorConflict(
        int $editionId,
        int $playerId,
        ?int $eventDivisionId,
        ?int $ignoreRegistrationId = null
    ): ?array {
        if ($eventDivisionId === null) {
            return [
                'event_division_id' => [
                    'An edition division is required.',
                ],
            ];
        }

        $query = static::query()
            ->active()
            ->where('event_id', $editionId)
            ->where('player_user_id', $playerId);

        if ($ignoreRegistrationId !== null) {
            $query->where('id_registration', '!=', $ignoreRegistrationId);
        }

        $others = $query->get(['id_registration', 'event_division_id']);

        if ($others->contains(fn (self $row): bool => (int) $row->event_division_id === $eventDivisionId)) {
            return [
                'event_division_id' => [
                    'This player is already registered for this edition division.',
                ],
            ];
        }

        return null;
    }
}
