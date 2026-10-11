<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Http\Resources\PlayerCustomFieldValueImportResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\PlayerCustomFieldValueImport;
use App\Services\PlayerCustomField\PlayerCustomFieldValueImportFailedException;
use App\Services\PlayerCustomField\PlayerCustomFieldValueImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Player Custom Field Value Imports",
 *     description="(v1) CSV custom field value import preview, save, and history"
 * )
 */
class PlayerCustomFieldValueImportController extends V1BaseController
{
    public function __construct(
        private readonly PlayerCustomFieldValueImportService $importService,
    ) {
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/players/custom-field-values/import/preview",
     *     summary="Preview a custom field values CSV without writing",
     *     tags={"🐧 Manager: Player Custom Field Value Imports"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Preview rows")
     * )
     */
    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file',
        ]);

        try {
            $preview = $this->importService->preview($request->file('file'));
        } catch (PlayerCustomFieldValueImportFailedException $e) {
            return $this->errorResponse($e->getMessage(), $e->status, $e->errors);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), $e->getMessage());
        }

        $rows = [];
        foreach ($preview['rows'] as $row) {
            $mapping = $row['mapping'];
            $playerIds = array_values(array_unique(array_filter(array_merge(
                $mapping['player_user_id'] !== null ? [$mapping['player_user_id']] : [],
                $mapping['candidate_ids'],
                $mapping['name_match_ids'],
            ))));

            $players = empty($playerIds)
                ? collect()
                : User::query()->whereIn('id_user', $playerIds)->get()->keyBy('id_user');

            $rows[] = [
                'index' => $row['index'],
                'full_name' => $row['full_name'],
                'email' => $row['email'],
                'phone' => $row['phone'],
                'answers' => $row['answers'],
                'included' => $row['included'],
                'mapping' => [
                    'status' => $mapping['status'],
                    'matched_by' => $mapping['matched_by'],
                    'player' => $this->playerPayload(
                        $mapping['player_user_id'] !== null ? $players->get($mapping['player_user_id']) : null
                    ),
                    'candidates' => array_values(array_filter(array_map(
                        fn (int $id) => $this->playerPayload($players->get($id)),
                        $mapping['candidate_ids']
                    ))),
                    'name_matches' => array_values(array_filter(array_map(
                        fn (int $id) => $this->playerPayload($players->get($id)),
                        $mapping['name_match_ids']
                    ))),
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
     *     path="/api/v1/manager/players/custom-field-values/import",
     *     summary="Save included CSV rows as custom field values",
     *     tags={"🐧 Manager: Player Custom Field Value Imports"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=201, description="Import saved")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'format' => 'required|string|max:64',
            'filename' => 'required|string|max:255',
            'event_id' => 'nullable|integer|exists:events,id_event',
            'rows' => 'required|array|min:1',
            'rows.*.index' => 'required|integer|min:0',
            'rows.*.full_name' => 'nullable|string|max:255',
            'rows.*.email' => 'nullable|string|max:255',
            'rows.*.phone' => 'nullable|string|max:30',
            'rows.*.player_user_id' => 'required|integer',
            'rows.*.answers' => 'required|array',
        ]);

        try {
            $import = $this->importService->save($validated, (int) $request->user()->id_user);
        } catch (PlayerCustomFieldValueImportFailedException $e) {
            return $this->errorResponse($e->getMessage(), $e->status, $e->errors);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e->errors(), $e->getMessage());
        }

        return $this->resourceResponse(
            new PlayerCustomFieldValueImportResource($import),
            'Custom field values imported successfully',
            201
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/player-custom-field-value-imports",
     *     summary="List custom field value import batches",
     *     tags={"🐧 Manager: Player Custom Field Value Imports"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Paginated imports")
     * )
     */
    public function index(Request $request)
    {
        $query = PlayerCustomFieldValueImport::with(['importedByManager', 'event']);

        $this->applyAdvancedPagination(
            $query,
            $request,
            PlayerCustomFieldValueImport::$searchableFields,
            PlayerCustomFieldValueImport::$filterableFields,
            PlayerCustomFieldValueImport::$sortableFields
        );

        $paginationParams = $this->getPaginationParams($request);

        if (! $request->has('sort_by')) {
            $query->orderBy('id_player_custom_field_value_import', 'desc');
        }

        $imports = $query->paginate(
            $paginationParams['per_page'],
            ['*'],
            'page',
            $paginationParams['page']
        );

        return PlayerCustomFieldValueImportResource::collection($imports);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/player-custom-field-value-imports/{playerCustomFieldValueImport}",
     *     summary="Get a custom field value import batch",
     *     tags={"🐧 Manager: Player Custom Field Value Imports"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Import details")
     * )
     */
    public function show(PlayerCustomFieldValueImport $playerCustomFieldValueImport): JsonResponse
    {
        $playerCustomFieldValueImport->load(['rows.player', 'importedByManager', 'event']);

        return $this->resourceResponse(
            new PlayerCustomFieldValueImportResource($playerCustomFieldValueImport),
            'Custom field value import retrieved successfully'
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
