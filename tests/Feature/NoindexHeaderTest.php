<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\SetNoindexHeader;
use Tests\TestCase;

class NoindexHeaderTest extends TestCase
{
    public function test_up_without_noindex_omits_x_robots_tag(): void
    {
        config(['app.noindex' => false]);

        $response = $this->get('/up');

        $response->assertOk();
        $this->assertFalse($response->headers->has('X-Robots-Tag'));
    }

    public function test_up_with_noindex_sets_x_robots_tag(): void
    {
        config(['app.noindex' => true]);

        $response = $this->get('/up');

        $response->assertOk();
        $response->assertHeader('X-Robots-Tag', SetNoindexHeader::X_ROBOTS_TAG);
    }
}
