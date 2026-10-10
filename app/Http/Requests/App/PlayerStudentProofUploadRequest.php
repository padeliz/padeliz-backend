<?php

declare(strict_types=1);

namespace App\Http\Requests\App;

use App\Http\Requests\Concerns\ValidatesPlayerStudentProofUpload;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Create a pending student proof: `student_until` + `files[]` (1-3).
 */
class PlayerStudentProofUploadRequest extends FormRequest
{
    use ValidatesPlayerStudentProofUpload;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->studentProofCreateRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->studentProofMessages();
    }
}
