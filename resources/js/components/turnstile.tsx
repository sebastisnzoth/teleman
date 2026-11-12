import { useEffect, useRef } from 'react';
import { usePage } from '@inertiajs/react';

declare global {
    interface Window {
        turnstile?: {
            render: (container: HTMLElement, options: Record<string, unknown>) => string;
            reset: (widgetId: string) => void;
            remove: (widgetId: string) => void;
        };
    }
}

export function Turnstile() {
    const { turnstile } = usePage().props as any;
    const containerRef = useRef<HTMLDivElement>(null);
    const widgetIdRef = useRef<string | null>(null);

    const enabled = turnstile?.enabled && turnstile?.siteKey;

    useEffect(() => {
        if (!enabled) return;

        const scriptId = 'cf-turnstile-script';
        if (!document.getElementById(scriptId)) {
            const script = document.createElement('script');
            script.id = scriptId;
            script.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
            script.async = true;
            document.head.appendChild(script);
        }

        const renderWidget = () => {
            if (!window.turnstile || !containerRef.current) return;

            if (widgetIdRef.current) {
                try { window.turnstile.remove(widgetIdRef.current); } catch {}
            }

            // Turnstile automatically creates a hidden input named
            // "cf-turnstile-response" inside the container with the token value.
            // No manual hidden input needed.
            widgetIdRef.current = window.turnstile.render(containerRef.current, {
                sitekey: turnstile.siteKey,
                theme: 'auto',
                size: 'flexible',
            });
        };

        if (window.turnstile) {
            renderWidget();
        } else {
            const script = document.getElementById(scriptId);
            script?.addEventListener('load', renderWidget);
        }

        return () => {
            if (widgetIdRef.current && window.turnstile) {
                try { window.turnstile.remove(widgetIdRef.current); } catch {}
            }
        };
    }, [enabled, turnstile?.siteKey]);

    if (!enabled) return null;

    return <div ref={containerRef} />;
}
