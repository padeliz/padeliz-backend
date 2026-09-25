<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Http\Resources\EditionRegistrationResource;
use App\Models\Edition;
use App\Models\EditionRegistration;
use App\Traits\CachesManagerResourceReads;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Edition Registrations",
 *     description="(v1) Edition registration management"
 * )
 */
class EditionRegistrationController extends V1BaseController
{
    use CachesManagerResourceReads;

    /**
     * @OA\Get(
     *     path="/api/v1/manager/edition-registrations",
     *     summary="Get all edition registrations",
     *     tags={"🐧 Manager: Edition Registrations"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(ref="#/components/parameters/page"),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer", default=15, maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="filters",
     *         in="query",
     *         required=false,
     *         style="deepObject",
     *         explode=true,
     *         @OA\Schema(
     *             type="object",
     *             additionalProperties=false,
     *             @OA\Property(property="edition_id", type="string"),
     *             @OA\Property(property="player_id", type="string"),
     *             @OA\Property(property="edition_division_id", type="string"),
     *             @OA\Property(property="is_minor", type="string"),
     *             @OA\Property(property="is_student", type="string"),
     *             @OA\Property(property="discount", type="string"),
     *             @OA\Property(property="payment", type="string")
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         required=false,
     *         style="deepObject",
     *         explode=true,
     *         @OA\Schema(
     *             type="object",
     *             additionalProperties=false,
     *             @OA\Property(property="id_registration", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="edition_id", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="player_id", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="edition_division_id", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="is_minor", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="is_student", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="discount", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="payment", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="inserted_at", type="string", enum={"asc", "desc"}),
     *             @OA\Property(property="updated_at", type="string", enum={"asc", "desc"})
     *         )
     *     ),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function index(Request $request)
    {
        $payload = $this->cachedManagerIndex('edition-registrations', $request, ['edition_id'], function () use ($request) {
            $query = EditionRegistration::with(['player', 'editionDivision', 'edition']);

            $this->applyAdvancedPagination(
                $query,
                $request,
                EditionRegistration::$searchableFields,
                EditionRegistration::$filterableFields,
                EditionRegistration::$sortableFields
            );

            $paginationParams = $this->getPaginationParams($request);

            if (! $request->has('sort_by')) {
                $query->orderBy('id_registration', 'desc');
            }

            $registrations = $query->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);

            return EditionRegistrationResource::collection($registrations)->response()->getData(true);
        });

        return response()->json($payload);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/edition-registrations/{editionRegistration}",
     *     summary="Get edition registration details",
     *     tags={"🐧 Manager: Edition Registrations"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="editionRegistration", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=404, description="Registration not found")
     * )
     */
    public function show(EditionRegistration $editionRegistration)
    {
        $editionRegistration->load(['player', 'editionDivision', 'edition']);

        return $this->resourceResponse(
            new EditionRegistrationResource($editionRegistration),
            'Registration details retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/edition-registrations",
     *     summary="Create edition registration",
     *     tags={"🐧 Manager: Edition Registrations"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"edition_id", "player_id"},
     *             @OA\Property(property="edition_id", type="integer"),
     *             @OA\Property(property="player_id", type="integer"),
     *             @OA\Property(property="edition_division_id", type="integer", nullable=true),
     *             @OA\Property(property="is_minor", type="boolean"),
     *             @OA\Property(property="is_student", type="boolean"),
     *             @OA\Property(property="discount", type="integer", nullable=true),
     *             @OA\Property(property="payment", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Registration created"),
     *     @OA\Response(response=409, description="Edition is finalized"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->storeRules($request->input('edition_id')));

        $edition = Edition::query()->find($validated['edition_id']);
        if ($edition?->finalized_at !== null) {
            return $this->errorResponse('Registrations cannot be created after finalize.', 409);
        }

        $editionDivisionId = $this->nullableInt($validated, 'edition_division_id');
        $xor = EditionRegistration::xorConflict(
            (int) $validated['edition_id'],
            (int) $validated['player_id'],
            $editionDivisionId
        );
        if ($xor !== null) {
            return $this->validationErrorResponse($xor);
        }

        $validated['edition_division_id'] = $editionDivisionId;
        $validated['is_minor'] = $validated['is_minor'] ?? false;
        $validated['is_student'] = $validated['is_student'] ?? false;
        $validated['discount'] = $validated['discount'] ?? 0;
        $validated['payment'] = $validated['payment'] ?? 0;

        $registration = EditionRegistration::create($validated);
        $registration->load(['player', 'editionDivision', 'edition']);

        return $this->resourceResponse(
            new EditionRegistrationResource($registration),
            'Registration created successfully',
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/manager/edition-registrations/{editionRegistration}",
     *     summary="Update edition registration (complete replacement)",
     *     tags={"🐧 Manager: Edition Registrations"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="editionRegistration", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"edition_id", "player_id"},
     *             @OA\Property(property="edition_id", type="integer"),
     *             @OA\Property(property="player_id", type="integer"),
     *             @OA\Property(property="edition_division_id", type="integer", nullable=true),
     *             @OA\Property(property="is_minor", type="boolean"),
     *             @OA\Property(property="is_student", type="boolean"),
     *             @OA\Property(property="discount", type="integer", nullable=true),
     *             @OA\Property(property="payment", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Registration updated"),
     *     @OA\Response(response=409, description="Edition is finalized"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, EditionRegistration $editionRegistration)
    {
        $editionRegistration->loadMissing('edition');
        if ($editionRegistration->edition?->finalized_at !== null) {
            return $this->errorResponse('Registrations cannot be updated after finalize.', 409);
        }

        $validated = $request->validate($this->updateRules($request->input('edition_id')));

        $editionDivisionId = array_key_exists('edition_division_id', $validated)
            ? $this->nullableInt($validated, 'edition_division_id')
            : null;
        $xor = EditionRegistration::xorConflict(
            (int) $validated['edition_id'],
            (int) $validated['player_id'],
            $editionDivisionId,
            (int) $editionRegistration->id_registration
        );
        if ($xor !== null) {
            return $this->validationErrorResponse($xor);
        }

        $validated['edition_division_id'] = $editionDivisionId;
        $editionRegistration->update($validated);
        $editionRegistration->load(['player', 'editionDivision', 'edition']);

        return $this->resourceResponse(
            new EditionRegistrationResource($editionRegistration),
            'Registration updated successfully'
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/manager/edition-registrations/{editionRegistration}",
     *     summary="Partially update edition registration",
     *     tags={"🐧 Manager: Edition Registrations"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="editionRegistration", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="edition_id", type="integer"),
     *             @OA\Property(property="player_id", type="integer"),
     *             @OA\Property(property="edition_division_id", type="integer", nullable=true),
     *             @OA\Property(property="is_minor", type="boolean"),
     *             @OA\Property(property="is_student", type="boolean"),
     *             @OA\Property(property="discount", type="integer", nullable=true),
     *             @OA\Property(property="payment", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Registration partially updated"),
     *     @OA\Response(response=409, description="Edition is finalized"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function patch(Request $request, EditionRegistration $editionRegistration)
    {
        $editionRegistration->loadMissing('edition');
        if ($editionRegistration->edition?->finalized_at !== null) {
            return $this->errorResponse('Registrations cannot be updated after finalize.', 409);
        }

        $targetEditionId = $request->input('edition_id', $editionRegistration->edition_id);
        $validated = $request->validate($this->patchRules($targetEditionId));

        $editionId = (int) ($validated['edition_id'] ?? $editionRegistration->edition_id);
        $playerId = (int) ($validated['player_id'] ?? $editionRegistration->player_id);
        $editionDivisionId = array_key_exists('edition_division_id', $validated)
            ? $this->nullableInt($validated, 'edition_division_id')
            : ($editionRegistration->edition_division_id !== null ? (int) $editionRegistration->edition_division_id : null);

        $xor = EditionRegistration::xorConflict(
            $editionId,
            $playerId,
            $editionDivisionId,
            (int) $editionRegistration->id_registration
        );
        if ($xor !== null) {
            return $this->validationErrorResponse($xor);
        }

        if (array_key_exists('edition_division_id', $validated)) {
            $validated['edition_division_id'] = $editionDivisionId;
        }

        $editionRegistration->update($validated);
        $editionRegistration->load(['player', 'editionDivision', 'edition']);

        return $this->resourceResponse(
            new EditionRegistrationResource($editionRegistration),
            'Registration partially updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/edition-registrations/{editionRegistration}",
     *     summary="Delete edition registration",
     *     tags={"🐧 Manager: Edition Registrations"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="editionRegistration", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Registration deleted"),
     *     @OA\Response(response=409, description="Edition is finalized")
     * )
     */
    public function destroy(EditionRegistration $editionRegistration)
    {
        $editionRegistration->loadMissing('edition');
        if ($editionRegistration->edition?->finalized_at !== null) {
            return $this->errorResponse('Registrations cannot be deleted after finalize.', 409);
        }

        return $this->deleteResourceWithResponse(
            $editionRegistration,
            [],
            'registration',
            'Registration deleted successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/edition-registrations/bulk",
     *     summary="Bulk delete edition registrations",
     *     tags={"🐧 Manager: Edition Registrations"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"registration_ids"},
     *             @OA\Property(property="registration_ids", type="array", @OA\Items(type="integer"))
     *         )
     *     ),
     *     @OA\Response(response=200, description="All registrations deleted"),
     *     @OA\Response(response=207, description="Some registrations failed"),
     *     @OA\Response(response=409, description="All registrations failed")
     * )
     */
    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'registration_ids' => 'required|array|min:1',
            'registration_ids.*' => 'integer',
        ]);

        $results = [
            'successful' => [],
            'failed' => [],
            'total_requested' => count($request->registration_ids),
            'total_successful' => 0,
            'total_failed' => 0,
        ];

        foreach ($request->registration_ids as $registrationId) {
            try {
                $registration = EditionRegistration::find($registrationId);

                if (! $registration) {
                    $results['failed'][] = [
                        'id' => $registrationId,
                        'reason' => 'Resource not found',
                    ];
                    $results['total_failed']++;

                    continue;
                }

                $registration->loadMissing('edition');
                if ($registration->edition?->finalized_at !== null) {
                    $results['failed'][] = [
                        'id' => $registrationId,
                        'reason' => 'Registrations cannot be deleted after finalize.',
                    ];
                    $results['total_failed']++;

                    continue;
                }

                $registration->delete();

                $results['successful'][] = [
                    'id' => $registrationId,
                    'name' => $registration->player->name ?? $registrationId,
                ];
                $results['total_successful']++;
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'id' => $registrationId,
                    'reason' => 'Unexpected error: ' . $e->getMessage(),
                ];
                $results['total_failed']++;
            }
        }

        if ($results['total_failed'] === 0) {
            return $this->successResponse($results, 'Registrations deleted successfully');
        }

        if ($results['total_successful'] === 0) {
            return $this->errorResponse('All registrations failed to delete', 409, $results);
        }

        return $this->errorResponse('Some registrations failed to delete', 207, $results);
    }

    /**
     * @return array<string, mixed>
     */
    private function storeRules(mixed $editionId): array
    {
        return [
            'edition_id' => 'required|exists:editions,id_edition',
            'player_id' => 'required|exists:players,id_player',
            'edition_division_id' => $this->editionDivisionIdRules($editionId),
            'is_minor' => 'boolean',
            'is_student' => 'boolean',
            'discount' => 'nullable|integer|min:0',
            'payment' => 'nullable|integer|min:0',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function updateRules(mixed $editionId): array
    {
        return [
            'edition_id' => 'required|exists:editions,id_edition',
            'player_id' => 'required|exists:players,id_player',
            'edition_division_id' => $this->editionDivisionIdRules($editionId),
            'is_minor' => 'required|boolean',
            'is_student' => 'required|boolean',
            'discount' => 'nullable|integer|min:0',
            'payment' => 'required|integer|min:0',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function patchRules(mixed $editionId): array
    {
        return [
            'edition_id' => 'sometimes|exists:editions,id_edition',
            'player_id' => 'sometimes|exists:players,id_player',
            'edition_division_id' => $this->editionDivisionIdRules($editionId, sometimes: true),
            'is_minor' => 'boolean',
            'is_student' => 'boolean',
            'discount' => 'nullable|integer|min:0',
            'payment' => 'nullable|integer|min:0',
        ];
    }

    /**
     * @return list<mixed>
     */
    private function editionDivisionIdRules(mixed $editionId, bool $sometimes = false): array
    {
        $rules = [];
        if ($sometimes) {
            $rules[] = 'sometimes';
        }
        $rules[] = 'nullable';
        $rules[] = Rule::exists('edition_divisions', 'id_edition_division')
            ->where('edition_id', $editionId);

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function nullableInt(array $validated, string $key): ?int
    {
        if (! array_key_exists($key, $validated) || $validated[$key] === null) {
            return null;
        }

        return (int) $validated[$key];
    }
}
