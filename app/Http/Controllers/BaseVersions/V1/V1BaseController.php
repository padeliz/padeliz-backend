<?php

declare(strict_types=1);

namespace App\Http\Controllers\BaseVersions\V1;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use App\Traits\HandlesResourceDeletion;
use App\Traits\HasAdvancedPagination;

/**
 * @OA\Info(
 *     version="v1",
 *     title="v1: Padeliz API",
 *     description="API endpoints documentation for Padeliz application. Supporting players and managers."
 * )
 *
 * @OA\Server(
 *     url="/",
 *     description="API Server"
 * )
 *
 * @OA\Tag(
 *     name="[DEV ONLY]",
 *     description="‼️ These endpoints are not available in production."
 * )
 *
 * @OA\Schema(
 *     schema="ApiPaginatedResponse",
 *     description="Generic paginated response",
 *     @OA\Property(property="data", type="array", @OA\Items(type="object")),
 *     @OA\Property(property="links", type="object",
 *         @OA\Property(property="first", type="string"),
 *         @OA\Property(property="last", type="string"),
 *         @OA\Property(property="prev", type="string", nullable=true),
 *         @OA\Property(property="next", type="string", nullable=true)
 *     ),
 *     @OA\Property(property="meta", type="object",
 *         @OA\Property(property="current_page", type="integer"),
 *         @OA\Property(property="from", type="integer"),
 *         @OA\Property(property="last_page", type="integer"),
 *         @OA\Property(property="path", type="string"),
 *         @OA\Property(property="per_page", type="integer"),
 *         @OA\Property(property="to", type="integer"),
 *         @OA\Property(property="total", type="integer")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="ApiResponse",
 *     description="Standardized API response format",
 *     @OA\Property(property="success", type="boolean", example=true),
 *     @OA\Property(property="message", type="string", example="Operation completed successfully"),
 *     @OA\Property(property="data", type="object", description="Response data")
 * )
 *
 * @OA\Schema(
 *     schema="ApiErrorResponse",
 *     description="Standardized API error response format",
 *     @OA\Property(property="success", type="boolean", example=false),
 *     @OA\Property(property="message", type="string", example="Error message"),
 *     @OA\Property(property="errors", type="object", description="Validation errors", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="ResourceMetadata",
 *     description="Resource metadata information",
 *     @OA\Property(property="resource", type="string", example="players"),
 *     @OA\Property(
 *         property="searchable_fields",
 *         type="array",
 *         @OA\Items(type="string"),
 *         example={"name", "first_name", "last_name", "phone"}
 *     ),
 *     @OA\Property(
 *         property="filterable_fields",
 *         type="object",
 *         additionalProperties=@OA\Schema(
 *             oneOf={
 *                 @OA\Schema(
 *                     type="object",
 *                     @OA\Property(property="type", type="string", example="float"),
 *                     @OA\Property(
 *                         property="values",
 *                         type="array",
 *                         @OA\Items(type="number"),
 *                         example={1.0, 2.5, 3.0, 4.5, 5.0}
 *                     )
 *                 ),
 *                 @OA\Schema(
 *                     type="object",
 *                     @OA\Property(property="type", type="string", example="boolean"),
 *                     @OA\Property(
 *                         property="values",
 *                         type="array",
 *                         @OA\Items(type="boolean"),
 *                         example={true, false}
 *                     )
 *                 ),
 *                 @OA\Schema(
 *                     type="object",
 *                     @OA\Property(property="type", type="string", example="string"),
 *                     @OA\Property(
 *                         property="values",
 *                         type="array",
 *                         @OA\Items(type="string"),
 *                         example={"Bucharest", "Cluj", "Timisoara"}
 *                     )
 *                 ),
 *                 @OA\Schema(
 *                     type="object",
 *                     @OA\Property(property="type", type="string", example="foreign_key"),
 *                     @OA\Property(property="related_model", type="string", example="clubs"),
 *                     @OA\Property(property="searchable_field", type="string", example="name"),
 *                     @OA\Property(
 *                         property="values",
 *                         type="array",
 *                         @OA\Items(type="string"),
 *                         example={"Club A", "Club B", "Club C"}
 *                     )
 *                 )
 *             }
 *         )
 *     ),
 *     @OA\Property(
 *         property="sortable_fields",
 *         type="array",
 *         @OA\Items(type="string"),
 *         example={"name", "first_name", "last_name", "skill_division", "inserted_at"}
 *     )
 * )
 *
 * @OA\Parameter(
 *     parameter="page",
 *     name="page",
 *     in="query",
 *     description="Page number",
 *     required=false,
 *     @OA\Schema(type="integer", default=1)
 * )
 *
 * @OA\SecurityScheme(
 *     type="http",
 *     scheme="bearer",
 *     securityScheme="sanctum"
 * )
 *
 * Base controller for API v1
 * All v1 controllers should extend this class
 */
class V1BaseController extends Controller
{
    use ApiResponse;
    use HasAdvancedPagination;
    use HandlesResourceDeletion;

    /**
     * Get the current API version
     */
    protected function getApiVersion(): string
    {
        return 'v1';
    }

    /**
     * Constructor to set default pagination values
     */
    public function __construct()
    {
        $this->setDefaultPerPage(15);
        $this->setMaxPerPage(100);
    }
}
