<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProcessCheckInRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'registration_code' => [
                'required',
                'string',
                'max:50',
                'regex:/^EVENT-REG-[A-Z0-9]{8}$/',
            ],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'registration_code.required' => 'Please enter or scan a registration code.',
            'registration_code.regex' => 'The registration code format is invalid (e.g. EVENT-REG-XXXXXXXX).',
        ];
    }
}
