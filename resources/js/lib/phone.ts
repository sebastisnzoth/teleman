/**
 * Format an E.164 phone number for display.
 * US/Canada (+1XXXXXXXXXX) → (XXX) XXX-XXXX
 * All others → returned as-is.
 */
export function formatPhone(e164: string | null | undefined): string {
    if (!e164) return '';

    const digits = e164.replace(/\D/g, '');

    // US/Canada: country code 1 + 10 digits = 11 digits total
    if (digits.length === 11 && digits.startsWith('1')) {
        const area = digits.slice(1, 4);
        const prefix = digits.slice(4, 7);
        const line = digits.slice(7, 11);
        return `(${area}) ${prefix}-${line}`;
    }

    return e164;
}
