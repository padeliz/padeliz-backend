<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Models\PlayerStudentProof;

trait ValidatesPlayerStudentProofUpload
{
    /**
     * @return list<string>
     */
    protected function studentProofFileRules(): array
    {
        return [
            'file',
            'mimes:jpeg,jpg,png,gif,webp,pdf',
            'max:' . PlayerStudentProof::MAX_FILE_KILOBYTES,
        ];
    }

    /**
     * @return list<string>
     */
    protected function studentUntilRules(): array
    {
        return ['required', 'date_format:Y-m-d', 'after_or_equal:today'];
    }

    /**
     * Create: `student_until` plus 1-3 `files[]`.
     *
     * @return array<string, mixed>
     */
    protected function studentProofCreateRules(): array
    {
        return [
            'student_until' => $this->studentUntilRules(),
            'files' => ['required', 'array', 'min:1', 'max:' . PlayerStudentProof::MAX_FILES],
            'files.*' => ['required', ...$this->studentProofFileRules()],
        ];
    }

    /**
     * Update pending: `student_until` only.
     *
     * @return array<string, mixed>
     */
    protected function studentProofUpdateRules(): array
    {
        return [
            'student_until' => $this->studentUntilRules(),
        ];
    }

    /**
     * Append one file.
     *
     * @return array<string, mixed>
     */
    protected function studentProofAppendRules(): array
    {
        return [
            'file' => ['required', ...$this->studentProofFileRules()],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function studentProofMessages(): array
    {
        $maxMb = (int) (PlayerStudentProof::MAX_FILE_KILOBYTES / 1024);
        $phpUploadLimit = ini_get('upload_max_filesize') ?: 'unknown';
        $uploadFailed = "The file did not upload completely. It may exceed the server's PHP limit (upload_max_filesize is {$phpUploadLimit}) or this endpoint's maximum ({$maxMb} MB). Try a smaller or compressed file.";
        $maxFiles = PlayerStudentProof::MAX_FILES;

        return [
            'file.file' => 'The uploaded file is not valid.',
            'file.uploaded' => $uploadFailed,
            'file.mimes' => 'The file must be a JPEG, PNG, GIF, WebP, or PDF.',
            'file.max' => 'The file must not exceed ' . PlayerStudentProof::MAX_FILE_KILOBYTES . ' KB (' . $maxMb . ' MB).',
            'files.required' => 'Provide between 1 and ' . $maxFiles . ' files.',
            'files.min' => 'Provide between 1 and ' . $maxFiles . ' files.',
            'files.max' => 'Provide between 1 and ' . $maxFiles . ' files.',
            'files.*.file' => 'Each uploaded file must be valid.',
            'files.*.uploaded' => $uploadFailed,
            'files.*.mimes' => 'Each file must be a JPEG, PNG, GIF, WebP, or PDF.',
            'files.*.max' => 'Each file must not exceed ' . PlayerStudentProof::MAX_FILE_KILOBYTES . ' KB (' . $maxMb . ' MB).',
            'student_until.required' => 'The student_until date is required.',
            'student_until.date_format' => 'The student_until date must use the Y-m-d format.',
            'student_until.after_or_equal' => 'The student_until date cannot be in the past.',
        ];
    }
}
