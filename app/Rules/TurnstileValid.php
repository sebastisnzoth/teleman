<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TurnstileValid implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Skip if Turnstile is disabled
        if (!config('services.turnstile.enabled')) {
            return;
        }

        $secretKey = config('services.turnstile.secret_key');

        if (empty($secretKey)) {
            Log::warning('Turnstile enabled but secret key is missing');
            return;
        }

        if (empty($value)) {
            $fail('Please complete the security verification.');
            return;
        }

        try {
            $response = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => $secretKey,
                'response' => $value,
                'remoteip' => request()->ip(),
            ]);

            if (!$response->successful() || !$response->json('success')) {
                $fail('Security verification failed. Please try again.');
            }
        } catch (\Exception $e) {
            Log::error('Turnstile verification error', ['error' => $e->getMessage()]);
            // Don't block the user if Turnstile service is down
        }
    }
}
