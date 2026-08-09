<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlayerFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>|\Illuminate\Contracts\Validation\ValidationRule|string>
     */
    public function rules(): array
    {
        $playerId = $this->route('player')?->id_player ?? $this->user()?->id_player;

        $rules = [
            'skill_division' => 'nullable|numeric|min:0|max:9.9',
            'middle_name' => 'nullable|string|max:50',
            'last_name' => 'nullable|string|max:50',
            'year_of_birth' => 'nullable|integer|min:1900|max:9999',
            'being_student' => 'boolean',
            'playtomic_id' => 'nullable|string|max:10',
            'rankedin_id' => 'nullable|string|max:15',
            'instagram_username' => 'nullable|string|max:50',
            'facebook_username' => 'nullable|string|max:50',
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('player_emails', 'email')->where(function ($query) use ($playerId) {
                    if ($playerId) {
                        $query->where('player_id', '!=', $playerId);
                    }
                }),
            ],
            'phone' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('player_phones', 'phone')->where(function ($query) use ($playerId) {
                    if ($playerId) {
                        $query->where('player_id', '!=', $playerId);
                    }
                }),
            ],
        ];

        if ($this->isMethod('POST')) {
            $rules['first_name'] = 'required|string|max:50';
        } else {
            $rules['first_name'] = 'sometimes|required|string|max:50';
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'The player first name is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email address is already registered.',
            'phone.unique' => 'This phone number is already registered.',
            'skill_division.numeric' => 'Skill division must be a number.',
            'skill_division.min' => 'Skill division must be at least 0.',
            'skill_division.max' => 'Skill division cannot exceed 9.9.',
            'year_of_birth.integer' => 'Year of birth must be a valid year.',
            'year_of_birth.min' => 'Year of birth must be after 1900.',
            'year_of_birth.max' => 'Year of birth must be before 9999.',
        ];
    }
}
