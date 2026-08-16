<?php

declare(strict_types=1);

namespace App\Http\Requests\Manager;

use App\Http\Requests\Concerns\ValidatesPrizeMediaUpload;
use Illuminate\Foundation\Http\FormRequest;

class ManagerPrizeMediaUploadRequest extends FormRequest
{
    use ValidatesPrizeMediaUpload;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->prizeMediaRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->prizeMediaMessages();
    }
}
