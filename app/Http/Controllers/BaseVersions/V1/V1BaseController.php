<?php

declare(strict_types=1);

namespace App\Http\Controllers\BaseVersions\V1;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use App\Traits\HandlesResourceDeletion;
use App\Traits\HasAdvancedPagination;

/**
 * @OA\Ignore
 *
 * @OA\Info(
 *     version="v1.0",
 *     title="v1: Padeliz API",
 *     description="API endpoints documentation for Padeliz application. Supporting players and managers."
 * )
 *
 * @OA\Server(
 *     url="/",
 *     description="API Server"
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
 *     description="Resource metadata information including Swagger schema",
 *     @OA\Property(property="version", type="string", example="v1", description="API version"),
 *     @OA\Property(property="schema_name", type="string", example="EventParticipation", description="Schema/Model name"),
 *     @OA\Property(
 *         property="swagger_schema",
 *         type="object",
 *         description="Complete OpenAPI schema from Swagger documentation",
 *         @OA\Property(property="required", type="array", @OA\Items(type="string"), example={"field1", "field2", "field3"}),
 *         @OA\Property(
 *             property="properties",
 *             type="object",
 *             description="Schema properties with validation rules, formats, and constraints"
 *         )
 *     ),
 *     @OA\Property(
 *         property="request_body",
 *         type="object",
 *         description="Request body schemas for different HTTP methods and endpoints"
 *     ),
 *     @OA\Property(
 *         property="display_field",
 *         type="string",
 *         nullable=true,
 *         description="Field name to use for user-friendly display when referenced by foreign keys",
 *         example="name"
 *     ),
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
 *                     @OA\Property(property="values", type="array", @OA\Items(type="boolean"), example={true, false})
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
 *                     @OA\Property(property="related_schema_name", type="string", example="Club"),
 *                     @OA\Property(property="related_schema_field", type="string", example="name"),
 *                     @OA\Property(
 *                         property="values",
 *                         type="object",
 *                         description="Object with foreign key IDs as keys and field values as values",
 *                         example={"14": "Club A", "2": "Club B", "30": "Club C"}
 *                     )
 *                 )
 *             }
 *         )
 *     ),
 *     @OA\Property(
 *         property="sortable_fields",
 *         type="array",
 *         @OA\Items(type="string"),
 *         example={"name", "first_name", "last_name", "inserted_at"}
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
 * Base controller for API v1.
 * All v1 controllers should extend this class.
 */
abstract class V1BaseController extends Controller
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
