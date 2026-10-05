<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BankTransferSettings;
use Illuminate\Database\Eloquent\Factories\Factory;

class BankTransferSettingsFactory extends Factory
{
    protected $model = BankTransferSettings::class;

    public function definition(): array
    {
        return [
            'iban' => 'RO49AAAA1B31007593840000',
            'beneficiary_name' => 'Padeliz SRL',
            'bank_name' => 'Test Bank',
            'payment_reference_template' => 'PAD-{checkout_id}',
            'extra_instructions' => null,
            'inserted_at' => time(),
        ];
    }
}
