<?php

declare(strict_types=1);

namespace App\Services\PlayerCustomField;

use App\Enums\PlayerCustomFieldType;
use App\Models\PlayerCustomField;
use App\Models\PlayerCustomFieldOption;
use App\Models\PlayerCustomFieldValue;
use App\Models\PlayerCustomFieldValueOption;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class PlayerCustomFieldValueWriter
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function write(
        int $playerId,
        PlayerCustomField $field,
        ?int $editionId,
        array $input,
        string $submittedVia,
        ?int $submittedByPlayerId = null,
        ?int $createdViaImportId = null,
    ): string {
        if ($field->isArchived()) {
            throw ValidationException::withMessages([
                'player_custom_field_id' => ['This custom field is archived.'],
            ]);
        }

        $normalized = $this->normalizeInput($field, $input);
        $replaced = false;

        if ($editionId !== null) {
            $existing = PlayerCustomFieldValue::query()
                ->where('player_id', $playerId)
                ->where('player_custom_field_id', $field->id_player_custom_field)
                ->where('edition_id', $editionId)
                ->first();

            if ($existing !== null) {
                $existing->delete();
                $replaced = true;
            }
        }

        $value = PlayerCustomFieldValue::create([
            'player_id' => $playerId,
            'player_custom_field_id' => $field->id_player_custom_field,
            'edition_id' => $editionId,
            'value_text' => $normalized['value_text'],
            'value_integer' => $normalized['value_integer'],
            'value_boolean' => $normalized['value_boolean'],
            'value_date' => $normalized['value_date'],
            'submitted_by_player_id' => $submittedByPlayerId,
            'submitted_via' => $submittedVia,
            'created_via_import_id' => $createdViaImportId,
        ]);

        foreach ($normalized['options'] as $option) {
            PlayerCustomFieldValueOption::create([
                'player_custom_field_value_id' => $value->id_player_custom_field_value,
                'option_id' => $option['option_id'],
                'option_label_snapshot' => $option['option_label_snapshot'],
            ]);
        }

        return $replaced ? 'replaced' : 'new';
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     value_text: ?string,
     *     value_integer: ?int,
     *     value_boolean: ?bool,
     *     value_date: ?string,
     *     options: list<array{option_id: int, option_label_snapshot: string}>
     * }
     */
    private function normalizeInput(PlayerCustomField $field, array $input): array
    {
        return match ($field->type) {
            PlayerCustomFieldType::Text => $this->normalizeText($field, $input),
            PlayerCustomFieldType::Integer => $this->normalizeInteger($field, $input),
            PlayerCustomFieldType::Boolean => $this->normalizeBoolean($input),
            PlayerCustomFieldType::Date => $this->normalizeDate($input),
            PlayerCustomFieldType::SingleSelect => $this->normalizeSingleSelect($field, $input),
            PlayerCustomFieldType::MultiSelect => $this->normalizeMultiSelect($field, $input),
        };
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     value_text: ?string,
     *     value_integer: ?int,
     *     value_boolean: ?bool,
     *     value_date: ?string,
     *     options: list<array{option_id: int, option_label_snapshot: string}>
     * }
     */
    private function normalizeText(PlayerCustomField $field, array $input): array
    {
        $raw = array_key_exists('value', $input) ? $input['value'] : null;
        if ($raw === null || (is_string($raw) && trim($raw) === '')) {
            throw ValidationException::withMessages([
                'value' => ['A value is required.'],
            ]);
        }

        $text = is_scalar($raw) ? trim((string) $raw) : '';
        if ($text === '') {
            throw ValidationException::withMessages([
                'value' => ['A value is required.'],
            ]);
        }

        $maxLength = (int) ($field->rules_json['max_length'] ?? 255);
        if (mb_strlen($text) > $maxLength) {
            throw ValidationException::withMessages([
                'value' => ["The value may not be greater than {$maxLength} characters."],
            ]);
        }

        return [
            'value_text' => $text,
            'value_integer' => null,
            'value_boolean' => null,
            'value_date' => null,
            'options' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     value_text: ?string,
     *     value_integer: ?int,
     *     value_boolean: ?bool,
     *     value_date: ?string,
     *     options: list<array{option_id: int, option_label_snapshot: string}>
     * }
     */
    private function normalizeInteger(PlayerCustomField $field, array $input): array
    {
        if (! array_key_exists('value', $input) || $input['value'] === null || $input['value'] === '') {
            throw ValidationException::withMessages([
                'value' => ['A value is required.'],
            ]);
        }

        if (! is_int($input['value']) && ! (is_string($input['value']) && ctype_digit(trim((string) $input['value'])))) {
            throw ValidationException::withMessages([
                'value' => ['The value must be an integer.'],
            ]);
        }

        $integer = (int) $input['value'];
        $rules = ['value' => ['integer']];
        if (isset($field->rules_json['min'])) {
            $rules['value'][] = 'min:' . (int) $field->rules_json['min'];
        }
        if (isset($field->rules_json['max'])) {
            $rules['value'][] = 'max:' . (int) $field->rules_json['max'];
        }

        $validator = Validator::make(['value' => $integer], $rules);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return [
            'value_text' => null,
            'value_integer' => $integer,
            'value_boolean' => null,
            'value_date' => null,
            'options' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     value_text: ?string,
     *     value_integer: ?int,
     *     value_boolean: ?bool,
     *     value_date: ?string,
     *     options: list<array{option_id: int, option_label_snapshot: string}>
     * }
     */
    private function normalizeBoolean(array $input): array
    {
        if (! array_key_exists('value', $input)) {
            throw ValidationException::withMessages([
                'value' => ['A value is required.'],
            ]);
        }

        $raw = $input['value'];
        if (is_bool($raw)) {
            $boolean = $raw;
        } elseif ($raw === 1 || $raw === '1' || $raw === 'true') {
            $boolean = true;
        } elseif ($raw === 0 || $raw === '0' || $raw === 'false') {
            $boolean = false;
        } else {
            throw ValidationException::withMessages([
                'value' => ['The value must be a boolean.'],
            ]);
        }

        return [
            'value_text' => null,
            'value_integer' => null,
            'value_boolean' => $boolean,
            'value_date' => null,
            'options' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     value_text: ?string,
     *     value_integer: ?int,
     *     value_boolean: ?bool,
     *     value_date: ?string,
     *     options: list<array{option_id: int, option_label_snapshot: string}>
     * }
     */
    private function normalizeDate(array $input): array
    {
        if (! array_key_exists('value', $input) || ! is_string($input['value']) || trim($input['value']) === '') {
            throw ValidationException::withMessages([
                'value' => ['A value is required.'],
            ]);
        }

        $date = trim($input['value']);
        $validator = Validator::make(['value' => $date], [
            'value' => ['date_format:Y-m-d'],
        ]);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return [
            'value_text' => null,
            'value_integer' => null,
            'value_boolean' => null,
            'value_date' => $date,
            'options' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     value_text: ?string,
     *     value_integer: ?int,
     *     value_boolean: ?bool,
     *     value_date: ?string,
     *     options: list<array{option_id: int, option_label_snapshot: string}>
     * }
     */
    private function normalizeSingleSelect(PlayerCustomField $field, array $input): array
    {
        if (! array_key_exists('option_id', $input) || $input['option_id'] === null || $input['option_id'] === '') {
            throw ValidationException::withMessages([
                'option_id' => ['An option is required.'],
            ]);
        }

        $option = $this->resolveActiveOption($field, (int) $input['option_id'], 'option_id');

        return [
            'value_text' => null,
            'value_integer' => null,
            'value_boolean' => null,
            'value_date' => null,
            'options' => [[
                'option_id' => (int) $option->id_player_custom_field_option,
                'option_label_snapshot' => $option->label,
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     value_text: ?string,
     *     value_integer: ?int,
     *     value_boolean: ?bool,
     *     value_date: ?string,
     *     options: list<array{option_id: int, option_label_snapshot: string}>
     * }
     */
    private function normalizeMultiSelect(PlayerCustomField $field, array $input): array
    {
        $optionIds = $input['option_ids'] ?? null;
        if (! is_array($optionIds) || $optionIds === []) {
            throw ValidationException::withMessages([
                'option_ids' => ['At least one option is required.'],
            ]);
        }

        $options = [];
        foreach (array_values(array_unique(array_map('intval', $optionIds))) as $optionId) {
            $option = $this->resolveActiveOption($field, $optionId, 'option_ids');
            $options[] = [
                'option_id' => (int) $option->id_player_custom_field_option,
                'option_label_snapshot' => $option->label,
            ];
        }

        return [
            'value_text' => null,
            'value_integer' => null,
            'value_boolean' => null,
            'value_date' => null,
            'options' => $options,
        ];
    }

    private function resolveActiveOption(
        PlayerCustomField $field,
        int $optionId,
        string $errorKey,
    ): PlayerCustomFieldOption {
        $option = PlayerCustomFieldOption::query()
            ->where('player_custom_field_id', $field->id_player_custom_field)
            ->whereKey($optionId)
            ->first();

        if ($option === null) {
            throw ValidationException::withMessages([
                $errorKey => ['The selected option does not belong to this field.'],
            ]);
        }

        if (! $option->is_active) {
            throw ValidationException::withMessages([
                $errorKey => ['The selected option is inactive.'],
            ]);
        }

        return $option;
    }
}
