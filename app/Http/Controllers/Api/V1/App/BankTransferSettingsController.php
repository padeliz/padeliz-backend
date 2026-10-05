<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Models\BankTransferSettings;
use Illuminate\Http\Request;

class BankTransferSettingsController extends V1BaseController
{
    public function show(Request $request)
    {
        $settings = BankTransferSettings::current();
        if ($settings === null) {
            return $this->errorResponse('Bank transfer settings are not configured.', 404);
        }

        $registrationId = $request->query('registration_id');
        $checkoutId = $request->query('checkout_id');
        $reference = null;
        if ($checkoutId !== null && ctype_digit((string) $checkoutId)) {
            $reference = $settings->referenceForCheckout((int) $checkoutId);
        } elseif ($registrationId !== null && ctype_digit((string) $registrationId)) {
            $reference = $settings->referenceForRegistration((int) $registrationId);
        }

        return $this->successResponse([
            'iban' => $settings->iban,
            'beneficiary_name' => $settings->beneficiary_name,
            'bank_name' => $settings->bank_name,
            'payment_reference_template' => $settings->payment_reference_template,
            'payment_reference' => $reference,
            'extra_instructions' => $settings->extra_instructions,
        ], 'Bank transfer settings');
    }
}
