import { usePage } from '@inertiajs/react';
import { useCallback } from 'react';
import type { SharedData } from '@/types';
import { formatCurrency, getCurrencySymbol } from '@/lib/currency';

/**
 * Hook to access the system's base currency from Inertia shared props.
 */
export function useBaseCurrency() {
    const { baseCurrency = 'USD', baseCurrencySymbol = '$' } = usePage<SharedData>().props;
    return { baseCurrency, baseCurrencySymbol };
}

/**
 * Hook that returns a bound formatCurrency function using the system's base currency.
 */
export function useFormatCurrency() {
    const { baseCurrency } = useBaseCurrency();

    const format = useCallback(
        (amount: number, currency?: string, decimals?: number) => {
            return formatCurrency(amount, currency ?? baseCurrency, decimals);
        },
        [baseCurrency],
    );

    const symbol = getCurrencySymbol(baseCurrency);

    return { formatCurrency: format, baseCurrency, baseCurrencySymbol: symbol };
}
