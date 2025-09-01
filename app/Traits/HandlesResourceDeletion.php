<?php

declare(strict_types=1);

namespace App\Traits;

use App\Exceptions\ResourceDeletionException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

trait HandlesResourceDeletion
{
    /**
     * Safely delete a resource, handling foreign key constraint violations
     *
     * @param Model $resource The resource to delete
     * @param array $linkedDataRelations Array of relation methods and their display names
     * @param string $resourceType The type of resource (e.g., 'club', 'location', 'prize')
     * @return bool True if deletion was successful
     * @throws ResourceDeletionException If deletion fails due to linked data
     */
    protected function safeDeleteResource(
        Model $resource,
        array $linkedDataRelations,
        string $resourceType = 'resource'
    ): bool {
        try {
            $resource->delete();

            return true;
        } catch (QueryException $e) {
            // Check if it's a foreign key constraint violation
            if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'foreign key constraint fails')) {
                // Get linked data information
                $linkedData = [];
                foreach ($linkedDataRelations as $relationMethod => $displayName) {
                    if (method_exists($resource, $relationMethod)) {
                        $linkedData[$displayName] = $resource->{$relationMethod}()->count();
                    }
                }

                $resourceTypeCapitalized = ucfirst($resourceType);
                $linkedDataList = implode(', ', array_values($linkedDataRelations));

                throw new ResourceDeletionException(
                    "{$resourceTypeCapitalized} cannot be deleted because it has linked data. Please remove all associated {$linkedDataList} first.",
                    $linkedData,
                    $resourceType
                );
            }

            // Re-throw other database exceptions
            throw $e;
        }
    }

    /**
     * Handle resource deletion with proper error response
     *
     * @param Model $resource The resource to delete
     * @param array $linkedDataRelations Array of relation methods and their display names
     * @param string $resourceType The type of resource
     * @param string $successMessage Custom success message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function deleteResourceWithResponse(
        Model $resource,
        array $linkedDataRelations,
        string $resourceType = 'resource',
        string $successMessage = null
    ) {
        try {
            $this->safeDeleteResource($resource, $linkedDataRelations, $resourceType);

            $defaultMessage = ucfirst($resourceType) . ' deleted successfully';
            $message = $successMessage ?? $defaultMessage;

            return $this->successResponse(null, $message);
        } catch (ResourceDeletionException $e) {
            return $this->errorResponse(
                $e->getMessage(),
                409,
                ['linked_data' => $e->getLinkedData()]
            );
        }
    }

    /**
     * Handle bulk resource deletion with proper error response
     *
     * @param array $resourceIds Array of resource IDs to delete
     * @param string $modelClass The model class name
     * @param array $linkedDataRelations Array of relation methods and their display names
     * @param string $resourceType The type of resource
     * @param string $successMessage Custom success message
     * @return \Illuminate\Http\JsonResponse
     */
    protected function deleteBulkResourcesWithResponse(
        array $resourceIds,
        string $modelClass,
        array $linkedDataRelations,
        string $resourceType = 'resource',
        string $successMessage = null
    ) {
        if (empty($resourceIds)) {
            return $this->errorResponse(
                'No resources provided for deletion',
                400
            );
        }

        $results = [
            'successful' => [],
            'failed' => [],
            'total_requested' => count($resourceIds),
            'total_successful' => 0,
            'total_failed' => 0,
        ];

        foreach ($resourceIds as $resourceId) {
            try {
                $resource = $modelClass::find($resourceId);

                if (! $resource) {
                    $results['failed'][] = [
                        'id' => $resourceId,
                        'reason' => 'Resource not found',
                    ];
                    $results['total_failed']++;

                    continue;
                }

                $this->safeDeleteResource($resource, $linkedDataRelations, $resourceType);

                $results['successful'][] = [
                    'id' => $resourceId,
                    'name' => $resource->name ?? $resource->id ?? $resourceId,
                ];
                $results['total_successful']++;

            } catch (ResourceDeletionException $e) {
                $results['failed'][] = [
                    'id' => $resourceId,
                    'reason' => $e->getMessage(),
                    'linked_data' => $e->getLinkedData(),
                ];
                $results['total_failed']++;
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'id' => $resourceId,
                    'reason' => 'Unexpected error: ' . $e->getMessage(),
                ];
                $results['total_failed']++;
            }
        }

        // Determine response based on results
        if ($results['total_failed'] === 0) {
            // All successful
            $defaultMessage = ucfirst($resourceType) . 's deleted successfully';
            $message = $successMessage ?? $defaultMessage;

            return $this->successResponse(
                $results,
                $message
            );
        } elseif ($results['total_successful'] === 0) {
            // All failed - check if it's due to linked data or not found
            $hasLinkedDataErrors = false;
            foreach ($results['failed'] as $failure) {
                if (isset($failure['linked_data'])) {
                    $hasLinkedDataErrors = true;

                    break;
                }
            }

            $statusCode = $hasLinkedDataErrors ? 409 : 207;

            return $this->errorResponse(
                'All resources failed to delete',
                $statusCode,
                $results
            );
        } else {
            // Partial success
            return $this->errorResponse(
                'Some resources failed to delete',
                207, // Multi-Status
                $results
            );
        }
    }
}
