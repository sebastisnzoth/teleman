import { useCallback, useEffect, useState } from 'react';

export type DashboardFont = 'inter' | 'instrument-sans' | 'geist-mono' | 'system-ui' | 'arial' | 'georgia';

const fontFamilies: Record<DashboardFont, string> = {
    'inter': "'Inter', ui-sans-serif, system-ui, sans-serif",
    'instrument-sans': "'Instrument Sans', ui-sans-serif, system-ui, sans-serif",
    'geist-mono': "'Geist Mono', ui-monospace, monospace",
    'system-ui': "system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif",
    'arial': "Arial, Helvetica, sans-serif",
    'georgia': "Georgia, 'Times New Roman', serif",
};

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;
    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const applyFont = (font: DashboardFont) => {
    const fontFamily = fontFamilies[font];
    // Apply to body element for entire application
    if (typeof document !== 'undefined') {
        document.body.style.fontFamily = fontFamily;
    }
};

export function initializeFont() {
    const savedFont = (localStorage.getItem('dashboard-font') as DashboardFont) || 'inter';
    applyFont(savedFont);
}

export function useFont() {
    const [font, setFont] = useState<DashboardFont>('inter');

    const updateFont = useCallback((selectedFont: DashboardFont) => {
        setFont(selectedFont);

        // Store in localStorage for client-side persistence
        localStorage.setItem('dashboard-font', selectedFont);

        // Store in cookie for SSR
        setCookie('dashboard-font', selectedFont);

        applyFont(selectedFont);
    }, []);

    useEffect(() => {
        const savedFont = localStorage.getItem('dashboard-font') as DashboardFont | null;

        if (savedFont) {
            setFont(savedFont);
        }
    }, []);

    return { font, updateFont, fontFamilies } as const;
}
