<?php

declare(strict_types=1);

namespace App\Http\Controllers\BaseVersions\V1;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
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
