<?php

declare(strict_types=1);

namespace App\Http\Requests\Manager;

use App\Http\Requests\Concerns\ValidatesLogoUpload;
use Illuminate\Foundation\Http\FormRequest;

class ManagerLogoUploadRequest extends FormRequest
{
    use ValidatesLogoUpload;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->logoRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->logoMessages();
    }
}
