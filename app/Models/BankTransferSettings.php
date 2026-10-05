<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankTransferSettings extends Model
{
    use HasFactory;

    protected $table = 'bank_transfer_settings';

    protected $primaryKey = 'id_bank_transfer_settings';

    public $timestamps = true;

    protected $dateFormat = 'U';

    public const CREATED_AT = 'inserted_at';

    public const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'iban',
        'beneficiary_name',
        'bank_name',
        'payment_reference_template',
        'extra_instructions',
    ];

    protected $casts = [
        'inserted_at' => 'integer',
        'updated_at' => 'integer',
    ];

    public static function current(): ?self
    {
        return static::query()->orderBy('id_bank_transfer_settings')->first();
    }

    public function referenceForCheckout(int $checkoutId): string
    {
        $template = $this->payment_reference_template ?: 'PAD-{checkout_id}';

        return str_replace(
            ['{checkout_id}', '{registration_id}'],
            [(string) $checkoutId, (string) $checkoutId],
            $template
        );
    }

    /** @deprecated Use referenceForCheckout */
    public function referenceForRegistration(int $registrationId): string
    {
        return $this->referenceForCheckout($registrationId);
    }
}
