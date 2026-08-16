<?php

declare(strict_types=1);

namespace App\Http\Requests\App;

use App\Http\Requests\Concerns\ValidatesEligibilityStudentProofUpload;
use Illuminate\Foundation\Http\FormRequest;

class EligibilityStudentProofUploadRequest extends FormRequest
{
    use ValidatesEligibilityStudentProofUpload;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->studentProofRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->studentProofMessages();
    }
}
