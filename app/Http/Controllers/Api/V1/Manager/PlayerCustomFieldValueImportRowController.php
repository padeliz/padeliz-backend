<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Http\Resources\PlayerCustomFieldValueImportRowResource;
use App\Models\PlayerCustomFieldValueImportRow;
use Illuminate\Http\Request;

class PlayerCustomFieldValueImportRowController extends V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/manager/player-custom-field-value-import-rows",
     *     summary="List custom field value import row snapshots",
     *     tags={"🐧 Manager: Player Custom Field Value Imports"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Paginated import rows")
     * )
     */
    public function index(Request $request)
    {
        $query = PlayerCustomFieldValueImportRow::query()->with('player');

        $this->applyAdvancedPagination(
            $query,
            $request,
            PlayerCustomFieldValueImportRow::$searchableFields,
            PlayerCustomFieldValueImportRow::$filterableFields,
            PlayerCustomFieldValueImportRow::$sortableFields
        );

        $paginationParams = $this->getPaginationParams($request);

        if (! $request->has('sort_by')) {
            $query->orderBy('row_index')->orderBy('id_player_custom_field_value_import_row');
        }

        $rows = $query->paginate(
            $paginationParams['per_page'],
            ['*'],
            'page',
            $paginationParams['page']
        );

        return PlayerCustomFieldValueImportRowResource::collection($rows);
    }
}
