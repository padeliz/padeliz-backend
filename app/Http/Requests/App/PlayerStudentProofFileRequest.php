<?php

declare(strict_types=1);

namespace App\Http\Requests\App;

use App\Http\Requests\Concerns\ValidatesPlayerStudentProofUpload;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Append one file to the pending student proof.
 */
class PlayerStudentProofFileRequest extends FormRequest
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
        return $this->studentProofAppendRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->studentProofMessages();
    }
}
