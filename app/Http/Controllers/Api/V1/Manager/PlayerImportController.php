<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Http\Resources\PlayerImportResource;
use App\Http\Resources\PlayerResource;
use App\Models\Player;
use App\Models\PlayerImport;
use App\Services\PlayerImport\PlayerImportFailedException;
use App\Services\PlayerImport\PlayerImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Player Imports",
 *     description="(v1) CSV player import preview, save, and history"
 * )
 */
class PlayerImportController extends V1BaseController
{
    public function __construct(
        private readonly PlayerImportService $importService
    ) {
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/players/import/preview",
     *     summary="Preview a player CSV without writing",
     *     tags={"🐧 Manager: Player Imports"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"file"},
     *                 @OA\Property(property="file", type="string", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Preview rows"),
     *     @OA\Response(response=401, description="Unauthenticated", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file',
        ]);

        try {
            $preview = $this->importService->preview($request->file('file'));
        } catch (PlayerImportFailedException $e) {
            return $this->errorResponse($e->getMessage(), $e->status, $e->errors);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), $e->getMessage());
        }

        $rows = [];
        foreach ($preview['rows'] as $row) {
            $mapping = $row['mapping'];
            $rows[] = [
                'index' => $row['index'],
                'first_name' => $row['first_name'],
                'middle_name' => $row['middle_name'],
                'last_name' => $row['last_name'],
                'email' => $row['email'],
                'phone' => $row['phone'],
                'mapping' => [
                    'status' => $mapping['status'],
                    'matched_by' => $mapping['matched_by'],
                    'player' => $this->playerPayload($mapping['player']),
                    'candidates' => array_map(
                        fn (Player $player) => $this->playerPayload($player),
                        $mapping['candidates']
                    ),
                    'name_matches' => array_map(
                        fn (Player $player) => $this->playerPayload($player),
                        $mapping['name_matches']
                    ),
                ],
            ];
        }

        return $this->successResponse(
            [
                'format' => $preview['format'],
                'rows' => $rows,
            ],
            'Preview generated'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/players/import",
     *     summary="Save included CSV rows as players and an import batch",
     *     tags={"🐧 Manager: Player Imports"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"format", "filename", "rows"},
     *             @OA\Property(property="format", type="string", example="playtomic"),
     *             @OA\Property(property="filename", type="string"),
     *             @OA\Property(
     *                 property="rows",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="index", type="integer"),
     *                     @OA\Property(property="first_name", type="string", nullable=true),
     *                     @OA\Property(property="middle_name", type="string", nullable=true),
     *                     @OA\Property(property="last_name", type="string", nullable=true),
     *                     @OA\Property(property="email", type="string", nullable=true),
     *                     @OA\Property(property="phone", type="string", nullable=true),
     *                     @OA\Property(property="player_id", type="integer", nullable=true)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Import saved",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(@OA\Property(property="data", ref="#/components/schemas/PlayerImport"))
     *             }
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'format' => 'required|string|max:32',
            'filename' => 'required|string|max:255',
            'rows' => 'required|array|min:1',
            'rows.*.index' => 'required|integer|min:0',
            'rows.*.first_name' => 'nullable|string|max:50',
            'rows.*.middle_name' => 'nullable|string|max:50',
            'rows.*.last_name' => 'nullable|string|max:50',
            'rows.*.email' => 'nullable|string|max:255',
            'rows.*.phone' => 'nullable|string|max:30',
            'rows.*.player_id' => 'nullable|integer',
        ]);

        try {
            $import = $this->importService->save($validated, (int) $request->user()->id_player);
        } catch (PlayerImportFailedException $e) {
            return $this->errorResponse($e->getMessage(), $e->status, $e->errors);
        }

        return $this->resourceResponse(
            new PlayerImportResource($import),
            'Players imported successfully',
            201
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/player-imports",
     *     summary="List player import batches",
     *     tags={"🐧 Manager: Player Imports"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(ref="#/components/parameters/page"),
     *     @OA\Response(response=200, description="Paginated imports")
     * )
     */
    public function index(Request $request)
    {
        $query = PlayerImport::with('importedByPlayer');

        $this->applyAdvancedPagination(
            $query,
            $request,
            PlayerImport::$searchableFields,
            PlayerImport::$filterableFields,
            PlayerImport::$sortableFields
        );

        $paginationParams = $this->getPaginationParams($request);

        if (! $request->has('sort_by')) {
            $query->orderBy('id_player_import', 'desc');
        }

        $imports = $query->paginate(
            $paginationParams['per_page'],
            ['*'],
            'page',
            $paginationParams['page']
        );

        return PlayerImportResource::collection($imports);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/player-imports/{playerImport}",
     *     summary="Get a player import batch",
     *     tags={"🐧 Manager: Player Imports"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="playerImport", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Import details",
     *         @OA\JsonContent(
     *             allOf={
     *                 @OA\Schema(ref="#/components/schemas/ApiResponse"),
     *                 @OA\Schema(@OA\Property(property="data", ref="#/components/schemas/PlayerImport"))
     *             }
     *         )
     *     )
     * )
     */
    public function show(PlayerImport $playerImport): JsonResponse
    {
        $playerImport->load(['rows', 'importedByPlayer']);

        return $this->resourceResponse(
            new PlayerImportResource($playerImport),
            'Player import retrieved successfully'
        );
    }

    /**
     * @return null|array<string, mixed>
     */
    private function playerPayload(?Player $player): ?array
    {
        if ($player === null) {
            return null;
        }

        return (new PlayerResource($player))->resolve();
    }
}
