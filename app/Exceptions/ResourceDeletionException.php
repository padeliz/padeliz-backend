<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class ResourceDeletionException extends Exception
{
    protected $linkedData;

    protected $resourceType;

    public function __construct(
        string $message = 'Resource cannot be deleted due to linked data',
        array $linkedData = [],
        string $resourceType = 'resource'
    ) {
        parent::__construct($message);
        $this->linkedData = $linkedData;
        $this->resourceType = $resourceType;
    }

    public function getLinkedData(): array
    {
        return $this->linkedData;
    }

    public function getResourceType(): string
    {
        return $this->resourceType;
    }
}
