<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Services\Schedule\Exception\TemplateMatchesNotFoundException;
use App\Services\Schedule\TemplatesToolsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Manager tools for committed schedule templates (catalog, demo structure, quality metrics).
 *
 * @OA\Tag(
 *     name="🐧 Manager: Tools Templates",
 *     description="(v1) Template catalog, demo match structure, and quality metrics"
 * )
 */
class TemplatesToolsController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    public function __construct(
        private readonly TemplatesToolsService $templatesToolsService,
    ) {
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/tools/templates/catalog",
     *     summary="List available schedule template combinations",
     *     description="Mixed templates by default. Pass include_fixed_teams=1 to include -fixedteams JSON.",
     *     tags={"🐧 Manager: Tools Templates"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="include_fixed_teams",
     *         in="query",
     *         description="Include fixed-teams templates (0/1). Default 0 (mixed only).",
     *         @OA\Schema(type="integer", enum={0,1}, default=0)
     *     ),
     *     @OA\Parameter(
     *         name="templates_version",
     *         in="query",
     *         description="Template version directory (defaults to latest compatible v{N})",
     *         @OA\Schema(type="integer", minimum=1)
     *     ),
     *     @OA\Response(response=200, description="Template catalog"),
     *     @OA\Response(response=422, description="Invalid query")
     * )
     */
    public function catalog(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'include_fixed_teams' => ['nullable'],
                'templates_version' => ['nullable', 'integer', 'min:1'],
            ]);

            $data = $this->templatesToolsService->catalog(
                $this->parseBoolQuery($request, 'include_fixed_teams', false),
                isset($validated['templates_version']) ? (int) $validated['templates_version'] : null
            );
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        return $this->successResponse($data, 'Template catalog retrieved successfully');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/tools/templates/demo/{players}/{partners}/{repeat}/{courts}",
     *     summary="Return a template match structure for the demo UI",
     *     description="Loads mixed templates by default. Pass fixed_teams=1 for -fixedteams JSON.",
     *     tags={"🐧 Manager: Tools Templates"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="players", in="path", required=true, @OA\Schema(type="integer", minimum=4)),
     *     @OA\Parameter(name="partners", in="path", required=true, @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="repeat", in="path", required=true, @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="courts", in="path", required=true, @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(
     *         name="fixed_teams",
     *         in="query",
     *         description="Load fixed-teams template (0/1). Default 0 (mixed).",
     *         @OA\Schema(type="integer", enum={0,1}, default=0)
     *     ),
     *     @OA\Parameter(
     *         name="templates_version",
     *         in="query",
     *         description="Template version directory (defaults to latest compatible v{N})",
     *         @OA\Schema(type="integer", minimum=1)
     *     ),
     *     @OA\Response(response=200, description="Template demo payload"),
     *     @OA\Response(response=404, description="Template not found"),
     *     @OA\Response(response=422, description="Invalid parameters")
     * )
     */
    public function demo(
        Request $request,
        int $players,
        int $partners,
        int $repeat,
        int $courts
    ): JsonResponse {
        try {
            $validated = $request->validate([
                'fixed_teams' => ['nullable'],
                'templates_version' => ['nullable', 'integer', 'min:1'],
            ]);

            if ($players < 4 || $partners < 1 || $repeat < 1 || $courts < 1) {
                return $this->errorResponse('players, partners, repeat, and courts must be positive (players >= 4).', 422);
            }

            $data = $this->templatesToolsService->demo(
                $players,
                $partners,
                $repeat,
                $courts,
                $this->parseBoolQuery($request, 'fixed_teams', false),
                isset($validated['templates_version']) ? (int) $validated['templates_version'] : null
            );
        } catch (TemplateMatchesNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        return $this->successResponse($data, 'Template demo retrieved successfully');
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/tools/templates/metrics",
     *     summary="Quality metrics summary for committed templates",
     *     description="Same combo set as `php artisan templates:metrics`. Mixed by default; include_fixed_teams=1 lists mixed + fixed JSON on disk.",
     *     tags={"🐧 Manager: Tools Templates"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="include_fixed_teams",
     *         in="query",
     *         description="Include fixed-teams templates (0/1). Default 0 (mixed expected catalog).",
     *         @OA\Schema(type="integer", enum={0,1}, default=0)
     *     ),
     *     @OA\Parameter(
     *         name="templates_version",
     *         in="query",
     *         description="Template version directory (defaults to latest compatible v{N})",
     *         @OA\Schema(type="integer", minimum=1)
     *     ),
     *     @OA\Response(response=200, description="Metrics summary"),
     *     @OA\Response(response=422, description="Invalid query")
     * )
     */
    public function metrics(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'include_fixed_teams' => ['nullable'],
                'templates_version' => ['nullable', 'integer', 'min:1'],
            ]);

            $data = $this->templatesToolsService->metrics(
                $this->parseBoolQuery($request, 'include_fixed_teams', false),
                isset($validated['templates_version']) ? (int) $validated['templates_version'] : null
            );
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }

        return $this->successResponse($data, 'Template metrics retrieved successfully');
    }

    private function parseBoolQuery(Request $request, string $key, bool $default): bool
    {
        if (! $request->has($key) || $request->query($key) === null || $request->query($key) === '') {
            return $default;
        }

        $value = strtolower(trim((string) $request->query($key)));
        if (in_array($value, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }
        if (in_array($value, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        throw new \InvalidArgumentException(sprintf(
            'Invalid boolean query "%s": "%s". Use 0/1 or true/false.',
            $key,
            $value
        ));
    }
}
