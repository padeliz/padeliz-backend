<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Enums\PlayerCustomFieldType;
use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Http\Resources\PlayerCustomFieldResource;
use App\Models\PlayerCustomField;
use App\Traits\CachesManagerResourceReads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Player Custom Fields",
 *     description="(v1) Player custom field catalog management"
 * )
 */
class PlayerCustomFieldController extends V1BaseController
{
    use CachesManagerResourceReads;

    /**
     * @OA\Get(
     *     path="/api/v1/manager/player-custom-fields",
     *     summary="Get paginated list of player custom fields",
     *     tags={"🐧 Manager: Player Custom Fields"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(ref="#/components/parameters/page"),
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function index(Request $request)
    {
        $payload = $this->cachedManagerIndex('player-custom-fields', $request, [], function () use ($request) {
            $query = PlayerCustomField::with('options');

            $this->applyAdvancedPagination(
                $query,
                $request,
                PlayerCustomField::$searchableFields,
                PlayerCustomField::$filterableFields,
                PlayerCustomField::$sortableFields
            );

            $paginationParams = $this->getPaginationParams($request);

            if (! $request->has('sort_by')) {
                $query->orderBy('sort_order')->orderBy('id_player_custom_field');
            }

            $fields = $query->paginate(
                $paginationParams['per_page'],
                ['*'],
                'page',
                $paginationParams['page']
            );

            return PlayerCustomFieldResource::collection($fields)->response()->getData(true);
        });

        return response()->json($payload);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/player-custom-fields/{playerCustomField}",
     *     summary="Get player custom field details",
     *     tags={"🐧 Manager: Player Custom Fields"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function show(PlayerCustomField $playerCustomField): JsonResponse
    {
        $data = $this->cachedManagerShow('player-custom-fields', (int) $playerCustomField->id_player_custom_field, function () use ($playerCustomField) {
            $playerCustomField->load('options');

            return (new PlayerCustomFieldResource($playerCustomField))->resolve();
        });

        return $this->successResponse($data, 'Player custom field retrieved successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/player-custom-fields",
     *     summary="Create a player custom field",
     *     tags={"🐧 Manager: Player Custom Fields"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=201, description="Created")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:64|unique:player_custom_fields,code',
            'label' => 'required|string|max:255',
            'help_text' => 'nullable|string|max:512',
            'sort_order' => 'nullable|integer|min:0',
            'type' => 'required|string|in:' . implode(',', PlayerCustomFieldType::values()),
            'rules_json' => 'nullable|array',
            'is_required' => 'nullable|boolean',
        ]);

        $field = PlayerCustomField::create([
            ...$validated,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_required' => $validated['is_required'] ?? false,
        ]);
        $field->load('options');

        return $this->resourceResponse(
            new PlayerCustomFieldResource($field),
            'Player custom field created successfully',
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/manager/player-custom-fields/{playerCustomField}",
     *     summary="Update a player custom field",
     *     tags={"🐧 Manager: Player Custom Fields"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Updated")
     * )
     */
    public function update(Request $request, PlayerCustomField $playerCustomField): JsonResponse
    {
        $this->rejectLockedFieldChanges($request, $playerCustomField);

        $validated = $request->validate([
            'label' => 'required|string|max:255',
            'help_text' => 'nullable|string|max:512',
            'sort_order' => 'required|integer|min:0',
            'rules_json' => 'nullable|array',
            'is_required' => 'required|boolean',
        ]);

        $playerCustomField->update($validated);
        $playerCustomField->load('options');

        return $this->resourceResponse(
            new PlayerCustomFieldResource($playerCustomField),
            'Player custom field updated successfully'
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/manager/player-custom-fields/{playerCustomField}",
     *     summary="Partially update a player custom field",
     *     tags={"🐧 Manager: Player Custom Fields"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Updated")
     * )
     */
    public function patch(Request $request, PlayerCustomField $playerCustomField): JsonResponse
    {
        $this->rejectLockedFieldChanges($request, $playerCustomField);

        $validated = $request->validate([
            'label' => 'sometimes|string|max:255',
            'help_text' => 'sometimes|nullable|string|max:512',
            'sort_order' => 'sometimes|integer|min:0',
            'rules_json' => 'sometimes|nullable|array',
            'is_required' => 'sometimes|boolean',
        ]);

        $playerCustomField->update($validated);
        $playerCustomField->load('options');

        return $this->resourceResponse(
            new PlayerCustomFieldResource($playerCustomField),
            'Player custom field partially updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/player-custom-fields/{playerCustomField}",
     *     summary="Delete a player custom field",
     *     tags={"🐧 Manager: Player Custom Fields"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Deleted")
     * )
     */
    public function destroy(PlayerCustomField $playerCustomField): JsonResponse
    {
        if ($playerCustomField->values()->exists()) {
            return $this->errorResponse(
                'Player custom field cannot be deleted because it has values.',
                422,
                ['linked_data' => ['values_count' => $playerCustomField->values()->count()]]
            );
        }

        $linkedDataRelations = [
            'values' => 'values_count',
        ];

        return $this->deleteResourceWithResponse(
            $playerCustomField,
            $linkedDataRelations,
            'player custom field',
            'Player custom field deleted successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/player-custom-fields/bulk",
     *     summary="Bulk delete player custom fields",
     *     tags={"🐧 Manager: Player Custom Fields"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Deleted")
     * )
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $request->validate([
            'player_custom_field_ids' => 'required|array|min:1',
            'player_custom_field_ids.*' => 'integer',
        ]);

        $linkedDataRelations = [
            'values' => 'values_count',
        ];

        return $this->deleteBulkResourcesWithResponse(
            $request->player_custom_field_ids,
            PlayerCustomField::class,
            $linkedDataRelations,
            'player custom field',
            'Player custom fields deleted successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/player-custom-fields/{playerCustomField}/archive",
     *     summary="Archive a player custom field",
     *     tags={"🐧 Manager: Player Custom Fields"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Archived")
     * )
     */
    public function archive(PlayerCustomField $playerCustomField): JsonResponse
    {
        $playerCustomField->update(['archived_at' => time()]);
        $playerCustomField->load('options');

        return $this->resourceResponse(
            new PlayerCustomFieldResource($playerCustomField),
            'Player custom field archived successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/player-custom-fields/{playerCustomField}/unarchive",
     *     summary="Unarchive a player custom field",
     *     tags={"🐧 Manager: Player Custom Fields"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Unarchived")
     * )
     */
    public function unarchive(PlayerCustomField $playerCustomField): JsonResponse
    {
        $playerCustomField->update(['archived_at' => null]);
        $playerCustomField->load('options');

        return $this->resourceResponse(
            new PlayerCustomFieldResource($playerCustomField),
            'Player custom field unarchived successfully'
        );
    }

    private function rejectLockedFieldChanges(Request $request, PlayerCustomField $playerCustomField): void
    {
        $errors = [];

        if ($request->has('code') && $request->input('code') !== $playerCustomField->code) {
            $errors['code'][] = 'The code cannot be changed after creation.';
        }

        if ($request->has('type')) {
            $requested = $request->input('type');
            $current = $playerCustomField->type instanceof PlayerCustomFieldType
                ? $playerCustomField->type->value
                : (string) $playerCustomField->type;
            if ((string) $requested !== $current) {
                $errors['type'][] = 'The type cannot be changed after creation.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
