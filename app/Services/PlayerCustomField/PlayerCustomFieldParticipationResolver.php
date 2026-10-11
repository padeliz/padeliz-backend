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
     *     recent_answers: list<array{value: string, submitted_via: string, inserted_date: string}>
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
            ->with(['field', 'selectedOptions'])
            ->whereIn('player_user_id', $playerIds)
            ->whereIn('player_custom_field_id', $activeFieldIds)
            ->validated()
            ->orderBy('reviewed_at')
            ->orderBy('id_player_custom_field_value')
            ->get();

        /** @var array<int, array<int, list<PlayerCustomFieldValue>>> $grouped */
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row->player_user_id][(int) $row->player_custom_field_id][] = $row;
        }

        $result = [];

        foreach ($playerIds as $playerId) {
            $entries = [];
            foreach ($fields as $field) {
                $fieldId = (int) $field->id_player_custom_field;
                $candidates = array_values(array_filter(
                    $grouped[$playerId][$fieldId] ?? [],
                    fn (PlayerCustomFieldValue $row): bool => $this->isEligible($row, $cutoffDateYmd)
                ));
                if ($candidates === []) {
                    continue;
                }

                $valueRow = $candidates[array_key_last($candidates)];
                $display = $this->scalarString($valueRow, $field);
                if ($display === null || $display === '') {
                    continue;
                }

                $entries[] = [
                    'field_id' => $fieldId,
                    'label' => (string) $field->label,
                    'value' => $display,
                    'recent_answers' => $this->buildRecentAnswers($candidates, $field),
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
     * @return list<array{value: string, submitted_via: string, inserted_date: string}>
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
                'submitted_via' => (string) $row->submitted_via,
                'inserted_date' => $this->insertedDateYmd($row),
            ];
        }

        if ($answers === []) {
            return [];
        }

        return array_slice($answers, -2);
    }

    /**
     * Values are no longer edition-scoped, so only the submission date gates them.
     */
    private function isEligible(PlayerCustomFieldValue $row, string $cutoffDateYmd): bool
    {
        return $this->insertedDateYmd($row) <= $cutoffDateYmd;
    }

    private function insertedDateYmd(PlayerCustomFieldValue $row): string
    {
        $ts = (int) $row->inserted_at;
        if ($ts <= 0) {
            return '';
        }

        return Carbon::createFromTimestamp($ts, config('app.timezone'))->format('Y-m-d');
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

        if (is_array($resolved)) {
            return json_encode($resolved) ?: null;
        }

        if (is_bool($resolved)) {
            return $resolved ? 'Yes' : 'No';
        }

        return (string) $resolved;
    }
}
