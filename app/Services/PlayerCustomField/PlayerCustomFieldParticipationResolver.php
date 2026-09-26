<?php

declare(strict_types=1);

namespace App\Services\PlayerCustomField;

use App\Enums\PlayerCustomFieldType;
use App\Models\PlayerCustomField;
use App\Models\PlayerCustomFieldValue;
use Carbon\Carbon;

final class PlayerCustomFieldParticipationResolver
{
    /**
     * @param  list<int>  $playerIds
     * @param  list<int>  $fieldIds
     * @return array<int, list<array{
     *     field_id: int,
     *     label: string,
     *     value: string,
     *     recent_answers: list<array{value: string, edition_name: null|string, inserted_date: string}>
     * }>>
     */
    public function resolveForPlayers(
        int $editionId,
        array $playerIds,
        array $fieldIds,
        string $cutoffDateYmd
    ): array {
        $playerIds = array_values(array_unique(array_filter($playerIds, static fn (int $id): bool => $id > 0)));
        $fieldIds = array_values(array_unique(array_filter($fieldIds, static fn (int $id): bool => $id > 0)));

        if ($editionId <= 0 || $playerIds === [] || $fieldIds === [] || $cutoffDateYmd === '') {
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
            ->with(['field', 'selectedOptions', 'edition'])
            ->whereIn('player_id', $playerIds)
            ->whereIn('player_custom_field_id', $activeFieldIds)
            ->whereNull('invalidated_at')
            ->where(function ($query) use ($cutoffDateYmd): void {
                $query->whereNull('edition_id')
                    ->orWhereHas('edition', function ($editionQuery) use ($cutoffDateYmd): void {
                        $editionQuery->whereDate('date', '<=', $cutoffDateYmd);
                    });
            })
            ->orderBy('inserted_at')
            ->orderBy('id_player_custom_field_value')
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

                $tipCandidates = array_values(array_filter(
                    $candidates,
                    fn (PlayerCustomFieldValue $row): bool => $this->isEligible($row, $cutoffDateYmd)
                ));

                $entries[] = [
                    'field_id' => $fieldId,
                    'label' => (string) $field->label,
                    'value' => $display,
                    'recent_answers' => $this->buildRecentAnswers($tipCandidates, $field),
                ];
            }

            if ($entries !== []) {
                $result[$playerId] = $entries;
            }
        }

        return $result;
    }

    /**
     * @param  list<PlayerCustomFieldValue>  $candidates  inserted_at ASC
     * @return list<array{value: string, edition_name: null|string, inserted_date: string}>
     */
    private function buildRecentAnswers(array $candidates, PlayerCustomField $field): array
    {
        $answers = [];
        foreach ($candidates as $row) {
            $display = $this->scalarString($row, $field);
            if ($display === null || $display === '') {
                continue;
            }

            $answers[] = [
                'value' => $display,
                'edition_name' => $row->edition_id === null
                    ? null
                    : (string) ($row->edition?->name ?? ''),
                'inserted_date' => $this->insertedDateYmd($row),
            ];
        }

        if ($answers === []) {
            return [];
        }

        return array_slice($answers, -2);
    }

    private function isEligible(PlayerCustomFieldValue $row, string $cutoffDateYmd): bool
    {
        if ($row->edition_id === null) {
            return $this->insertedDateYmd($row) <= $cutoffDateYmd;
        }

        $edition = $row->edition;
        if ($edition === null) {
            return false;
        }

        if ($edition->date instanceof \DateTimeInterface) {
            return $edition->date->format('Y-m-d') <= $cutoffDateYmd;
        }

        return (string) ($edition->formatted_date ?? '') <= $cutoffDateYmd;
    }

    private function insertedDateYmd(PlayerCustomFieldValue $row): string
    {
        $ts = (int) $row->inserted_at;
        if ($ts <= 0) {
            return '';
        }

        return Carbon::createFromTimestamp($ts, config('app.timezone'))->format('Y-m-d');
    }

    /**
     * @param  list<PlayerCustomFieldValue>  $candidates  oldest-first
     */
    private function pickBestValue(array $candidates, int $editionId): ?PlayerCustomFieldValue
    {
        $editionScoped = [];
        $global = [];
        foreach ($candidates as $row) {
            if ($row->edition_id !== null && (int) $row->edition_id === $editionId) {
                $editionScoped[] = $row;
            } elseif ($row->edition_id === null) {
                $global[] = $row;
            }
        }

        if ($editionScoped !== []) {
            return $editionScoped[array_key_last($editionScoped)];
        }

        if ($global !== []) {
            return $global[array_key_last($global)];
        }

        return null;
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
