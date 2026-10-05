<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Registration\PaymentProofReviewStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

/**
 * @property-read string $url
 */
class EventRegistrationPaymentProof extends Model
{
    use HasFactory;

    /** Maximum allowed file size in kilobytes. */
    public const MAX_FILE_KILOBYTES = 30720;

    protected $table = 'event_registration_payment_proofs';

    protected $primaryKey = 'id_payment_proof';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'checkout_id',
        'proof_path',
        'review_status',
        'review_message',
        'reviewed_by',
        'reviewed_at',
        'coverage',
    ];

    protected $casts = [
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
        'reviewed_at' => 'integer',
        'checkout_id' => 'integer',
        'reviewed_by' => 'integer',
        'coverage' => 'array',
        'review_status' => PaymentProofReviewStatus::class,
    ];

    protected $appends = ['url'];

    protected static function booted(): void
    {
        static::deleting(function (EventRegistrationPaymentProof $row): void {
            if ($row->proof_path !== null && $row->proof_path !== '') {
                Storage::disk($row->storageDisk())->delete($row->proof_path);
            }
        });
    }

    public function checkout(): BelongsTo
    {
        return $this->belongsTo(EventRegistrationCheckout::class, 'checkout_id', 'id_checkout');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'reviewed_by', 'id_player');
    }

    public function storageDisk(): string
    {
        return config('filesystems.user_uploads_disk', 'public');
    }

    public function getUrlAttribute(): string
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($this->storageDisk());

        return $disk->url($this->proof_path);
    }
}
