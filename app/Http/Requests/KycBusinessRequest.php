<?php

namespace App\Http\Requests;

use App\Enums\DocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KycBusinessRequest extends FormRequest
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
            // Business Information
            'business_name' => ['required', 'string', 'max:255'],
            'business_registration_number' => ['required', 'string', 'max:100'],
            'business_type' => ['required', 'string', 'in:sole_proprietorship,partnership,llc,corporation,other'],
            'business_address_line1' => ['required', 'string', 'max:255'],
            'business_address_line2' => ['nullable', 'string', 'max:255'],
            'business_city' => ['required', 'string', 'max:100'],
            'business_state' => ['required', 'string', 'max:100'],
            'business_postal_code' => ['required', 'string', 'max:20'],
            'business_country' => ['required', 'string', 'size:2'], // ISO 3166-1 alpha-2
            
            // Document Type
            'id_document_type' => [
                'required',
                'string',
                Rule::in(['passport', 'drivers_license', 'national_id']),
            ],
            
            // Document Files
            'id_document' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120', // 5MB
            ],
            'business_document' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120', // 5MB
            ],
            'selfie_with_id' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png',
                'max:5120', // 5MB
            ],
        ];
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'business_name.required' => 'Business name is required.',
            'business_registration_number.required' => 'Business registration number is required.',
            'business_type.required' => 'Please select a business type.',
            'business_type.in' => 'Invalid business type selected.',
            'business_address_line1.required' => 'Business address is required.',
            'business_city.required' => 'City is required.',
            'business_state.required' => 'State/Province is required.',
            'business_postal_code.required' => 'Postal code is required.',
            'business_country.required' => 'Country is required.',
            'business_country.size' => 'Country must be a 2-letter ISO code.',
            
            'id_document_type.required' => 'Please select an ID document type.',
            'id_document_type.in' => 'Invalid ID document type selected.',
            
            'id_document.required' => 'ID document is required.',
            'id_document.mimes' => 'ID document must be a JPG, PNG, or PDF file.',
            'id_document.max' => 'ID document must not exceed 5MB.',
            
            'business_document.required' => 'Business registration document is required.',
            'business_document.mimes' => 'Business document must be a JPG, PNG, or PDF file.',
            'business_document.max' => 'Business document must not exceed 5MB.',
            
            'selfie_with_id.required' => 'Selfie with ID is required.',
            'selfie_with_id.mimes' => 'Selfie must be a JPG or PNG image.',
            'selfie_with_id.max' => 'Selfie must not exceed 5MB.',
        ];
    }

    /**
     * Get custom attributes for validation errors.
     */
    public function attributes(): array
    {
        return [
            'business_name' => 'business name',
            'business_registration_number' => 'registration number',
            'business_type' => 'business type',
            'business_address_line1' => 'address line 1',
            'business_address_line2' => 'address line 2',
            'business_city' => 'city',
            'business_state' => 'state/province',
            'business_postal_code' => 'postal code',
            'business_country' => 'country',
            'id_document_type' => 'ID document type',
            'id_document' => 'ID document',
            'business_document' => 'business document',
            'selfie_with_id' => 'selfie with ID',
        ];
    }
}
