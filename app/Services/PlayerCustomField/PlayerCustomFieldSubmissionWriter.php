<?php

declare(strict_types=1);

namespace App\Services\PlayerCustomField;

use App\Models\PlayerCustomField;
use App\Models\PlayerCustomFieldSubmission;
use App\Models\PlayerCustomFieldValue;
use Illuminate\Support\Facades\DB;

/**
 * Writes answers into one submission. A player's current answer for a field is the latest
 * `validated` value (by reviewed_at, then id). Editing a draft rewrites inside the same
 * submission; a new submission always adds a new row, whose status depends on the path
 * (see PlayerCustomFieldValueWriter). Empty answers write nothing.
 */
final class PlayerCustomFieldSubmissionWriter
{
    public function __construct(
        private readonly PlayerCustomFieldValueWriter $valueWriter,
    ) {
    }

    /**
     * @param  array<string, mixed>  $input
     * @return ?PlayerCustomFieldValue  null when the answer was empty and nothing was written
     */
    public function write(
        PlayerCustomFieldSubmission $submission,
        PlayerCustomField $field,
        array $input,
        ?int $submittedByPlayerId = null,
        ?int $createdViaImportId = null,
    ): ?PlayerCustomFieldValue {
        $playerId = (int) $submission->player_user_id;
        $submissionId = (int) $submission->id_player_custom_field_submission;
        $fieldId = (int) $field->id_player_custom_field;

        return DB::transaction(function () use (
            $submission,
            $field,
            $input,
            $submittedByPlayerId,
            $createdViaImportId,
            $playerId,
            $submissionId,
            $fieldId
        ): ?PlayerCustomFieldValue {
            $outcome = $this->valueWriter->write(
                $playerId,
                $field,
                $submissionId,
                $input,
                (string) $submission->submitted_via,
                $submittedByPlayerId,
                $createdViaImportId,
            );

            if ($outcome === PlayerCustomFieldValueWriter::OUTCOME_SKIPPED) {
                return null;
            }

            return $this->latestInSubmission($submissionId, $fieldId);
        });
    }

    /**
     * @param  array<string, array<string, mixed>>  $inputsByFieldCode
     * @return array<string, PlayerCustomFieldValue>
     */
    public function writeMany(
        PlayerCustomFieldSubmission $submission,
        array $inputsByFieldCode,
        ?int $submittedByPlayerId = null,
        ?int $createdViaImportId = null,
    ): array {
        $fields = PlayerCustomField::query()
            ->whereIn('code', array_keys($inputsByFieldCode))
            ->get()
            ->keyBy('code');

        $written = [];
        foreach ($inputsByFieldCode as $code => $input) {
            $field = $fields->get($code);
            if (! $field instanceof PlayerCustomField) {
                continue;
            }

            $value = $this->write(
                $submission,
                $field,
                $input,
                $submittedByPlayerId,
                $createdViaImportId,
            );

            if ($value !== null) {
                $written[$code] = $value;
            }
        }

        return $written;
    }

    public function currentValue(int $playerId, int $fieldId): ?PlayerCustomFieldValue
    {
        return PlayerCustomFieldValue::query()
            ->where('player_user_id', $playerId)
            ->where('player_custom_field_id', $fieldId)
            ->latestValidatedFirst()
            ->first();
    }

    private function latestInSubmission(int $submissionId, int $fieldId): PlayerCustomFieldValue
    {
        return PlayerCustomFieldValue::query()
            ->where('submission_id', $submissionId)
            ->where('player_custom_field_id', $fieldId)
            ->orderByDesc('id_player_custom_field_value')
            ->firstOrFail();
    }
}
