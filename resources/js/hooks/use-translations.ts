import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { hydrateTranslations } from '@/lib/i18n';

export interface ActiveLanguage {
    id: number;
    code: string;
    name: string;
    native_name: string;
    flag: string;
    is_rtl: boolean;
    is_default: boolean;
}

interface I18nPageProps {
    locale: string;
    translations: Record<string, string>;
    activeLanguages: ActiveLanguage[];
}

/**
 * Syncs translations from Inertia shared data into i18next.
 * Also handles RTL and lang attribute on <html>.
 */
export function useTranslationSync() {
    const props = usePage().props as unknown as I18nPageProps;
    const { locale, translations, activeLanguages } = props;

    useEffect(() => {
        if (locale && translations) {
            hydrateTranslations(locale, translations);
        }
    }, [locale, translations]);

    // Apply RTL and lang attribute
    useEffect(() => {
        if (!locale || !activeLanguages) return;

        const currentLang = activeLanguages.find((l) => l.code === locale);
        if (currentLang) {
            document.documentElement.dir = currentLang.is_rtl ? 'rtl' : 'ltr';
            document.documentElement.lang = locale;
        }
    }, [locale, activeLanguages]);

    return { locale, activeLanguages };
}
