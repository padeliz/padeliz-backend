<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Http\Resources\ResourceMetadataResource;
use App\Services\ResourceMetadataService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Resource Metadata",
 *     description="(v1) Resource metadata for frontend configuration"
 * )
 */
class ResourceMetadataController extends V1BaseController
{
    public function __construct(
        private readonly ResourceMetadataService $metadataService
    ) {
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/metadata/{resource}",
     *     summary="Get resource metadata",
     *     description="Get metadata information for a specific resource including searchable, filterable, and sortable fields",
     *     tags={"🐧 Manager: Resource Metadata"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="resource",
     *         in="path",
     *         required=true,
     *         description="Resource name (e.g., players, clubs, locations)",
     *         @OA\Schema(type="string", example="players")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Resource metadata retrieved successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(
     *                         property="data",
     *                         ref="#/components/schemas/ResourceMetadata"
     *                     )
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Authentication required",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Manager access required",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Resource not found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function show(Request $request, string $resource): \Illuminate\Http\JsonResponse
    {
        try {
            // Validate resource name
            $this->validateResourceName($resource);

            // Get metadata from service
            $metadata = $this->metadataService->getMetadata($resource);

            return $this->resourceResponse(
                new ResourceMetadataResource($metadata),
                'Resource metadata retrieved successfully'
            );
        } catch (\InvalidArgumentException $e) {
            return $this->notFoundResponse($e->getMessage());
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), 'Invalid resource name');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve resource metadata',
                500
            );
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/metadata/{resource}/cache",
     *     summary="Clear resource metadata cache",
     *     description="Clear the cached metadata for a specific resource",
     *     tags={"🐧 Manager: Resource Metadata"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="resource",
     *         in="path",
     *         required=true,
     *         description="Resource name to clear cache for",
     *         @OA\Schema(type="string", example="players")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cache cleared successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", type="null", example=null)
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Authentication required",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Manager access required",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Resource not found",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function clearCache(Request $request, string $resource): \Illuminate\Http\JsonResponse
    {
        try {
            // Validate resource name
            $this->validateResourceName($resource);

            // Clear cache
            $this->metadataService->clearCache($resource);

            return $this->successResponse(
                null,
                'Resource metadata cache cleared successfully'
            );
        } catch (\InvalidArgumentException $e) {
            return $this->notFoundResponse($e->getMessage());
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), 'Invalid resource name');
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/metadata/cache",
     *     summary="Clear all resource metadata cache",
     *     description="Clear all cached metadata for all resources",
     *     tags={"🐧 Manager: Resource Metadata"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="All cache cleared successfully",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(
     *                     @OA\Property(property="data", type="null", example=null)
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized - Authentication required",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Manager access required",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")
     *     )
     * )
     */
    public function clearAllCache(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            // Clear all cache
            $this->metadataService->clearAllCache();

            return $this->successResponse(
                null,
                'All resource metadata cache cleared successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to clear cache',
                500
            );
        }
    }

    /**
     * Validate resource name
     */
    private function validateResourceName(string $resource): void
    {
        $validator = \Illuminate\Support\Facades\Validator::make(
            ['resource' => $resource],
            [
                'resource' => [
                    'required',
                    'string',
                    'min:1',
                    'max:50',
                    'regex:/^[a-z_]+$/',
                ],
            ],
            [
                'resource.regex' => 'Resource name must contain only lowercase letters and underscores.',
            ]
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}
