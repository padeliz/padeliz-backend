<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Resources\EditionResource;
use App\Models\Edition;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Editions",
 *     description="(v1) Edition management and operations"
 * )
 */
class EditionFinalizeController extends \App\Http\Controllers\BaseVersions\V1\V1BaseController
{
    /**
     * @OA\Post(
     *     path="/api/v1/manager/editions/{edition}/finalize",
     *     summary="Finalize an edition (locks schedule/scores/standings/replacements/participations/lottery live)",
     *     tags={"🐧 Manager: Editions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="edition", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Finalized"),
     *     @OA\Response(response=409, description="Already finalized")
     * )
     */
    public function store(Edition $edition)
    {
        if ($edition->finalized_at !== null) {
            return $this->errorResponse('Edition is already finalized.', 409);
        }

        $edition->update([
            'finalized_at' => time(),
        ]);

        $edition->load(['location', 'divisions', 'participations', 'lotteries']);

        return $this->resourceResponse(
            new EditionResource($edition),
            'Edition finalized successfully'
        );
    }
}
