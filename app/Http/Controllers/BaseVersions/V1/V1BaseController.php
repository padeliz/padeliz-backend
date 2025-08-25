<?php

namespace App\Http\Controllers\BaseVersions\V1;

use App\Http\Controllers\Controller;

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
 *     schema="PaginatedResponse",
 *     description="Generic paginated response",
 *     @OA\Property(property="current_page", type="integer"),
 *     @OA\Property(property="data", type="array", @OA\Items(type="object")),
 *     @OA\Property(property="first_page_url", type="string"),
 *     @OA\Property(property="from", type="integer"),
 *     @OA\Property(property="last_page", type="integer"),
 *     @OA\Property(property="last_page_url", type="string"),
 *     @OA\Property(property="next_page_url", type="string"),
 *     @OA\Property(property="path", type="string"),
 *     @OA\Property(property="per_page", type="integer"),
 *     @OA\Property(property="prev_page_url", type="string"),
 *     @OA\Property(property="to", type="integer"),
 *     @OA\Property(property="total", type="integer")
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
 *     bearerFormat="JWT",
 *     securityScheme="sanctum"
 * )
 *
 * Base controller for API v1
 * All v1 controllers should extend this class
 */
class V1BaseController extends Controller
{
    /**
     * Get the current API version
     */
    protected function getApiVersion(): string
    {
        return 'v1';
    }
}
