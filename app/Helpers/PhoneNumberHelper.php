<?php

declare(strict_types=1);

namespace App\Helpers;

class PhoneNumberHelper
{
    /**
     * Format phone number for display
     * 
     * @param string $phoneNumber Phone number in any format
     * @return string Formatted phone number
     */
    public static function format(string $phoneNumber): string
    {
        // Remove non-digits
        $digits = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // Format as (XXX) XXX-XXXX for US/CA numbers (11 digits starting with 1)
        if (strlen($digits) === 11 && $digits[0] === '1') {
            return sprintf('(%s) %s-%s', 
                substr($digits, 1, 3), 
                substr($digits, 4, 3), 
                substr($digits, 7, 4)
            );
        }
        
        // Format as (XXX) XXX-XXXX for 10 digit numbers
        if (strlen($digits) === 10) {
            return sprintf('(%s) %s-%s', 
                substr($digits, 0, 3), 
                substr($digits, 3, 3), 
                substr($digits, 6, 4)
            );
        }
        
        // Return original if can't format
        return $phoneNumber;
    }

    /**
     * Extract area code from phone number
     * 
     * @param string $phoneNumber Phone number in any format
     * @return string|null Area code or null if not extractable
     */
    public static function extractAreaCode(string $phoneNumber): ?string
    {
        // Remove non-digits
        $digits = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // For US/CA numbers (11 digits starting with 1), extract area code
        if (strlen($digits) === 11 && $digits[0] === '1') {
            return substr($digits, 1, 3);
        }
        
        // For 10 digit numbers, first 3 digits
        if (strlen($digits) === 10) {
            return substr($digits, 0, 3);
        }
        
        return null;
    }

    /**
     * Validate E.164 phone number format
     * 
     * @param string $phoneNumber Phone number to validate
     * @return bool True if valid E.164 format
     */
    public static function isValidE164(string $phoneNumber): bool
    {
        return preg_match('/^\+[1-9]\d{1,14}$/', $phoneNumber) === 1;
    }

    /**
     * Convert to E.164 format (add + if missing, assume US if no country code)
     * 
     * @param string $phoneNumber Phone number in any format
     * @return string Phone number in E.164 format
     */
    public static function toE164(string $phoneNumber): string
    {
        // Already in E.164 format
        if (str_starts_with($phoneNumber, '+')) {
            return $phoneNumber;
        }

        // Remove non-digits
        $digits = preg_replace('/[^0-9]/', '', $phoneNumber);

        // If 10 digits, assume US/CA
        if (strlen($digits) === 10) {
            return '+1' . $digits;
        }

        // If 11 digits starting with 1, already has country code
        if (strlen($digits) === 11 && $digits[0] === '1') {
            return '+' . $digits;
        }

        // Otherwise, add + and return
        return '+' . $digits;
    }
}
