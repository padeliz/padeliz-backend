<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Enums\Benefit\BenefitAmountType;
use App\Enums\Benefit\BenefitConditionType;
use App\Enums\Benefit\BenefitExclusivity;
use App\Enums\Benefit\BenefitScopeType;
use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Http\Resources\BenefitDefinitionResource;
use App\Models\BenefitDefinition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Benefit Definitions",
 *     description="(v1) Benefit catalog management"
 * )
 */
class BenefitDefinitionController extends V1BaseController
{
    /**
     * @OA\Get(
     *     path="/api/v1/manager/benefit-definitions",
     *     summary="List benefit definitions",
     *     tags={"🐧 Manager: Benefit Definitions"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function index(Request $request)
    {
        $query = BenefitDefinition::with(['conditions', 'events', 'club']);

        $this->applyAdvancedPagination(
            $query,
            $request,
            BenefitDefinition::$searchableFields,
            BenefitDefinition::$filterableFields,
            BenefitDefinition::$sortableFields
        );

        $paginationParams = $this->getPaginationParams($request);

        if (! $request->has('sort_by')) {
            $query->orderBy('priority')->orderBy('id_benefit');
        }

        $benefits = $query->paginate($paginationParams['per_page'], ['*'], 'page', $paginationParams['page']);

        return BenefitDefinitionResource::collection($benefits);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/benefit-definitions/{benefitDefinition}",
     *     summary="Get benefit definition details",
     *     tags={"🐧 Manager: Benefit Definitions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="benefitDefinition", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=404, description="Not found")
     * )
     */
    public function show(BenefitDefinition $benefitDefinition)
    {
        $benefitDefinition->load(['conditions', 'events', 'club']);

        return $this->resourceResponse(
            new BenefitDefinitionResource($benefitDefinition),
            'Benefit definition retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/benefit-definitions",
     *     summary="Create a benefit definition",
     *     tags={"🐧 Manager: Benefit Definitions"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=201, description="Created"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->storeRules());

        $benefit = DB::transaction(function () use ($validated) {
            $conditions = $validated['conditions'] ?? [];
            $editionIds = $validated['event_ids'] ?? [];
            unset($validated['conditions'], $validated['event_ids']);

            $validated['is_system'] = false;
            if (! array_key_exists('is_active', $validated)) {
                $validated['is_active'] = false;
            }

            $benefit = BenefitDefinition::query()->create($validated);
            $this->syncConditions($benefit, $conditions);
            $this->syncEditions($benefit, $editionIds);

            return $benefit;
        });

        $benefit->load(['conditions', 'events', 'club']);

        return $this->resourceResponse(
            new BenefitDefinitionResource($benefit),
            'Benefit definition created successfully',
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/manager/benefit-definitions/{benefitDefinition}",
     *     summary="Update a benefit definition",
     *     tags={"🐧 Manager: Benefit Definitions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="benefitDefinition", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Updated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, BenefitDefinition $benefitDefinition)
    {
        $validated = $request->validate($this->updateRules($benefitDefinition));

        DB::transaction(function () use ($validated, $benefitDefinition) {
            $conditions = $validated['conditions'] ?? [];
            $editionIds = $validated['event_ids'] ?? [];
            unset($validated['conditions'], $validated['event_ids'], $validated['is_system']);

            $benefitDefinition->fill($validated);
            $benefitDefinition->save();
            $this->syncConditions($benefitDefinition, $conditions);
            $this->syncEditions($benefitDefinition, $editionIds);
        });

        $benefitDefinition->load(['conditions', 'events', 'club']);

        return $this->resourceResponse(
            new BenefitDefinitionResource($benefitDefinition),
            'Benefit definition updated successfully'
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/manager/benefit-definitions/{benefitDefinition}",
     *     summary="Partially update a benefit definition",
     *     tags={"🐧 Manager: Benefit Definitions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="benefitDefinition", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Updated")
     * )
     */
    public function patch(Request $request, BenefitDefinition $benefitDefinition)
    {
        $validated = $request->validate($this->patchRules($benefitDefinition));

        DB::transaction(function () use ($validated, $benefitDefinition, $request) {
            $hasConditions = array_key_exists('conditions', $validated);
            $hasEditionIds = array_key_exists('event_ids', $validated);
            $conditions = $validated['conditions'] ?? [];
            $editionIds = $validated['event_ids'] ?? [];
            unset($validated['conditions'], $validated['event_ids'], $validated['is_system']);

            if ($validated !== []) {
                $benefitDefinition->fill($validated);
                $benefitDefinition->save();
            }

            if ($hasConditions) {
                $this->syncConditions($benefitDefinition, $conditions);
            }

            if ($hasEditionIds || $request->has('scope_type')) {
                $this->syncEditions($benefitDefinition->fresh(), $editionIds);
            }
        });

        $benefitDefinition->load(['conditions', 'events', 'club']);

        return $this->resourceResponse(
            new BenefitDefinitionResource($benefitDefinition),
            'Benefit definition partially updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/benefit-definitions/{benefitDefinition}",
     *     summary="Delete a benefit definition",
     *     tags={"🐧 Manager: Benefit Definitions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="benefitDefinition", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deleted"),
     *     @OA\Response(response=422, description="System benefit cannot be deleted")
     * )
     */
    public function destroy(BenefitDefinition $benefitDefinition)
    {
        if ($benefitDefinition->is_system) {
            return $this->errorResponse('System benefits cannot be deleted.', 422);
        }

        $benefitDefinition->delete();

        return $this->successResponse(null, 'Benefit definition deleted successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/benefit-definitions/{benefitDefinition}/activate",
     *     summary="Activate a benefit definition",
     *     tags={"🐧 Manager: Benefit Definitions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="benefitDefinition", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Activated")
     * )
     */
    public function activate(BenefitDefinition $benefitDefinition)
    {
        $benefitDefinition->is_active = true;
        $benefitDefinition->save();
        $benefitDefinition->load(['conditions', 'events', 'club']);

        return $this->resourceResponse(
            new BenefitDefinitionResource($benefitDefinition),
            'Benefit definition activated'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/benefit-definitions/{benefitDefinition}/deactivate",
     *     summary="Deactivate a benefit definition",
     *     tags={"🐧 Manager: Benefit Definitions"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="benefitDefinition", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Deactivated")
     * )
     */
    public function deactivate(BenefitDefinition $benefitDefinition)
    {
        $benefitDefinition->is_active = false;
        $benefitDefinition->save();
        $benefitDefinition->load(['conditions', 'events', 'club']);

        return $this->resourceResponse(
            new BenefitDefinitionResource($benefitDefinition),
            'Benefit definition deactivated'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function storeRules(): array
    {
        return array_merge($this->baseRules(), [
            'slug' => 'required|string|max:64|unique:benefit_definitions,slug',
            'name' => 'required|string|max:128',
            'amount_type' => ['required', Rule::enum(BenefitAmountType::class)],
            'amount_value' => 'required|integer|min:0',
            'scope_type' => ['required', Rule::enum(BenefitScopeType::class)],
            'exclusivity' => ['sometimes', Rule::enum(BenefitExclusivity::class)],
            'conditions' => 'sometimes|array',
            'event_ids' => 'sometimes|array',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function updateRules(BenefitDefinition $benefitDefinition): array
    {
        return array_merge($this->baseRules(), [
            'slug' => [
                'required',
                'string',
                'max:64',
                Rule::unique('benefit_definitions', 'slug')->ignore($benefitDefinition->id_benefit, 'id_benefit'),
            ],
            'name' => 'required|string|max:128',
            'amount_type' => ['required', Rule::enum(BenefitAmountType::class)],
            'amount_value' => 'required|integer|min:0',
            'scope_type' => ['required', Rule::enum(BenefitScopeType::class)],
            'exclusivity' => ['required', Rule::enum(BenefitExclusivity::class)],
            'conditions' => 'sometimes|array',
            'event_ids' => 'sometimes|array',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function patchRules(BenefitDefinition $benefitDefinition): array
    {
        return array_merge($this->baseRules(sometimes: true), [
            'slug' => [
                'sometimes',
                'string',
                'max:64',
                Rule::unique('benefit_definitions', 'slug')->ignore($benefitDefinition->id_benefit, 'id_benefit'),
            ],
            'name' => 'sometimes|string|max:128',
            'amount_type' => ['sometimes', Rule::enum(BenefitAmountType::class)],
            'amount_value' => 'sometimes|integer|min:0',
            'scope_type' => ['sometimes', Rule::enum(BenefitScopeType::class)],
            'exclusivity' => ['sometimes', Rule::enum(BenefitExclusivity::class)],
            'conditions' => 'sometimes|array',
            'event_ids' => 'sometimes|array',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function baseRules(bool $sometimes = false): array
    {
        $prefix = $sometimes ? 'sometimes|' : '';

        return [
            'description' => $prefix.'nullable|string',
            'is_active' => $prefix.'boolean',
            'max_discount_ron' => $prefix.'nullable|integer|min:0',
            'club_id' => $prefix.'nullable|integer|exists:clubs,id_club',
            'valid_from' => $prefix.'nullable|integer|min:0',
            'valid_until' => $prefix.'nullable|integer|min:0',
            'player_optional' => $prefix.'boolean',
            'stack_group' => $prefix.'nullable|string|max:64',
            'priority' => $prefix.'integer|min:0',
            'max_redemptions_per_player' => $prefix.'nullable|integer|min:0',
            'max_redemptions_global' => $prefix.'nullable|integer|min:0',
            'max_redemptions_per_event' => $prefix.'nullable|integer|min:0',
            'conditions.*.condition_type' => ['required_with:conditions', Rule::enum(BenefitConditionType::class)],
            'conditions.*.parameters' => 'nullable|array',
            'event_ids.*' => 'integer|exists:events,id_event',
        ];
    }

    /**
     * @param  list<array{condition_type: string, parameters?: array<string, mixed>|null}>  $conditions
     */
    private function syncConditions(BenefitDefinition $benefit, array $conditions): void
    {
        $benefit->conditions()->delete();

        foreach ($conditions as $condition) {
            $benefit->conditions()->create([
                'condition_type' => $condition['condition_type'],
                'parameters' => $condition['parameters'] ?? null,
            ]);
        }
    }

    /**
     * @param  list<int>  $editionIds
     */
    private function syncEditions(BenefitDefinition $benefit, array $editionIds): void
    {
        $scope = $benefit->scope_type instanceof BenefitScopeType
            ? $benefit->scope_type
            : BenefitScopeType::tryFrom((string) $benefit->scope_type);

        if ($scope !== BenefitScopeType::PadelEvent) {
            $benefit->events()->detach();

            return;
        }

        $now = time();
        $payload = [];
        foreach (array_values(array_unique(array_map('intval', $editionIds))) as $editionId) {
            $payload[$editionId] = [
                'inserted_at' => $now,
                'updated_at' => $now,
            ];
        }

        $benefit->events()->sync($payload);
    }
}
