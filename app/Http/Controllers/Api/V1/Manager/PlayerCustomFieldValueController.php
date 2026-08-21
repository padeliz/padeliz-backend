<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Manager;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Http\Resources\PlayerCustomFieldValueResource;
use App\Models\Player;
use App\Models\PlayerCustomField;
use App\Models\PlayerCustomFieldValue;
use App\Services\PlayerCustomField\PlayerCustomFieldValueWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="🐧 Manager: Player Custom Field Values",
 *     description="(v1) Player custom field value submission and history"
 * )
 */
class PlayerCustomFieldValueController extends V1BaseController
{
    public function __construct(
        private readonly PlayerCustomFieldValueWriter $writer,
    ) {
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/players/{player}/custom-field-values",
     *     summary="List custom field values for a player",
     *     tags={"🐧 Manager: Player Custom Field Values"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function index(Request $request, Player $player)
    {
        $query = PlayerCustomFieldValue::query()
            ->where('player_id', $player->id_player)
            ->with(['field', 'edition', 'selectedOptions']);

        $this->applyAdvancedPagination(
            $query,
            $request,
            PlayerCustomFieldValue::$searchableFields,
            PlayerCustomFieldValue::$filterableFields,
            PlayerCustomFieldValue::$sortableFields
        );

        $paginationParams = $this->getPaginationParams($request);

        if (! $request->has('sort_by')) {
            $query->orderBy('id_player_custom_field_value', 'desc');
        }

        $values = $query->paginate(
            $paginationParams['per_page'],
            ['*'],
            'page',
            $paginationParams['page']
        );

        return PlayerCustomFieldValueResource::collection($values);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/players/{player}/custom-field-values",
     *     summary="Submit a custom field value for a player",
     *     tags={"🐧 Manager: Player Custom Field Values"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=201, description="Created")
     * )
     */
    public function store(Request $request, Player $player): JsonResponse
    {
        $validated = $request->validate([
            'player_custom_field_id' => 'required|integer|exists:player_custom_fields,id_player_custom_field',
            'edition_id' => 'nullable|integer|exists:editions,id_edition',
            'value' => 'nullable',
            'option_id' => 'nullable|integer',
            'option_ids' => 'nullable|array',
            'option_ids.*' => 'integer',
        ]);

        if ($request->has('edition_division_id')) {
            throw ValidationException::withMessages([
                'edition_division_id' => ['This field is not supported.'],
            ]);
        }

        $field = PlayerCustomField::query()->findOrFail((int) $validated['player_custom_field_id']);
        $editionId = $validated['edition_id'] ?? null;

        $this->writer->write(
            (int) $player->id_player,
            $field,
            $editionId !== null ? (int) $editionId : null,
            $validated,
            PlayerCustomFieldValue::SUBMITTED_VIA_MANAGER,
            (int) $request->user()->id_player,
        );

        $value = PlayerCustomFieldValue::query()
            ->where('player_id', $player->id_player)
            ->where('player_custom_field_id', $field->id_player_custom_field)
            ->when(
                $editionId !== null,
                static fn ($query) => $query->where('edition_id', $editionId),
                static fn ($query) => $query->orderByDesc('id_player_custom_field_value')
            )
            ->with(['field', 'edition', 'selectedOptions'])
            ->firstOrFail();

        return $this->resourceResponse(
            new PlayerCustomFieldValueResource($value),
            'Player custom field value submitted successfully',
            201
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/manager/players/{player}/custom-field-values/{playerCustomFieldValue}",
     *     summary="Get a player custom field value",
     *     tags={"🐧 Manager: Player Custom Field Values"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Successful operation")
     * )
     */
    public function show(
        Player $player,
        PlayerCustomFieldValue $playerCustomFieldValue,
    ): JsonResponse {
        $this->assertValueBelongsToPlayer($player, $playerCustomFieldValue);
        $playerCustomFieldValue->load(['field', 'edition', 'selectedOptions']);

        return $this->resourceResponse(
            new PlayerCustomFieldValueResource($playerCustomFieldValue),
            'Player custom field value retrieved successfully'
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/manager/players/{player}/custom-field-values/{playerCustomFieldValue}/invalidate",
     *     summary="Invalidate a player custom field value",
     *     tags={"🐧 Manager: Player Custom Field Values"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Invalidated")
     * )
     */
    public function invalidate(
        Request $request,
        Player $player,
        PlayerCustomFieldValue $playerCustomFieldValue,
    ): JsonResponse {
        $this->assertValueBelongsToPlayer($player, $playerCustomFieldValue);

        if ($playerCustomFieldValue->isInvalidated()) {
            throw ValidationException::withMessages([
                'player_custom_field_value' => ['This value is already invalidated.'],
            ]);
        }

        $validated = $request->validate([
            'invalidation_reason' => 'nullable|string|max:512',
        ]);

        $playerCustomFieldValue->update([
            'invalidated_at' => time(),
            'invalidated_by_player_id' => (int) $request->user()->id_player,
            'invalidation_reason' => $validated['invalidation_reason'] ?? null,
        ]);

        $playerCustomFieldValue->load(['field', 'edition', 'selectedOptions']);

        return $this->resourceResponse(
            new PlayerCustomFieldValueResource($playerCustomFieldValue),
            'Player custom field value invalidated successfully'
        );
    }

    private function assertValueBelongsToPlayer(Player $player, PlayerCustomFieldValue $value): void
    {
        if ((int) $value->player_id !== (int) $player->id_player) {
            abort(404);
        }
    }
}
