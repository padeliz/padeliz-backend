<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Services\RegistrationPayment\NetopiaPaymentService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class NetopiaIpnController extends V1BaseController
{
    public function __construct(
        private readonly NetopiaPaymentService $netopiaPaymentService,
    ) {
    }

    public function handle(Request $request)
    {
        try {
            $attempt = $this->netopiaPaymentService->handleIpn($request->all());
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }

        return response()->json([
            'status' => 'ok',
            'order_id' => $attempt->order_id,
            'attempt_status' => $attempt->status?->value ?? $attempt->status,
        ]);
    }
}
