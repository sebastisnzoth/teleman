/**
 * Phone number validation utility
 * Validates phone numbers in E.164 format
 */

export interface PhoneValidationResult {
  isValid: boolean;
  error?: string;
  formatted?: string;
}

/**
 * Validates a phone number
 * Accepts E.164 format: +[country code][number]
 * Example: +12345678900, +447890123456
 */
export function validatePhoneNumber(phone: string): PhoneValidationResult {
  // Remove whitespace
  const cleaned = phone.trim();

  // Check if empty
  if (!cleaned) {
    return {
      isValid: false,
      error: 'Phone number is required',
    };
  }

  // Check if starts with +
  if (!cleaned.startsWith('+')) {
    return {
      isValid: false,
      error: 'Phone number must start with + (E.164 format)',
    };
  }

  // Remove + for validation
  const numberPart = cleaned.substring(1);

  // Check if contains only digits after +
  if (!/^\d+$/.test(numberPart)) {
    return {
      isValid: false,
      error: 'Phone number can only contain digits after +',
    };
  }

  // Check length (E.164 allows 1-15 digits after country code)
  // Minimum: +1234567890 (10 digits, e.g., US)
  // Maximum: +123456789012345 (15 digits)
  if (numberPart.length < 10) {
    return {
      isValid: false,
      error: 'Phone number is too short (min 10 digits)',
    };
  }

  if (numberPart.length > 15) {
    return {
      isValid: false,
      error: 'Phone number is too long (max 15 digits)',
    };
  }

  return {
    isValid: true,
    formatted: cleaned,
  };
}

/**
 * Validates multiple phone numbers
 */
export function validatePhoneNumbers(phones: string[]): {
  valid: string[];
  invalid: Array<{ phone: string; error: string }>;
} {
  const valid: string[] = [];
  const invalid: Array<{ phone: string; error: string }> = [];

  phones.forEach((phone) => {
    const result = validatePhoneNumber(phone);
    if (result.isValid && result.formatted) {
      valid.push(result.formatted);
    } else {
      invalid.push({
        phone,
        error: result.error || 'Invalid phone number',
      });
    }
  });

  return { valid, invalid };
}

/**
 * Parse phone numbers from various input formats
 * Supports: comma-separated, newline-separated, space-separated
 */
export function parsePhoneNumbers(input: string): string[] {
  return input
    .split(/[\n,;\s]+/) // Split by newline, comma, semicolon, or space
    .map((phone) => phone.trim())
    .filter((phone) => phone.length > 0);
}

/**
 * Normalize a phone number to E.164 format.
 * When the input has no country code prefix, assumes the provided default country.
 *
 * Examples (defaultCountry='US'):
 *   "(208) 342-1813"   -> "+12083421813"
 *   "208-342-1813"     -> "+12083421813"
 *   "2083421813"       -> "+12083421813"
 *   "12083421813"      -> "+12083421813"
 *   "+12083421813"     -> "+12083421813" (unchanged)
 *   "+447911123456"    -> "+447911123456" (unchanged, already international)
 *
 * Critically, a bare "2083421813" must NEVER be interpreted as Egyptian (+20)
 * — it must be treated as a local number under defaultCountry.
 */
export function normalizeToE164(phone: string, defaultCountry: 'US' | 'CA' | 'GB' | 'AU' = 'US'): string {
  if (!phone) return '';

  const trimmed = phone.trim();

  // Already in E.164 format — preserve as-is (even if it's non-US)
  if (trimmed.startsWith('+')) {
    const digits = trimmed.slice(1).replace(/\D/g, '');
    return digits ? `+${digits}` : '';
  }

  // No + prefix — treat as local number, strip formatting
  const digitsOnly = trimmed.replace(/\D/g, '');
  if (!digitsOnly) return '';

  // Country calling codes for NANP / common defaults
  const countryCallingCodes: Record<string, string> = {
    US: '1',
    CA: '1',
    GB: '44',
    AU: '61',
  };
  const cc = countryCallingCodes[defaultCountry] || '1';

  // For NANP countries (US/CA): 10 digits = local, 11 digits starting with 1 = national
  if (cc === '1') {
    if (digitsOnly.length === 10) {
      return `+1${digitsOnly}`;
    }
    if (digitsOnly.length === 11 && digitsOnly.startsWith('1')) {
      return `+${digitsOnly}`;
    }
  }

  // For UK/AU: often has a leading 0 for national dialing that must be stripped
  if (cc !== '1' && digitsOnly.startsWith('0')) {
    return `+${cc}${digitsOnly.slice(1)}`;
  }

  // Fallback: prepend default country calling code
  return `+${cc}${digitsOnly}`;
}
