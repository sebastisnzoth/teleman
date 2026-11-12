<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\TelephonyProvider;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LockTelephonyProviderRequest extends FormRequest
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
     * The provider must be one of TelephonyProvider's cases — never an
     * arbitrary string — since it is about to be handed straight to
     * LockTelephonyProviderAction.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'provider' => ['required', Rule::enum(TelephonyProvider::class)],
        ];
    }
}
