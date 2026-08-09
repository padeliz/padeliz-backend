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
     *     path="/api/v1/manager/metadata/{schema}",
     *     summary="Get resource metadata",
     *     description="Get metadata information for a specific schema/model including Swagger schema, searchable, filterable, and sortable fields",
     *     tags={"🐧 Manager: Resource Metadata"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="schema",
     *         in="path",
     *         required=true,
     *         description="(e.g., Club, Division, Edition, EditionDivision, EditionLottery, EditionLotteryLucky, EditionParticipation, Eligibility, Location, MatchesTeam, Player, Prize, PrizeFromClub)",
     *         @OA\Schema(type="string", example="EditionParticipation")
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
    public function show(Request $request, string $schema): \Illuminate\Http\JsonResponse
    {
        try {
            // Extract version from the request path
            $version = $this->extractVersionFromRequest($request);

            // Validate schema name
            $this->validateSchemaName($schema);

            // Get metadata from service
            $metadata = $this->metadataService->getMetadata($schema, $version);

            return $this->resourceResponse(
                new ResourceMetadataResource($metadata),
                'Resource metadata retrieved successfully'
            );
        } catch (\InvalidArgumentException $e) {
            return $this->notFoundResponse($e->getMessage());
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), 'Invalid schema name');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve resource metadata',
                500
            );
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/metadata/{schema}/cache",
     *     summary="Clear resource metadata cache",
     *     description="Clear the cached metadata for a specific schema/model",
     *     tags={"🐧 Manager: Resource Metadata"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="schema",
     *         in="path",
     *         required=true,
     *         description="Schema/Model name to clear cache for",
     *         @OA\Schema(type="string", example="EditionParticipation")
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
    public function clearCache(Request $request, string $schema): \Illuminate\Http\JsonResponse
    {
        try {
            // Extract version from the request path
            $version = $this->extractVersionFromRequest($request);

            // Validate schema name
            $this->validateSchemaName($schema);

            // Clear cache
            $this->metadataService->clearCache($schema, $version);

            return $this->successResponse(
                null,
                'Resource metadata cache cleared successfully'
            );
        } catch (\InvalidArgumentException $e) {
            return $this->notFoundResponse($e->getMessage());
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), 'Invalid schema name');
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
     * Extract version from request path
     */
    private function extractVersionFromRequest(Request $request): string
    {
        $path = $request->path();

        // Extract version from path like /api/v1/manager/metadata/{schema}
        if (preg_match('/\/api\/(v\d+)\//', $path, $matches)) {
            return $matches[1];
        }

        // Default to v1 if no version found
        return 'v1';
    }

    /**
     * Validate schema name
     */
    private function validateSchemaName(string $schema): void
    {
        $validator = \Illuminate\Support\Facades\Validator::make(
            ['schema' => $schema],
            [
                'schema' => [
                    'required',
                    'string',
                    'min:1',
                    'max:50',
                    'regex:/^[A-Z][a-zA-Z0-9]*$/',
                ],
            ],
            [
                'schema.regex' => 'Schema name must start with uppercase letter and contain only letters and numbers (e.g., EditionParticipation).',
            ]
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}
