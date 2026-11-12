import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';

i18n.use(initReactI18next).init({
    fallbackLng: 'en',
    defaultNS: 'translation',
    interpolation: {
        escapeValue: false, // React already escapes
    },
    react: {
        useSuspense: false, // Important for Inertia.js
    },
    resources: {},
});

export default i18n;

/**
 * Hydrate i18n with translations from Inertia shared data.
 * Caches in localStorage. Automatically busts stale cache when
 * the server sends a different number of keys (after a deploy).
 */
export function hydrateTranslations(locale: string, translations: Record<string, string>) {
    if (!locale || !translations) return;

    const serverKeyCount = Object.keys(translations).length;

    try {
        const cachedCount = Number(localStorage.getItem(`i18n_${locale}_count`) || 0);

        if (cachedCount !== serverKeyCount) {
            // Server has more/different keys — clear stale cache for all locales
            Object.keys(localStorage)
                .filter((k) => k.startsWith('i18n_'))
                .forEach((k) => localStorage.removeItem(k));
        }

        // Write fresh translations to cache
        localStorage.setItem(`i18n_${locale}`, JSON.stringify(translations));
        localStorage.setItem(`i18n_${locale}_count`, String(serverKeyCount));
        localStorage.setItem('i18n_locale', locale);
    } catch {
        // localStorage full or unavailable — continue without caching
    }

    // Replace resource bundle with fresh data from server
    i18n.addResourceBundle(locale, 'translation', translations, true, true);

    // Trigger language change to re-render all useTranslation() consumers
    if (i18n.language !== locale) {
        i18n.changeLanguage(locale);
    } else {
        // Force i18next to emit a change event so all t() calls re-evaluate
        i18n.changeLanguage(locale);
    }
}

/**
 * Initialize i18n from localStorage cache for instant rendering
 * before Inertia props arrive. Avoids flash of untranslated content.
 */
export function initializeI18nFromCache() {
    try {
        const cachedLocale = localStorage.getItem('i18n_locale') || 'en';
        const cachedTranslations = localStorage.getItem(`i18n_${cachedLocale}`);

        if (cachedTranslations) {
            const translations = JSON.parse(cachedTranslations);
            i18n.addResourceBundle(cachedLocale, 'translation', translations, true, true);
            i18n.changeLanguage(cachedLocale);
        }
    } catch {
        // Ignore parse errors — fresh data will arrive via Inertia
    }
}
