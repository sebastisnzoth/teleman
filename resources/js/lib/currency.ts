/**
 * Currency symbols mapping.
 */
export const CURRENCY_SYMBOLS: Record<string, string> = {
    USD: '$',
    EUR: '€',
    GBP: '£',
    JPY: '¥',
    CNY: '¥',
    INR: '₹',
    NGN: '₦',
    GHS: 'GH₵',
    ZAR: 'R',
    KES: 'KSh',
    BRL: 'R$',
    AUD: 'A$',
    CAD: 'C$',
    CHF: 'CHF',
    SEK: 'kr',
    NOK: 'kr',
    DKK: 'kr',
    PLN: 'zł',
    CZK: 'Kč',
    HUF: 'Ft',
    RUB: '₽',
    TRY: '₺',
    MXN: 'MX$',
    SGD: 'S$',
    HKD: 'HK$',
    NZD: 'NZ$',
    KRW: '₩',
    THB: '฿',
    MYR: 'RM',
    PHP: '₱',
    IDR: 'Rp',
    AED: 'د.إ',
    SAR: '﷼',
    EGP: 'E£',
    UGX: 'USh',
    TZS: 'TSh',
    XOF: 'CFA',
    XAF: 'FCFA',
    RWF: 'FRw',
};

/**
 * Common currencies list for dropdowns.
 */
export const CURRENCY_LIST = [
    { code: 'USD', name: 'US Dollar', symbol: '$' },
    { code: 'EUR', name: 'Euro', symbol: '€' },
    { code: 'GBP', name: 'British Pound', symbol: '£' },
    { code: 'JPY', name: 'Japanese Yen', symbol: '¥' },
    { code: 'CAD', name: 'Canadian Dollar', symbol: 'C$' },
    { code: 'AUD', name: 'Australian Dollar', symbol: 'A$' },
    { code: 'CHF', name: 'Swiss Franc', symbol: 'CHF' },
    { code: 'CNY', name: 'Chinese Yuan', symbol: '¥' },
    { code: 'INR', name: 'Indian Rupee', symbol: '₹' },
    { code: 'BRL', name: 'Brazilian Real', symbol: 'R$' },
    { code: 'NGN', name: 'Nigerian Naira', symbol: '₦' },
    { code: 'GHS', name: 'Ghanaian Cedi', symbol: 'GH₵' },
    { code: 'ZAR', name: 'South African Rand', symbol: 'R' },
    { code: 'KES', name: 'Kenyan Shilling', symbol: 'KSh' },
    { code: 'EGP', name: 'Egyptian Pound', symbol: 'E£' },
    { code: 'AED', name: 'UAE Dirham', symbol: 'د.إ' },
    { code: 'SAR', name: 'Saudi Riyal', symbol: '﷼' },
    { code: 'MXN', name: 'Mexican Peso', symbol: 'MX$' },
    { code: 'SGD', name: 'Singapore Dollar', symbol: 'S$' },
    { code: 'HKD', name: 'Hong Kong Dollar', symbol: 'HK$' },
    { code: 'NZD', name: 'New Zealand Dollar', symbol: 'NZ$' },
    { code: 'SEK', name: 'Swedish Krona', symbol: 'kr' },
    { code: 'NOK', name: 'Norwegian Krone', symbol: 'kr' },
    { code: 'DKK', name: 'Danish Krone', symbol: 'kr' },
    { code: 'PLN', name: 'Polish Zloty', symbol: 'zł' },
    { code: 'TRY', name: 'Turkish Lira', symbol: '₺' },
    { code: 'KRW', name: 'South Korean Won', symbol: '₩' },
    { code: 'THB', name: 'Thai Baht', symbol: '฿' },
    { code: 'MYR', name: 'Malaysian Ringgit', symbol: 'RM' },
    { code: 'PHP', name: 'Philippine Peso', symbol: '₱' },
    { code: 'IDR', name: 'Indonesian Rupiah', symbol: 'Rp' },
    { code: 'RUB', name: 'Russian Ruble', symbol: '₽' },
];

/**
 * Get currency symbol for a given currency code.
 */
export function getCurrencySymbol(currencyCode: string): string {
    return CURRENCY_SYMBOLS[currencyCode.toUpperCase()] ?? currencyCode;
}

/**
 * Format an amount with its currency symbol.
 */
export function formatCurrency(amount: number, currencyCode: string = 'USD', decimals: number = 2): string {
    const code = currencyCode.toUpperCase();

    try {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: code,
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        }).format(amount);
    } catch {
        // Fallback for unsupported currency codes in Intl
        const symbol = getCurrencySymbol(code);
        return `${symbol}${amount.toFixed(decimals)}`;
    }
}
