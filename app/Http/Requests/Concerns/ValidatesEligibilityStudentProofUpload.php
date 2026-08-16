<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Models\EligibilityStudentProof;

trait ValidatesEligibilityStudentProofUpload
{
    /**
     * @return array<string, mixed>
     */
    protected function studentProofRules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:jpeg,jpg,png,gif,webp,pdf',
                'max:' . EligibilityStudentProof::MAX_FILE_KILOBYTES,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function studentProofMessages(): array
    {
        $maxMb = (int) (EligibilityStudentProof::MAX_FILE_KILOBYTES / 1024);
        $phpUploadLimit = ini_get('upload_max_filesize') ?: 'unknown';

        return [
            'file.required' => 'Please select a file to upload.',
            'file.file' => 'The uploaded file is not valid.',
            'file.uploaded' => "The file did not upload completely. It may exceed the server's PHP limit (upload_max_filesize is {$phpUploadLimit}) or this endpoint's maximum ({$maxMb} MB). Try a smaller or compressed file.",
            'file.mimes' => 'The file must be a JPEG, PNG, GIF, WebP, or PDF.',
            'file.max' => 'The file must not exceed ' . EligibilityStudentProof::MAX_FILE_KILOBYTES . ' KB (' . $maxMb . ' MB).',
        ];
    }
}
