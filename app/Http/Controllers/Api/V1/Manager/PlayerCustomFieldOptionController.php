<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Http\Resources\PlayerCustomFieldOptionResource;
use App\Models\PlayerCustomField;
use App\Models\PlayerCustomFieldOption;
use App\Traits\CachesManagerResourceReads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Player Custom Field Options",
 *     description="(v1) Options for select-type player custom fields"
 * )
 */
class PlayerCustomFieldOptionController extends V1BaseController
{
    use CachesManagerResourceReads;
    /**
     * @OA\Get(
     *     path="/api/v1/manager/player-custom-fields/{playerCustomField}/options",
     *     summary="List options for a player custom field",
     *     tags={"🐧 Manager: Player Custom Field Options"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function index(Request $request, PlayerCustomField $playerCustomField)
    {
        $this->assertSelectField($playerCustomField);

        $request->merge([
            'filters' => array_merge(
                is_array($request->input('filters')) ? $request->input('filters') : [],
                ['player_custom_field_id' => $playerCustomField->id_player_custom_field],
            ),
        ]);

        $payload = $this->cachedManagerIndex('player-custom-field-options', $request, ['player_custom_field_id'], function () use ($playerCustomField) {
            $options = $playerCustomField->options()->get();

            return PlayerCustomFieldOptionResource::collection($options)->response()->getData(true);
        });

        return response()->json($payload);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/player-custom-fields/{playerCustomField}/options",
     *     summary="Create an option for a player custom field",
     *     tags={"🐧 Manager: Player Custom Field Options"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=201, description="Created")
     * )
     */
    public function store(Request $request, PlayerCustomField $playerCustomField): JsonResponse
    {
        $this->assertSelectField($playerCustomField);

        $validated = $request->validate([
            'code' => 'nullable|string|max:64',
            'label' => 'required|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $code = trim((string) ($validated['code'] ?? ''));
        if ($code === '') {
            $code = $this->uniqueCodeFromLabel($playerCustomField, $validated['label']);
        }

        $exists = PlayerCustomFieldOption::query()
            ->where('player_custom_field_id', $playerCustomField->id_player_custom_field)
            ->where('code', $code)
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages([
                'code' => ['The code has already been taken for this field.'],
            ]);
        }

        $option = PlayerCustomFieldOption::create([
            'player_custom_field_id' => $playerCustomField->id_player_custom_field,
            'code' => $code,
            'label' => $validated['label'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return $this->resourceResponse(
            new PlayerCustomFieldOptionResource($option),
            'Player custom field option created successfully',
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/manager/player-custom-fields/{playerCustomField}/options/{playerCustomFieldOption}",
     *     summary="Update a player custom field option",
     *     tags={"🐧 Manager: Player Custom Field Options"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Updated")
     * )
     */
    public function update(
        Request $request,
        PlayerCustomField $playerCustomField,
        PlayerCustomFieldOption $playerCustomFieldOption,
    ): JsonResponse {
        $this->assertOptionBelongsToField($playerCustomField, $playerCustomFieldOption);
        $this->rejectLockedOptionChanges($request, $playerCustomFieldOption);

        $validated = $request->validate([
            'label' => 'required|string|max:255',
            'sort_order' => 'required|integer|min:0',
            'is_active' => 'required|boolean',
        ]);

        $playerCustomFieldOption->update($validated);

        return $this->resourceResponse(
            new PlayerCustomFieldOptionResource($playerCustomFieldOption),
            'Player custom field option updated successfully'
        );
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/manager/player-custom-fields/{playerCustomField}/options/{playerCustomFieldOption}",
     *     summary="Partially update a player custom field option",
     *     tags={"🐧 Manager: Player Custom Field Options"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Updated")
     * )
     */
    public function patch(
        Request $request,
        PlayerCustomField $playerCustomField,
        PlayerCustomFieldOption $playerCustomFieldOption,
    ): JsonResponse {
        $this->assertOptionBelongsToField($playerCustomField, $playerCustomFieldOption);
        $this->rejectLockedOptionChanges($request, $playerCustomFieldOption);

        $validated = $request->validate([
            'label' => 'sometimes|string|max:255',
            'sort_order' => 'sometimes|integer|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        $playerCustomFieldOption->update($validated);

        return $this->resourceResponse(
            new PlayerCustomFieldOptionResource($playerCustomFieldOption),
            'Player custom field option partially updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/manager/player-custom-fields/{playerCustomField}/options/{playerCustomFieldOption}",
     *     summary="Delete a player custom field option",
     *     tags={"🐧 Manager: Player Custom Field Options"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Deleted")
     * )
     */
    public function destroy(
        PlayerCustomField $playerCustomField,
        PlayerCustomFieldOption $playerCustomFieldOption,
    ): JsonResponse {
        $this->assertOptionBelongsToField($playerCustomField, $playerCustomFieldOption);

        if ($playerCustomFieldOption->valueOptions()->exists()) {
            return $this->errorResponse(
                'Player custom field option cannot be deleted because it is referenced by values.',
                422,
                ['linked_data' => ['value_options_count' => $playerCustomFieldOption->valueOptions()->count()]]
            );
        }

        return $this->deleteResourceWithResponse(
            $playerCustomFieldOption,
            ['valueOptions' => 'value_options_count'],
            'player custom field option',
            'Player custom field option deleted successfully'
        );
    }

    private function assertSelectField(PlayerCustomField $playerCustomField): void
    {
        if (! $playerCustomField->type->isSelect()) {
            throw ValidationException::withMessages([
                'player_custom_field_id' => ['Options are only supported for select-type fields.'],
            ]);
        }
    }

    private function assertOptionBelongsToField(
        PlayerCustomField $playerCustomField,
        PlayerCustomFieldOption $playerCustomFieldOption,
    ): void {
        $this->assertSelectField($playerCustomField);

        if ((int) $playerCustomFieldOption->player_custom_field_id !== (int) $playerCustomField->id_player_custom_field) {
            abort(404);
        }
    }

    private function rejectLockedOptionChanges(Request $request, PlayerCustomFieldOption $option): void
    {
        if ($request->has('code') && $request->input('code') !== $option->code) {
            throw ValidationException::withMessages([
                'code' => ['The code cannot be changed after creation.'],
            ]);
        }
    }

    private function uniqueCodeFromLabel(PlayerCustomField $field, string $label): string
    {
        $base = Str::slug($label);
        if ($base === '') {
            $base = 'option';
        }

        $code = $base;
        $suffix = 1;
        while (
            PlayerCustomFieldOption::query()
                ->where('player_custom_field_id', $field->id_player_custom_field)
                ->where('code', $code)
                ->exists()
        ) {
            $code = $base . '_' . $suffix;
            $suffix++;
        }

        return substr($code, 0, 64);
    }
}
