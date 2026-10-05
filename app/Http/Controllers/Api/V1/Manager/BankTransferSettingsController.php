<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Models\BankTransferSettings;
use Illuminate\Http\Request;

class BankTransferSettingsController extends V1BaseController
{
    public function show()
    {
        $settings = BankTransferSettings::current();
        if ($settings === null) {
            return $this->successResponse(null, 'Bank transfer settings not configured yet');
        }

        return $this->successResponse([
            'id_bank_transfer_settings' => $settings->id_bank_transfer_settings,
            'iban' => $settings->iban,
            'beneficiary_name' => $settings->beneficiary_name,
            'bank_name' => $settings->bank_name,
            'payment_reference_template' => $settings->payment_reference_template,
            'extra_instructions' => $settings->extra_instructions,
        ], 'Bank transfer settings');
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'iban' => 'required|string|max:64',
            'beneficiary_name' => 'required|string|max:255',
            'bank_name' => 'nullable|string|max:255',
            'payment_reference_template' => 'nullable|string|max:255',
            'extra_instructions' => 'nullable|string|max:5000',
        ]);

        $settings = BankTransferSettings::current();
        if ($settings === null) {
            $settings = BankTransferSettings::query()->create([
                ...$validated,
                'payment_reference_template' => $validated['payment_reference_template'] ?? 'PAD-{checkout_id}',
            ]);
        } else {
            $settings->fill([
                ...$validated,
                'payment_reference_template' => $validated['payment_reference_template'] ?? $settings->payment_reference_template,
            ]);
            $settings->save();
        }

        return $this->successResponse([
            'id_bank_transfer_settings' => $settings->id_bank_transfer_settings,
            'iban' => $settings->iban,
            'beneficiary_name' => $settings->beneficiary_name,
            'bank_name' => $settings->bank_name,
            'payment_reference_template' => $settings->payment_reference_template,
            'extra_instructions' => $settings->extra_instructions,
        ], 'Bank transfer settings saved');
    }
}
