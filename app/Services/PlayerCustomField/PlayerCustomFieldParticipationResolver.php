<?php

declare(strict_types=1);

namespace App\Services\PlayerCustomField;

use App\Enums\PlayerCustomFieldType;
use App\Models\PlayerCustomField;
use App\Models\PlayerCustomFieldValue;

final class PlayerCustomFieldParticipationResolver
{
    /**
     * @param  list<int>  $playerIds
     * @param  list<int>  $fieldIds
     * @return array<int, list<array{field_id: int, label: string, value: string}>>
     */
    public function resolveForPlayers(int $editionId, array $playerIds, array $fieldIds): array
    {
        $playerIds = array_values(array_unique(array_filter($playerIds, static fn (int $id): bool => $id > 0)));
        $fieldIds = array_values(array_unique(array_filter($fieldIds, static fn (int $id): bool => $id > 0)));

        if ($editionId <= 0 || $playerIds === [] || $fieldIds === []) {
            return [];
        }

        $fields = PlayerCustomField::query()
            ->whereIn('id_player_custom_field', $fieldIds)
            ->whereNull('archived_at')
            ->orderBy('sort_order')
            ->orderBy('id_player_custom_field')
            ->get()
            ->keyBy('id_player_custom_field');

        if ($fields->isEmpty()) {
            return [];
        }

        $activeFieldIds = $fields->keys()->map(static fn ($id): int => (int) $id)->all();

        $rows = PlayerCustomFieldValue::query()
            ->with(['field', 'selectedOptions'])
            ->whereIn('player_id', $playerIds)
            ->whereIn('player_custom_field_id', $activeFieldIds)
            ->whereNull('invalidated_at')
            ->where(function ($query) use ($editionId): void {
                $query->where('edition_id', $editionId)
                    ->orWhereNull('edition_id');
            })
            ->orderByDesc('inserted_at')
            ->orderByDesc('id_player_custom_field_value')
            ->get();

        /** @var array<int, array<int, list<PlayerCustomFieldValue>>> $grouped */
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row->player_id][(int) $row->player_custom_field_id][] = $row;
        }

        $result = [];

        foreach ($playerIds as $playerId) {
            $entries = [];
            foreach ($fields as $field) {
                $fieldId = (int) $field->id_player_custom_field;
                $candidates = $grouped[$playerId][$fieldId] ?? [];
                if ($candidates === []) {
                    continue;
                }

                $valueRow = $this->pickBestValue($candidates, $editionId);
                if ($valueRow === null) {
                    continue;
                }

                $display = $this->scalarString($valueRow, $field);
                if ($display === null || $display === '') {
                    continue;
                }

                $entries[] = [
                    'field_id' => $fieldId,
                    'label' => (string) $field->label,
                    'value' => $display,
                ];
            }

            if ($entries !== []) {
                $result[$playerId] = $entries;
            }
        }

        return $result;
    }

    /**
     * @param  list<PlayerCustomFieldValue>  $candidates  newest-first
     */
    private function pickBestValue(array $candidates, int $editionId): ?PlayerCustomFieldValue
    {
        $editionScoped = array_values(array_filter(
            $candidates,
            static fn (PlayerCustomFieldValue $row): bool => $row->edition_id !== null
                && (int) $row->edition_id === $editionId
        ));

        if ($editionScoped !== []) {
            return $editionScoped[0];
        }

        $global = array_values(array_filter(
            $candidates,
            static fn (PlayerCustomFieldValue $row): bool => $row->edition_id === null
        ));

        return $global[0] ?? null;
    }

    private function scalarString(PlayerCustomFieldValue $value, PlayerCustomField $field): ?string
    {
        $resolved = PlayerCustomFieldValuePresenter::resolve($value);

        if ($resolved === null) {
            return null;
        }

        if ($field->type === PlayerCustomFieldType::MultiSelect) {
            if (! is_array($resolved)) {
                return null;
            }

            $labels = array_values(array_filter(
                $resolved,
                static fn ($label): bool => is_string($label) && $label !== ''
            ));

            return $labels === [] ? null : implode(', ', $labels);
        }

        if (is_bool($resolved)) {
            return $resolved ? 'Yes' : 'No';
        }

        return (string) $resolved;
    }
}
