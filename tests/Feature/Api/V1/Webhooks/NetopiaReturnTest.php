<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Webhooks;

use Tests\TestCase;

class NetopiaReturnTest extends TestCase
{
    public function test_return_redirects_to_padeliz_app_scheme(): void
    {
        $response = $this->get('/api/v1/payments/netopia/return');

        $response->assertRedirect('padeliz-app://payments/netopia/return');
    }

    public function test_return_preserves_query_string(): void
    {
        $response = $this->get('/api/v1/payments/netopia/return?orderId=abc&status=ok');

        $response->assertRedirect('padeliz-app://payments/netopia/return?orderId=abc&status=ok');
    }
}
