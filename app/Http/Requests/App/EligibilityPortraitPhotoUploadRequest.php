<?php

declare(strict_types=1);

namespace App\Http\Requests\App;

use App\Http\Requests\Concerns\ValidatesEligibilityPortraitPhotoUpload;
use Illuminate\Foundation\Http\FormRequest;

class EligibilityPortraitPhotoUploadRequest extends FormRequest
{
    use ValidatesEligibilityPortraitPhotoUpload;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->portraitPhotoRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->portraitPhotoMessages();
    }
}
