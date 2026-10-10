<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Models\Player;
use App\Services\Pdf\PdfCachedImageGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Cached PDF Pair Images",
 *     description="(v1) Cached MATCHES couple composites used by poster PDFs"
 * )
 */
class CachedPdfPairImageController extends V1BaseController
{
    public function __construct(
        private readonly PdfCachedImageGenerator $pdfCachedImageGenerator = new PdfCachedImageGenerator(),
    ) {
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/cached-pdf-pair-images",
     *     summary="Generate or refresh the cached PDF couple image for two players",
     *     description="Players without a usable profile photo get an initials placeholder half instead of an error.",
     *     tags={"🐧 Manager: Cached PDF Pair Images"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"player_1_id", "player_2_id", "player_1_is_collecting_points", "player_2_is_collecting_points"},
     *             @OA\Property(property="player_1_id", type="integer"),
     *             @OA\Property(property="player_2_id", type="integer"),
     *             @OA\Property(property="player_1_is_collecting_points", type="boolean"),
     *             @OA\Property(property="player_2_is_collecting_points", type="boolean")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Cached pair image URL"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=403, description="Access denied. Required user type: manager"),
     *     @OA\Response(response=422, description="Validation error or pair image generation failure")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'player_1_id' => ['required', 'integer', 'different:player_2_id', 'exists:players,id_player'],
            'player_2_id' => ['required', 'integer', 'exists:players,id_player'],
            'player_1_is_collecting_points' => ['required', 'boolean'],
            'player_2_is_collecting_points' => ['required', 'boolean'],
        ]);

        $player1 = Player::query()->findOrFail((int) $validated['player_1_id']);
        $player2 = Player::query()->findOrFail((int) $validated['player_2_id']);

        try {
            $url = $this->pdfCachedImageGenerator->generatePairImageUrl(
                $player1,
                $player2,
                (bool) $validated['player_1_is_collecting_points'],
                (bool) $validated['player_2_is_collecting_points'],
            );
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Failed to generate PDF pair image.',
                'player_ids' => [(int) $player1->id_player, (int) $player2->id_player],
                'code' => 'pdf_pair_image_failed',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'couple_image_url' => $url,
            ],
        ]);
    }
}
