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
            'middle_name' => 'nullable|string|max:50',
            'last_name' => 'nullable|string|max:50',
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
        ];
    }
}
