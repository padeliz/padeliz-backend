<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Http\Resources\PlayerProfileImageImportRowResource;
use App\Models\PlayerProfileImageImportRow;
use Illuminate\Http\Request;

class PlayerProfileImageImportRowController extends V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/manager/player-profile-image-import-rows",
     *     summary="List profile image import row snapshots",
     *     tags={"🐧 Manager: Profile Image Imports"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Paginated import rows")
     * )
     */
    public function index(Request $request)
    {
        $query = PlayerProfileImageImportRow::query();

        $this->applyAdvancedPagination(
            $query,
            $request,
            PlayerProfileImageImportRow::$searchableFields,
            PlayerProfileImageImportRow::$filterableFields,
            PlayerProfileImageImportRow::$sortableFields
        );

        $paginationParams = $this->getPaginationParams($request);

        if (! $request->has('sort_by')) {
            $query->orderBy('row_index')->orderBy('id_player_profile_image_import_row');
        }

        $rows = $query->paginate(
            $paginationParams['per_page'],
            ['*'],
            'page',
            $paginationParams['page']
        );

        return PlayerProfileImageImportRowResource::collection($rows);
    }
}
