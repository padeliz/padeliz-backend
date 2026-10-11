<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Http\Resources\PlayerProfileImageImportResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\PlayerProfileImageImport;
use App\Services\PlayerProfileImageImport\PlayerProfileImageImportFailedException;
use App\Services\PlayerProfileImageImport\PlayerProfileImageImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Profile Image Imports",
 *     description="(v1) Bulk profile image import preview, save, and history"
 * )
 */
class PlayerProfileImageImportController extends V1BaseController
{
    public function __construct(
        private readonly PlayerProfileImageImportService $importService,
    ) {
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/players/profile-images/import/preview",
     *     summary="Preview profile image filenames without writing",
     *     tags={"🐧 Manager: Profile Image Imports"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"filenames"},
     *             @OA\Property(property="filenames", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     @OA\Response(response=200, description="Preview rows")
     * )
     */
    public function preview(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'filenames' => 'required|array|min:1',
            'filenames.*' => 'required|string|max:255',
        ]);

        try {
            $preview = $this->importService->preview($validated['filenames']);
        } catch (PlayerProfileImageImportFailedException $e) {
            return $this->errorResponse($e->getMessage(), $e->status, $e->errors);
        }

        $rows = [];
        foreach ($preview['rows'] as $row) {
            $mapping = $row['mapping'];
            $rows[] = [
                'index' => $row['index'],
                'filename' => $row['filename'],
                'mapping' => [
                    'status' => $mapping['status'],
                    'matched_by' => $mapping['matched_by'],
                    'player' => $this->playerPayload($mapping['player']),
                    'candidates' => [],
                    'name_matches' => array_map(
                        fn (User $player) => $this->playerPayload($player),
                        $mapping['name_matches']
                    ),
                ],
            ];
        }

        return $this->successResponse(
            [
                'max_images_per_player' => $preview['max_images_per_player'],
                'rows' => $rows,
            ],
            'Preview generated'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/players/profile-images/import",
     *     summary="Save included profile image import rows",
     *     tags={"🐧 Manager: Profile Image Imports"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=201, description="Import saved")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'original_filename' => 'required|string|max:255',
            'rows' => 'required|string',
        ]);

        $rows = json_decode($validated['rows'], true);
        if (! is_array($rows)) {
            return $this->errorResponse('Invalid rows JSON.', 422, [
                'rows' => ['Rows must be valid JSON.'],
            ]);
        }

        $payload = [
            'original_filename' => $validated['original_filename'],
            'rows' => $rows,
        ];

        $filesByIndex = [];
        $uploadedFiles = $request->file('files');
        if (is_array($uploadedFiles)) {
            foreach ($uploadedFiles as $index => $file) {
                if ($file instanceof UploadedFile) {
                    $filesByIndex[(int) $index] = $file;
                }
            }
        }
        foreach ($request->allFiles() as $key => $file) {
            if (preg_match('/^files\.(\d+)$/', (string) $key, $matches) === 1) {
                $uploaded = is_array($file) ? ($file[0] ?? null) : $file;
                if ($uploaded instanceof UploadedFile) {
                    $filesByIndex[(int) $matches[1]] = $uploaded;
                }
            }
        }

        try {
            $import = $this->importService->save(
                $payload,
                $filesByIndex,
                (int) $request->user()->id_user
            );
        } catch (PlayerProfileImageImportFailedException $e) {
            return $this->errorResponse($e->getMessage(), $e->status, $e->errors);
        }

        return $this->resourceResponse(
            new PlayerProfileImageImportResource($import),
            'Profile images imported successfully',
            201
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/player-profile-image-imports",
     *     summary="List profile image import batches",
     *     tags={"🐧 Manager: Profile Image Imports"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Paginated imports")
     * )
     */
    public function index(Request $request)
    {
        $query = PlayerProfileImageImport::with('importedByManager');

        $this->applyAdvancedPagination(
            $query,
            $request,
            PlayerProfileImageImport::$searchableFields,
            PlayerProfileImageImport::$filterableFields,
            PlayerProfileImageImport::$sortableFields
        );

        $paginationParams = $this->getPaginationParams($request);

        if (! $request->has('sort_by')) {
            $query->orderBy('id_player_profile_image_import', 'desc');
        }

        $imports = $query->paginate(
            $paginationParams['per_page'],
            ['*'],
            'page',
            $paginationParams['page']
        );

        return PlayerProfileImageImportResource::collection($imports);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/player-profile-image-imports/{playerProfileImageImport}",
     *     summary="Get a profile image import batch",
     *     tags={"🐧 Manager: Profile Image Imports"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Import details")
     * )
     */
    public function show(PlayerProfileImageImport $playerProfileImageImport): JsonResponse
    {
        $playerProfileImageImport->load(['rows', 'importedByManager']);

        return $this->resourceResponse(
            new PlayerProfileImageImportResource($playerProfileImageImport),
            'Profile image import retrieved successfully'
        );
    }

    /**
     * @return null|array<string, mixed>
     */
    private function playerPayload(?User $player): ?array
    {
        if ($player === null) {
            return null;
        }

        return (new UserResource($player))->resolve();
    }
}
