import '../css/app.css';
import './bootstrap';

import * as Sentry from '@sentry/react';
import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { I18nextProvider } from 'react-i18next';
import { initializeTheme } from './hooks/use-appearance';
import { initializeFont } from './hooks/use-font';
import i18n, { hydrateTranslations, initializeI18nFromCache } from './lib/i18n';
import { Toaster } from 'sonner';
import { SoftphoneProvider } from './contexts/SoftphoneContext';

const fallbackAppName = import.meta.env.VITE_APP_NAME || 'Laravel';
let dynamicAppName = fallbackAppName;

// Initialize Sentry
if (import.meta.env.VITE_SENTRY_DSN) {
    Sentry.init({
        dsn: import.meta.env.VITE_SENTRY_DSN,
        environment: import.meta.env.VITE_SENTRY_ENVIRONMENT || 'development',
        integrations: [
            Sentry.browserTracingIntegration(),
            Sentry.replayIntegration({
                maskAllText: false,
                blockAllMedia: false,
            }),
        ],
        // Performance Monitoring
        tracesSampleRate: parseFloat(import.meta.env.VITE_SENTRY_TRACES_SAMPLE_RATE || '1.0'),
        // Session Replay
        replaysSessionSampleRate: parseFloat(import.meta.env.VITE_SENTRY_REPLAYS_SESSION_SAMPLE_RATE || '0.1'),
        replaysOnErrorSampleRate: parseFloat(import.meta.env.VITE_SENTRY_REPLAYS_ON_ERROR_SAMPLE_RATE || '1.0'),
    });
}

// Update CSRF token and i18n translations on every Inertia page load
router.on('navigate', (event) => {
    const page = event.detail.page;
    const props = page.props as Record<string, unknown>;

    // Sync i18n before React re-renders so t() returns correct values immediately
    const locale = props.locale as string | undefined;
    const translations = props.translations as Record<string, string> | undefined;
    if (locale && translations && typeof translations === 'object') {
        hydrateTranslations(locale, translations);
    }

    // Sync dynamic app name from Inertia shared props
    const sharedAppName = props.appName as string | undefined;
    if (sharedAppName) {
        dynamicAppName = sharedAppName;
    }

    // Sync CSRF token
    const csrfToken = props.csrf_token as string | undefined;
    if (csrfToken && typeof csrfToken === 'string') {
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        if (metaTag) {
            metaTag.setAttribute('content', csrfToken);
        }
        if (window.axios) {
            window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken;
        }
    }
});

createInertiaApp({
    title: (title) => (title ? `${title} - ${dynamicAppName}` : dynamicAppName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.tsx`,
            import.meta.glob('./pages/**/*.tsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(
            <StrictMode>
                <I18nextProvider i18n={i18n}>
                    <Toaster position="top-right" expand={false} richColors closeButton />
                    <SoftphoneProvider>
                        <App {...props} />
                    </SoftphoneProvider>
                </I18nextProvider>
            </StrictMode>,
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();

// This will set the dashboard font on load...
initializeFont();

// Load cached translations for instant rendering
initializeI18nFromCache();
