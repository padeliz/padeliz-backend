<?php

declare(strict_types=1);

namespace Tests\Unit\Exceptions;

use App\Exceptions\ResourceDeletionException;
use Tests\TestCase;

class ResourceDeletionExceptionTest extends TestCase
{
    public function test_exception_has_default_message()
    {
        $exception = new ResourceDeletionException();

        $this->assertEquals('Resource cannot be deleted due to linked data', $exception->getMessage());
        $this->assertEquals([], $exception->getLinkedData());
        $this->assertEquals('resource', $exception->getResourceType());
    }

    public function test_exception_has_custom_message()
    {
        $customMessage = 'Custom deletion error message';
        $exception = new ResourceDeletionException($customMessage);

        $this->assertEquals($customMessage, $exception->getMessage());
        $this->assertEquals([], $exception->getLinkedData());
        $this->assertEquals('resource', $exception->getResourceType());
    }

    public function test_exception_has_linked_data()
    {
        $linkedData = [
            'locations_count' => 5,
            'prizes_count' => 3,
        ];

        $exception = new ResourceDeletionException('Custom message', $linkedData, 'club');

        $this->assertEquals('Custom message', $exception->getMessage());
        $this->assertEquals($linkedData, $exception->getLinkedData());
        $this->assertEquals('club', $exception->getResourceType());
    }

    public function test_exception_extends_base_exception()
    {
        $exception = new ResourceDeletionException();

        $this->assertInstanceOf(\Exception::class, $exception);
    }
}
