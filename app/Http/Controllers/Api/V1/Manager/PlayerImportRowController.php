<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Http\Resources\PlayerImportRowResource;
use App\Models\PlayerImportRow;
use Illuminate\Http\Request;

class PlayerImportRowController extends V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/manager/player-import-rows",
     *     summary="List player import row snapshots",
     *     tags={"🐧 Manager: Player Imports"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(ref="#/components/parameters/page"),
     *     @OA\Parameter(
     *         name="filters[player_import_id]",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Paginated import rows")
     * )
     */
    public function index(Request $request)
    {
        $query = PlayerImportRow::query();

        $this->applyAdvancedPagination(
            $query,
            $request,
            PlayerImportRow::$searchableFields,
            PlayerImportRow::$filterableFields,
            PlayerImportRow::$sortableFields
        );

        $paginationParams = $this->getPaginationParams($request);

        if (! $request->has('sort_by')) {
            $query->orderBy('row_index')->orderBy('id_player_import_row');
        }

        $rows = $query->paginate(
            $paginationParams['per_page'],
            ['*'],
            'page',
            $paginationParams['page']
        );

        return PlayerImportRowResource::collection($rows);
    }
}
