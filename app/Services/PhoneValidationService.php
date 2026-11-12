<?php

declare(strict_types=1);

namespace App\Services;

class PhoneValidationService
{
    /**
     * Sanitize and validate phone number to E.164 format
     * This is a simplified version - for production, use libphonenumber-for-php
     */
    public function sanitize(string $phoneNumber, string $defaultCountryCode = '+1'): array
    {
        try {
            // Remove all non-digit characters except +
            $cleaned = preg_replace('/[^0-9+]/', '', trim($phoneNumber));

            if (empty($cleaned)) {
                return [
                    'success' => false,
                    'formatted' => null,
                    'error' => 'Phone number is empty',
                ];
            }

            // Add default country code if not present
            if (!str_starts_with($cleaned, '+')) {
                // Normalize the country code prefix (e.g. '+1' => '1')
                $cc = ltrim($defaultCountryCode, '+');

                // NANP (country code '1'): accept 10-digit local numbers and
                // 11-digit national format (with leading '1'). Never use ltrim()
                // to strip leading digits — that can wrongly chop off area codes
                // for other country codes.
                if ($cc === '1') {
                    if (strlen($cleaned) === 11 && str_starts_with($cleaned, '1')) {
                        $cleaned = '+' . $cleaned;
                    } else {
                        $cleaned = '+1' . $cleaned;
                    }
                } else {
                    // Non-NANP countries often have a leading '0' for national dialing.
                    // Strip a single leading 0 (not multiple) before prepending.
                    if (str_starts_with($cleaned, '0')) {
                        $cleaned = substr($cleaned, 1);
                    }
                    $cleaned = '+' . $cc . $cleaned;
                }
            }

            // Basic validation: E.164 format should be 7-15 digits after +
            $digits = ltrim($cleaned, '+');
            $digitCount = strlen($digits);

            if ($digitCount < 7 || $digitCount > 15) {
                return [
                    'success' => false,
                    'formatted' => null,
                    'error' => 'Invalid phone number length',
                ];
            }

            // Ensure it starts with +
            if (!str_starts_with($cleaned, '+')) {
                $cleaned = '+' . $cleaned;
            }

            return [
                'success' => true,
                'formatted' => $cleaned,
                'error' => null,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'formatted' => null,
                'error' => 'Validation error',
            ];
        }
    }

    /**
     * Quick validation check
     */
    public function isValid(string $phoneNumber, string $defaultCountryCode = '+1'): bool
    {
        $result = $this->sanitize($phoneNumber, $defaultCountryCode);
        return $result['success'];
    }
}
