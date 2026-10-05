<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasResourceMetadata;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @OA\Schema(
 *     schema="EventLotteryLucky",
 *     required={"drawn_participation_id", "drawn_at", "available_at"},
 *     @OA\Property(
 *         property="id_lucky",
 *         type="integer",
 *         format="int64",
 *         readOnly=true,
 *         title="ID",
 *         description="Unique Identifier"
 *     ),
 *     @OA\Property(
 *         property="lottery_rule_id",
 *         type="integer",
 *         format="int64",
 *         nullable=true,
 *         minimum=1,
 *         title="EventLotteryRule",
 *         description="Lottery rule that produced this draw"
 *     ),
 *     @OA\Property(
 *         property="drawn_participation_id",
 *         type="integer",
 *         format="int64",
 *         minimum=1,
 *         title="EventParticipation",
 *         description="PadelEvent participation of the lucky one"
 *     ),
 *     @OA\Property(
 *         property="drawn_at",
 *         type="integer",
 *         format="timestamp",
 *         minimum=0,
 *         title="Drawn At",
 *         description="Unix timestamp when drawn",
 *         example=1704067200
 *     ),
 *     @OA\Property(
 *         property="available_at",
 *         type="integer",
 *         format="timestamp",
 *         minimum=0,
 *         title="Available At",
 *         description="Unix timestamp when available",
 *         example=1704153600
 *     ),
 *     @OA\Property(
 *         property="announced_at",
 *         type="integer",
 *         format="timestamp",
 *         nullable=true,
 *         minimum=0,
 *         title="Announced At",
 *         description="Unix timestamp when announced",
 *         example=1704240000
 *     ),
 *     @OA\Property(
 *         property="accepted_at",
 *         type="integer",
 *         format="timestamp",
 *         nullable=true,
 *         minimum=0,
 *         title="Accepted At",
 *         description="Unix timestamp when accepted",
 *         example=1704326400
 *     ),
 *     @OA\Property(
 *         property="rejected_at",
 *         type="integer",
 *         format="timestamp",
 *         nullable=true,
 *         minimum=0,
 *         title="Rejected At",
 *         description="Unix timestamp when rejected",
 *         example=1704412800
 *     ),
 *     @OA\Property(property="inserted_at", type="integer", format="timestamp", readOnly=true, title="Created At", description="Timestamp when the lottery lucky was created"),
 *     @OA\Property(property="updated_at", type="integer", format="timestamp", nullable=true, readOnly=true, title="Updated At", description="Timestamp when the lottery lucky was last updated"),
 *     @OA\Property(
 *         property="lotteryRule",
 *         ref="#/components/schemas/EventLotteryRule",
 *         title="Lottery Rule",
 *         description="Related lottery rule object",
 *         nullable=true
 *     ),
 *     @OA\Property(
 *         property="drawnParticipation",
 *         ref="#/components/schemas/EventParticipation",
 *         title="Drawn Participation",
 *         description="Related drawn participation object",
 *         nullable=true
 *     )
 * )
 */
class EventLotteryLucky extends Model
{
    use HasFactory;
    use HasResourceMetadata;

    /**
     * Fields that can be searched
     */
    public static array $searchableFields = [
        // No searchable fields for this model
    ];

    /**
     * Fields that can be filtered
     */
    public static array $filterableFields = [
        'lottery_rule_id',
        'drawn_participation_id',
    ];

    /**
     * Fields that can be sorted
     */
    public static array $sortableFields = [
        'id_lucky',
        'lottery_rule_id',
        'drawn_participation_id',
        'drawn_at',
        'available_at',
        'announced_at',
        'accepted_at',
        'rejected_at',
        'inserted_at',
        'updated_at',
    ];

    /**
     * Field that will be used to display the edition lottery lucky in the UI
     */
    public static ?string $displayField = null;

    protected $table = 'event_lottery_luckies';

    protected $primaryKey = 'id_lucky';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';
    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'lottery_rule_id',
        'drawn_participation_id',
        'drawn_at',
        'available_at',
        'announced_at',
        'accepted_at',
        'rejected_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
        'drawn_at' => 'integer',
        'available_at' => 'integer',
        'announced_at' => 'integer',
        'accepted_at' => 'integer',
        'rejected_at' => 'integer',
    ];

    public function lotteryRule(): BelongsTo
    {
        return $this->belongsTo(EventLotteryRule::class, 'lottery_rule_id', 'id_lottery_rule');
    }

    public function drawnParticipation(): BelongsTo
    {
        return $this->belongsTo(EventParticipation::class, 'drawn_participation_id');
    }

    /**
     * @deprecated Use drawnParticipation(); kept for readability in some call sites.
     */
    public function participation(): BelongsTo
    {
        return $this->drawnParticipation();
    }
}
