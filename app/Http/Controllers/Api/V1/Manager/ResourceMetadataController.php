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
     *         description="(e.g., Club, Division, PadelEvent, EventDivision, EventLottery, EventLotteryLucky, EventLotteryPrize, EventParticipation, EventRegistration, PlayerLevelAssessment, Location, User, UserImport, UserImportRow, PlayerProfileImageImport, PlayerProfileImageImportRow, Prize, Sponsor, SponsorPrize)",
     *         @OA\Schema(type="string", example="EventParticipation")
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
            $version = $this->extractVersionFromRequest($request);
            $this->validateSchemaName($schema);
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

    private function extractVersionFromRequest(Request $request): string
    {
        $path = $request->path();

        if (preg_match('/\/api\/(v\d+)\//', $path, $matches)) {
            return $matches[1];
        }

        return 'v1';
    }

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
                'schema.regex' => 'Schema name must start with uppercase letter and contain only letters and numbers (e.g., EventParticipation).',
            ]
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}
