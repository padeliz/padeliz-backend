<?php

declare(strict_types=1);

namespace App\Http\Requests\App;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class PendingEligibilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'duplicate_id' => ['sometimes', 'nullable', 'integer'],

            'edition_ids' => ['sometimes', 'nullable', 'array'],
            'edition_ids.*' => ['integer', 'exists:editions,id_edition'],

            'media_source' => ['sometimes', 'nullable', 'array'],
            'friends_source' => ['sometimes', 'nullable', 'array'],
            'racket_experience' => ['sometimes', 'nullable', 'array'],

            'playtomic_id' => ['sometimes', 'nullable', 'string', 'max:10'],
            'rankedin_id' => ['sometimes', 'nullable', 'string', 'max:15'],

            'first_name' => ['sometimes', 'nullable', 'string', 'max:50'],
            'middle_name' => ['sometimes', 'nullable', 'string', 'max:50'],
            'last_name' => ['sometimes', 'nullable', 'string', 'max:50'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:100'],

            'birth_year' => ['sometimes', 'nullable', 'integer', 'min:1900', 'max:9999'],
            'birth_month' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:12'],
            'birth_day' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:31'],

            'being_student' => ['sometimes', 'nullable', 'boolean'],

            'instagram_username' => ['sometimes', 'nullable', 'string', 'max:50'],
            'facebook_username' => ['sometimes', 'nullable', 'string', 'max:50'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
