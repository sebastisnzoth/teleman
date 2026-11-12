<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KycBasicRequest extends FormRequest
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
        return [
            'phone_number' => [
                'required',
                'string',
                'regex:/^\+[1-9]\d{1,14}$/', // E.164 format
                Rule::unique('user_kyc_verifications', 'phone_number')
                    ->ignore($this->user()->kycVerification?->id)
                    ->whereIn('status', ['pending', 'approved']),
            ],
        ];
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'phone_number.required' => 'Phone number is required.',
            'phone_number.regex' => 'Phone number must be in E.164 format (e.g., +12345678901).',
            'phone_number.unique' => 'This phone number is already registered with another account.',
        ];
    }

    /**
     * Get custom attributes for validation errors.
     */
    public function attributes(): array
    {
        return [
            'phone_number' => 'phone number',
        ];
    }
}
