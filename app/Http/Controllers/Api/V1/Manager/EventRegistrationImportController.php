<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Http\Resources\PlayerResource;
use App\Models\PadelEvent;
use App\Models\Player;
use App\Services\EventRegistrationImport\EventRegistrationImportFailedException;
use App\Services\EventRegistrationImport\EventRegistrationImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="🐧 Manager: PadelEvent Registration Imports",
 *     description="(v1) CSV edition registration import preview and save"
 * )
 */
class EventRegistrationImportController extends V1BaseController
{
    public function __construct(
        private readonly EventRegistrationImportService $importService
    ) {
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/events/{event}/registrations/import/preview",
     *     summary="Preview a Playtomic CSV registration import without writing",
     *     tags={"🐧 Manager: PadelEvent Registration Imports"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="event", in="path", required=true, @OA\Schema(type="integer")),
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
     *     @OA\Response(response=409, description="PadelEvent is finalized", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function preview(Request $request, PadelEvent $event): JsonResponse
    {
        if ($event->finalized_at !== null) {
            return $this->errorResponse('PadelEvent is finalized.', 409);
        }

        $request->validate([
            'file' => 'required|file',
        ]);

        try {
            $preview = $this->importService->preview($event, $request->file('file'));
        } catch (EventRegistrationImportFailedException $e) {
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
                'already_registered' => $row['already_registered'],
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
     *     path="/api/v1/manager/events/{event}/registrations/import",
     *     summary="Save mapped CSV rows as edition registrations",
     *     tags={"🐧 Manager: PadelEvent Registration Imports"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="event", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"format", "event_division_id", "rows"},
     *             @OA\Property(property="format", type="string", example="playtomic"),
     *             @OA\Property(property="event_division_id", type="integer", description="Target edition division for imported registrations"),
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
     *                     @OA\Property(property="player_id", type="integer", nullable=true),
     *                     @OA\Property(property="status", type="string", nullable=true)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, description="Import saved"),
     *     @OA\Response(response=409, description="PadelEvent is finalized", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse")),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ApiErrorResponse"))
     * )
     */
    public function store(Request $request, PadelEvent $event): JsonResponse
    {
        if ($event->finalized_at !== null) {
            return $this->errorResponse('PadelEvent is finalized.', 409);
        }

        $validated = $request->validate([
            'format' => 'required|string|max:32',
            'event_division_id' => 'required|integer|exists:event_divisions,id_event_division',
            'rows' => 'required|array|min:1',
            'rows.*.index' => 'required|integer|min:0',
            'rows.*.first_name' => 'nullable|string|max:50',
            'rows.*.middle_name' => 'nullable|string|max:50',
            'rows.*.last_name' => 'nullable|string|max:50',
            'rows.*.email' => 'nullable|string|max:255',
            'rows.*.phone' => 'nullable|string|max:30',
            'rows.*.player_id' => 'nullable|integer',
            'rows.*.status' => 'nullable|string|max:32',
        ]);

        try {
            $result = $this->importService->save($event, $validated);
        } catch (EventRegistrationImportFailedException $e) {
            return $this->errorResponse($e->getMessage(), $e->status, $e->errors);
        }

        return $this->successResponse(
            $result,
            'Registrations imported successfully',
            201
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
