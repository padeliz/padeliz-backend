<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Registration\CheckoutStatus;
use App\Enums\Registration\RegistrationPaymentMethod;
use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @OA\Schema(
 *     schema="EventRegistrationCheckout",
 *     required={"event_id", "player_id", "status", "total_amount"},
 *     @OA\Property(
 *         property="id_checkout",
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
 *         property="player_id",
 *         type="integer",
 *         format="int64",
 *         minimum=1,
 *         title="Player",
 *         description="Player of the checkout"
 *     ),
 *     @OA\Property(
 *         property="status",
 *         type="string",
 *         enum={"open", "awaiting_proof", "completed"},
 *         title="Status",
 *         description="Checkout lifecycle status"
 *     ),
 *     @OA\Property(
 *         property="total_amount",
 *         type="integer",
 *         minimum=0,
 *         title="Total Amount",
 *         description="Total checkout amount in RON"
 *     ),
 *     @OA\Property(
 *         property="payment_method",
 *         type="string",
 *         nullable=true,
 *         enum={"netopia", "bank_transfer"},
 *         title="Payment Method",
 *         description="Selected payment method for the checkout"
 *     ),
 *     @OA\Property(
 *         property="paid_at",
 *         type="integer",
 *         format="timestamp",
 *         nullable=true,
 *         title="Paid At",
 *         description="Timestamp when the checkout was paid"
 *     ),
 *     @OA\Property(
 *         property="inserted_at",
 *         type="integer",
 *         format="timestamp",
 *         readOnly=true,
 *         title="Created At",
 *         description="Timestamp when the checkout was created"
 *     ),
 *     @OA\Property(
 *         property="updated_at",
 *         type="integer",
 *         format="timestamp",
 *         nullable=true,
 *         readOnly=true,
 *         title="Updated At",
 *         description="Timestamp when the checkout was last updated"
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
 *         ref="#/components/schemas/Player",
 *         title="Player",
 *         description="Related player object",
 *         nullable=true
 *     )
 * )
 */
class EventRegistrationCheckout extends Model
{
    use HasFactory;
    use HasResourceMetadata;

    public static array $searchableFields = [];

    public static array $filterableFields = [
        'event_id',
        'player_id',
        'status',
        'total_amount',
        'payment_method',
    ];

    public static array $sortableFields = [
        'id_checkout',
        'event_id',
        'player_id',
        'status',
        'total_amount',
        'payment_method',
        'paid_at',
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
        'player_id',
        'event_id',
        'status',
        'total_amount',
        'payment_method',
    ];

    protected $table = 'event_registration_checkouts';

    protected $primaryKey = 'id_checkout';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'event_id',
        'player_id',
        'status',
        'total_amount',
        'payment_method',
        'paid_at',
    ];

    protected $casts = [
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
        'paid_at' => 'integer',
        'event_id' => 'integer',
        'player_id' => 'integer',
        'total_amount' => 'integer',
        'status' => CheckoutStatus::class,
        'payment_method' => RegistrationPaymentMethod::class,
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(PadelEvent::class, 'event_id', 'id_event');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id', 'id_player');
    }

    public function items(): HasMany
    {
        return $this->hasMany(EventRegistrationCheckoutItem::class, 'checkout_id', 'id_checkout');
    }

    public function paymentAttempts(): HasMany
    {
        return $this->hasMany(EventRegistrationPaymentAttempt::class, 'checkout_id', 'id_checkout');
    }

    public function paymentProofs(): HasMany
    {
        return $this->hasMany(EventRegistrationPaymentProof::class, 'checkout_id', 'id_checkout');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(EventRegistrationPaymentRefund::class, 'checkout_id', 'id_checkout');
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', [
            CheckoutStatus::Open->value,
            CheckoutStatus::AwaitingProof->value,
        ]);
    }
}
