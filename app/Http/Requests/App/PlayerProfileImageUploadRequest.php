<?php

declare(strict_types=1);

namespace App\Http\Requests\App;

use App\Http\Requests\Concerns\ValidatesPlayerProfileImageUpload;
use Illuminate\Foundation\Http\FormRequest;

class PlayerProfileImageUploadRequest extends FormRequest
{
    use ValidatesPlayerProfileImageUpload;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->playerProfileImageRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->playerProfileImageMessages();
    }
}
