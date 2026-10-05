<?php

declare(strict_types=1);

return [
    'pos_signature' => env('NETOPIA_POS_SIGNATURE', ''),
    'api_key' => env('NETOPIA_API_KEY', ''),
    'is_live' => (bool) env('NETOPIA_IS_LIVE', false),
    'return_url' => env('NETOPIA_RETURN_URL', ''),
    'notify_url' => env('NETOPIA_NOTIFY_URL', ''),
    'public_key_path' => env('NETOPIA_PUBLIC_KEY_PATH', ''),
    'sandbox_start_url' => 'https://secure.sandbox.netopia-payments.com/payment/card/start',
    'live_start_url' => 'https://secure.mobilpay.ro/pay/payment/card/start',
    'sandbox_refund_url' => env('NETOPIA_SANDBOX_REFUND_URL', 'https://secure.sandbox.netopia-payments.com/payment/card/refund'),
    'live_refund_url' => env('NETOPIA_LIVE_REFUND_URL', 'https://secure.mobilpay.ro/pay/payment/card/refund'),
];
