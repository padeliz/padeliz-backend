<?php

declare(strict_types=1);

namespace App\Services\PlayerCustomField;

use App\Enums\PlayerCustomFieldType;
use App\Enums\PlayerCustomFieldValueReviewStatus;
use App\Models\Level;
use App\Models\PlayerCustomField;
use App\Models\PlayerCustomFieldOption;
use App\Models\PlayerCustomFieldValue;
use App\Models\PlayerCustomFieldValueOption;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class PlayerCustomFieldValueWriter
{
    public const RACKET_EXPERIENCE_CODE = 'racket_experience';

    /**
     * Measures a player may report per sport; at least one is required.
     *
     * @var list<string>
     */
    public const RACKET_EXPERIENCE_MEASURES = [
        'years_of_playing',
        'matches_played',
        'lessons_had',
    ];

    public const OUTCOME_NEW = 'new';

    public const OUTCOME_REPLACED = 'replaced';

    public const OUTCOME_SKIPPED = 'skipped';

    /**
     * Writes one answer into a submission.
     *
     * - An empty answer is skipped (nothing is created, and an existing row is never cleared) unless
     *   `$skipEmpty` is false, in which case it fails validation like any other bad input.
     * - A row already in this submission for the field is updated in place: same primary key and
     *   `inserted_at`, review fields untouched.
     * - Otherwise a new row is inserted. App answers start `pending`; manager and CSV answers are
     *   `validated` immediately (CSV without a reviewing manager).
     *
     * @param  array<string, mixed>  $input
     * @return 'new'|'replaced'|'skipped'
     */
    public function write(
        int $playerId,
        PlayerCustomField $field,
        int $submissionId,
        array $input,
        string $submittedVia,
        ?int $submittedByPlayerId = null,
        ?int $createdViaImportId = null,
        bool $skipEmpty = true,
    ): string {
        if ($field->isArchived()) {
            throw ValidationException::withMessages([
                'player_custom_field_id' => ['This custom field is archived.'],
            ]);
        }

        if ($skipEmpty && $this->isEmptyInput($field, $input)) {
            return self::OUTCOME_SKIPPED;
        }

        $normalized = $this->normalizeInput($field, $input);

        $existing = PlayerCustomFieldValue::query()
            ->where('player_user_id', $playerId)
            ->where('player_custom_field_id', $field->id_player_custom_field)
            ->where('submission_id', $submissionId)
            ->orderByDesc('id_player_custom_field_value')
            ->first();

        $columns = [
            'value_text' => $normalized['value_text'],
            'value_integer' => $normalized['value_integer'],
            'value_boolean' => $normalized['value_boolean'],
            'value_date' => $normalized['value_date'],
            'value_json' => $normalized['value_json'],
        ];

        $submittedBy = $this->submittedByColumns($submittedVia, $submittedByPlayerId);

        if ($existing !== null) {
            $existing->fill($columns + $submittedBy);
            $existing->save();
            $existing->selectedOptions()->delete();
            $value = $existing;
            $outcome = self::OUTCOME_REPLACED;
        } else {
            $value = PlayerCustomFieldValue::create($columns + [
                'player_user_id' => $playerId,
                'player_custom_field_id' => $field->id_player_custom_field,
                'submission_id' => $submissionId,
                'submitted_via' => $submittedVia,
                'created_via_import_id' => $createdViaImportId,
            ] + $submittedBy + $this->reviewStateFor($submittedVia, $submittedByPlayerId));
            $outcome = self::OUTCOME_NEW;
        }

        foreach ($normalized['options'] as $option) {
            PlayerCustomFieldValueOption::create([
                'player_custom_field_value_id' => $value->id_player_custom_field_value,
                'option_id' => $option['option_id'],
                'option_label_snapshot' => $option['option_label_snapshot'],
            ]);
        }

        return $outcome;
    }

    /**
     * True when the answer carries nothing to store for the field's type (false/0 count as filled).
     *
     * @param  array<string, mixed>  $input
     */
    public function isEmptyInput(PlayerCustomField $field, array $input): bool
    {
        $blank = static fn (mixed $raw): bool => $raw === null
            || (is_string($raw) && trim($raw) === '')
            || $raw === [];

        return match ($field->type) {
            PlayerCustomFieldType::SingleSelect => $blank($input['option_id'] ?? null),
            PlayerCustomFieldType::MultiSelect => $blank($input['option_ids'] ?? null),
            PlayerCustomFieldType::LabeledForeignId => $blank($input['value'] ?? null)
                && $blank($input['label'] ?? null)
                && $blank($input['name'] ?? null)
                && $blank($input['foreign_id'] ?? null),
            default => $blank($input['value'] ?? null),
        };
    }

    /**
     * @return array{submitted_by_player_user_id: ?int, submitted_by_manager_user_id: ?int}
     */
    private function submittedByColumns(string $submittedVia, ?int $submittedByUserId): array
    {
        if (in_array($submittedVia, [
            PlayerCustomFieldValue::SUBMITTED_VIA_MANAGER,
            PlayerCustomFieldValue::SUBMITTED_VIA_CSV,
        ], true)) {
            return [
                'submitted_by_player_user_id' => null,
                'submitted_by_manager_user_id' => $submittedByUserId,
            ];
        }

        return [
            'submitted_by_player_user_id' => $submittedByUserId,
            'submitted_by_manager_user_id' => null,
        ];
    }

    /**
     * @return array{review_status: PlayerCustomFieldValueReviewStatus, reviewed_at: ?int, reviewed_by_manager_user_id: ?int}
     */
    private function reviewStateFor(string $submittedVia, ?int $submittedByPlayerId): array
    {
        if ($submittedVia === PlayerCustomFieldValue::SUBMITTED_VIA_MANAGER) {
            return [
                'review_status' => PlayerCustomFieldValueReviewStatus::Validated,
                'reviewed_at' => time(),
                'reviewed_by_manager_user_id' => $submittedByPlayerId,
            ];
        }

        if ($submittedVia === PlayerCustomFieldValue::SUBMITTED_VIA_CSV) {
            return [
                'review_status' => PlayerCustomFieldValueReviewStatus::Validated,
                'reviewed_at' => time(),
                'reviewed_by_manager_user_id' => null,
            ];
        }

        return [
            'review_status' => PlayerCustomFieldValueReviewStatus::Pending,
            'reviewed_at' => null,
            'reviewed_by_manager_user_id' => null,
        ];
    }

    /**
     * Racket experience is a list of per-sport entries, each needing a sport, a level and a measure.
     *
     * @param  list<string>  $allowedSports
     * @param  list<string>  $allowedLevels
     * @return list<array<string, mixed>>
     */
    public static function normalizeRacketExperience(
        mixed $raw,
        array $allowedSports = [],
        array $allowedLevels = [],
    ): array {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : null;
        }

        if (! is_array($raw) || $raw === [] || ! array_is_list($raw)) {
            throw ValidationException::withMessages([
                'value' => ['At least one racket experience entry is required.'],
            ]);
        }

        $entries = [];
        $seenSports = [];

        foreach ($raw as $index => $entry) {
            if (! is_array($entry)) {
                throw ValidationException::withMessages([
                    "value.{$index}" => ['Each racket experience entry must be an object.'],
                ]);
            }

            $sport = isset($entry['sport']) && is_string($entry['sport']) ? trim($entry['sport']) : '';
            if ($sport === '') {
                throw ValidationException::withMessages([
                    "value.{$index}.sport" => ['A sport is required.'],
                ]);
            }
            if ($allowedSports !== [] && ! in_array($sport, $allowedSports, true)) {
                throw ValidationException::withMessages([
                    "value.{$index}.sport" => ['The selected sport is invalid.'],
                ]);
            }
            if (isset($seenSports[$sport])) {
                throw ValidationException::withMessages([
                    "value.{$index}.sport" => ['Each sport may only be reported once.'],
                ]);
            }
            $seenSports[$sport] = true;

            $level = isset($entry['level']) && is_string($entry['level']) ? trim($entry['level']) : '';
            if ($level === '') {
                throw ValidationException::withMessages([
                    "value.{$index}.level" => ['A level is required.'],
                ]);
            }
            if ($allowedLevels !== [] && ! in_array($level, $allowedLevels, true)) {
                throw ValidationException::withMessages([
                    "value.{$index}.level" => ['The selected level is invalid.'],
                ]);
            }

            $normalized = ['sport' => $sport, 'level' => $level];

            foreach (self::RACKET_EXPERIENCE_MEASURES as $measure) {
                if (! array_key_exists($measure, $entry) || $entry[$measure] === null || $entry[$measure] === '') {
                    continue;
                }

                $value = $entry[$measure];
                if (! is_numeric($value)) {
                    throw ValidationException::withMessages([
                        "value.{$index}.{$measure}" => ['The value must be a number.'],
                    ]);
                }
                if ((float) $value < 0) {
                    throw ValidationException::withMessages([
                        "value.{$index}.{$measure}" => ['The value must be at least 0.'],
                    ]);
                }

                $normalized[$measure] = $measure === 'years_of_playing'
                    ? (float) $value
                    : (int) $value;
            }

            if (count($normalized) === 2) {
                throw ValidationException::withMessages([
                    "value.{$index}" => [
                        'At least one of years_of_playing, matches_played or lessons_had is required.',
                    ],
                ]);
            }

            $entries[] = $normalized;
        }

        return $entries;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *     value_text: ?string,
     *     value_integer: ?int,
     *     value_boolean: ?bool,
     *     value_date: ?string,
     *     value_json: ?array<mixed>,
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
            PlayerCustomFieldType::Json => $this->normalizeJson($field, $input),
            PlayerCustomFieldType::LabeledForeignId => $this->normalizeLabeledForeignId($field, $input),
        };
    }

    /**
     * @return array{
     *     value_text: ?string,
     *     value_integer: ?int,
     *     value_boolean: ?bool,
     *     value_date: ?string,
     *     value_json: ?array<mixed>,
     *     options: list<array{option_id: int, option_label_snapshot: string}>
     * }
     */
    private function emptyNormalized(): array
    {
        return [
            'value_text' => null,
            'value_integer' => null,
            'value_boolean' => null,
            'value_date' => null,
            'value_json' => null,
            'options' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
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

        return ['value_text' => $text] + $this->emptyNormalized();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
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

        return ['value_integer' => $integer] + $this->emptyNormalized();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
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

        return ['value_boolean' => $boolean] + $this->emptyNormalized();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
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

        return ['value_date' => $date] + $this->emptyNormalized();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalizeJson(PlayerCustomField $field, array $input): array
    {
        if (! array_key_exists('value', $input) || $input['value'] === null || $input['value'] === '') {
            throw ValidationException::withMessages([
                'value' => ['A value is required.'],
            ]);
        }

        if ($field->code === self::RACKET_EXPERIENCE_CODE) {
            $json = self::normalizeRacketExperience(
                $input['value'],
                array_values((array) ($field->rules_json['sports'] ?? [])),
                array_values((array) ($field->rules_json['levels'] ?? [])),
            );

            return ['value_json' => $json] + $this->emptyNormalized();
        }

        $raw = $input['value'];
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : null;
        }

        if (! is_array($raw)) {
            throw ValidationException::withMessages([
                'value' => ['The value must be valid JSON.'],
            ]);
        }

        return ['value_json' => $raw] + $this->emptyNormalized();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalizeSingleSelect(PlayerCustomField $field, array $input): array
    {
        if (! array_key_exists('option_id', $input) || $input['option_id'] === null || $input['option_id'] === '') {
            throw ValidationException::withMessages([
                'option_id' => ['An option is required.'],
            ]);
        }

        $option = $this->resolveActiveOption($field, (int) $input['option_id'], 'option_id');

        return ['options' => [[
            'option_id' => (int) $option->id_player_custom_field_option,
            'option_label_snapshot' => $option->label,
        ]]] + $this->emptyNormalized();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
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

        return ['options' => $options] + $this->emptyNormalized();
    }

    /**
     * Resolve a catalog row into value_integer (id) + value_text (label snapshot).
     *
     * Accepts id and/or name via `value`, optional `label`/`name`, or `foreign_id`.
     * When both resolve, they must refer to the same row or a 422 conflict is raised.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalizeLabeledForeignId(PlayerCustomField $field, array $input): array
    {
        $catalog = $field->rules_json['catalog'] ?? null;
        if (! is_string($catalog) || $catalog === '') {
            throw ValidationException::withMessages([
                'value' => ['This field has no catalog configured.'],
            ]);
        }

        if ($catalog !== 'levels') {
            throw ValidationException::withMessages([
                'value' => ["Unsupported catalog \"{$catalog}\"."],
            ]);
        }

        [$rawId, $rawLabel] = $this->extractLabeledForeignIdParts($input);

        $byId = null;
        if ($rawId !== null) {
            $byId = Level::query()->whereKey($rawId)->first();
            if ($byId === null) {
                throw ValidationException::withMessages([
                    'value' => ['The selected catalog id does not exist.'],
                ]);
            }
        }

        $byLabel = null;
        if ($rawLabel !== null) {
            $byLabel = Level::query()->where('name', $rawLabel)->first();
            if ($byLabel === null) {
                throw ValidationException::withMessages([
                    'value' => ["'{$rawLabel}' is not a valid catalog label."],
                ]);
            }
        }

        if ($byId === null && $byLabel === null) {
            throw ValidationException::withMessages([
                'value' => ['A catalog id or label is required.'],
            ]);
        }

        if ($byId !== null && $byLabel !== null
            && (int) $byId->id_level !== (int) $byLabel->id_level) {
            throw ValidationException::withMessages([
                'value' => ['The catalog id and label refer to different records.'],
            ]);
        }

        $level = $byId ?? $byLabel;
        assert($level instanceof Level);

        return [
            'value_integer' => (int) $level->id_level,
            'value_text' => (string) $level->name,
        ] + $this->emptyNormalized();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{0: ?int, 1: ?string}
     */
    private function extractLabeledForeignIdParts(array $input): array
    {
        $rawId = $this->asOptionalCatalogId($input['foreign_id'] ?? null);
        $rawLabel = $this->asOptionalCatalogLabel($input['label'] ?? $input['name'] ?? null);

        if (! array_key_exists('value', $input) || $input['value'] === null || $input['value'] === '') {
            return [$rawId, $rawLabel];
        }

        $value = $input['value'];

        if (is_array($value) && ! array_is_list($value)) {
            $nestedId = $this->asOptionalCatalogId($value['id'] ?? $value['foreign_id'] ?? null);
            $nestedLabel = $this->asOptionalCatalogLabel($value['label'] ?? $value['name'] ?? null);
            $rawId = $this->mergeCatalogId($rawId, $nestedId);
            $rawLabel = $this->mergeCatalogLabel($rawLabel, $nestedLabel);

            return [$rawId, $rawLabel];
        }

        if (is_int($value) || (is_string($value) && ctype_digit(trim($value)))) {
            $rawId = $this->mergeCatalogId($rawId, (int) $value);

            return [$rawId, $rawLabel];
        }

        if (is_scalar($value)) {
            $rawLabel = $this->mergeCatalogLabel($rawLabel, trim((string) $value));

            return [$rawId, $rawLabel];
        }

        throw ValidationException::withMessages([
            'value' => ['A catalog id or label is required.'],
        ]);
    }

    private function asOptionalCatalogId(mixed $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (! is_int($raw) && ! (is_string($raw) && ctype_digit(trim($raw)))) {
            throw ValidationException::withMessages([
                'value' => ['The catalog id must be an integer.'],
            ]);
        }

        return (int) $raw;
    }

    private function asOptionalCatalogLabel(mixed $raw): ?string
    {
        if ($raw === null || (is_string($raw) && trim($raw) === '')) {
            return null;
        }

        if (! is_scalar($raw)) {
            throw ValidationException::withMessages([
                'value' => ['The catalog label must be a string.'],
            ]);
        }

        $label = trim((string) $raw);

        return $label === '' ? null : $label;
    }

    private function mergeCatalogId(?int $existing, ?int $incoming): ?int
    {
        if ($incoming === null) {
            return $existing;
        }

        if ($existing !== null && $existing !== $incoming) {
            throw ValidationException::withMessages([
                'value' => ['The catalog id and label refer to different records.'],
            ]);
        }

        return $incoming;
    }

    private function mergeCatalogLabel(?string $existing, ?string $incoming): ?string
    {
        if ($incoming === null) {
            return $existing;
        }

        if ($existing !== null && $existing !== $incoming) {
            throw ValidationException::withMessages([
                'value' => ['The catalog id and label refer to different records.'],
            ]);
        }

        return $incoming;
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
