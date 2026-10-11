<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Http\Resources\UserImportRowResource;
use App\Models\UserImportRow;
use Illuminate\Http\Request;

class UserImportRowController extends V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/manager/user-import-rows",
     *     summary="List player import row snapshots",
     *     tags={"🐧 Manager: Player Imports"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(ref="#/components/parameters/page"),
     *     @OA\Parameter(
     *         name="filters[user_import_id]",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="Paginated import rows")
     * )
     */
    public function index(Request $request)
    {
        $query = UserImportRow::query();

        $this->applyAdvancedPagination(
            $query,
            $request,
            UserImportRow::$searchableFields,
            UserImportRow::$filterableFields,
            UserImportRow::$sortableFields
        );

        $paginationParams = $this->getPaginationParams($request);

        if (! $request->has('sort_by')) {
            $query->orderBy('row_index')->orderBy('id_user_import_row');
        }

        $rows = $query->paginate(
            $paginationParams['per_page'],
            ['*'],
            'page',
            $paginationParams['page']
        );

        return UserImportRowResource::collection($rows);
    }
}
