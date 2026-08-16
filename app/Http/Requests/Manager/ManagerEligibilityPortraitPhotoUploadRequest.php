<?php

declare(strict_types=1);

namespace App\Http\Requests\Manager;

use App\Http\Requests\Concerns\ValidatesEligibilityPortraitPhotoUpload;
use Illuminate\Foundation\Http\FormRequest;

class ManagerEligibilityPortraitPhotoUploadRequest extends FormRequest
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
