<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $playerId = $this->route('player')?->id_player ?? $this->user()?->id_player;

        $rules = [
            'skill_division' => 'nullable|numeric|min:0|max:9.9',
            'first_name' => 'nullable|string|max:50',
            'middle_name' => 'nullable|string|max:50',
            'last_name' => 'nullable|string|max:50',
            'year_of_birth' => 'nullable|integer|min:1900|max:9999',
            'being_student' => 'boolean',
            'playtomic_id' => 'nullable|string|max:10',
            'rankedin_id' => 'nullable|string|max:15',
            'instagram_username' => 'nullable|string|max:50',
            'facebook_username' => 'nullable|string|max:50',
        ];

        // For store method, require name, email, and phone
        if ($this->isMethod('POST')) {
            $rules['name'] = 'required|string|max:255';
            $rules['google_email'] = 'required|email|unique:players,google_email,' . $playerId . ',id_player';
            $rules['phone'] = 'required|string|unique:players,phone,' . $playerId . ',id_player';
        } else {
            // For update method, make them optional
            $rules['name'] = 'sometimes|string|max:255';
            $rules['google_email'] = 'sometimes|email|unique:players,google_email,' . $playerId . ',id_player';
            $rules['phone'] = 'sometimes|string|unique:players,phone,' . $playerId . ',id_player';
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
            'name.required' => 'The player name is required.',
            'google_email.required' => 'The email address is required.',
            'google_email.email' => 'Please provide a valid email address.',
            'google_email.unique' => 'This email address is already registered.',
            'phone.required' => 'The phone number is required.',
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
