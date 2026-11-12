import { usePage } from '@inertiajs/react';
import { type CSSProperties, type ReactNode } from 'react';

export type BrandSurface = 'landing' | 'dashboard' | 'auth';
export type BrandMode = 'logo_text' | 'text_only' | 'logo_only';

interface SurfaceCfg {
    mode: BrandMode;
    size: number;
}
interface BrandConfig {
    name?: string;
    logoLight?: string | null;
    logoDark?: string | null;
    landing?: SurfaceCfg;
    dashboard?: SurfaceCfg;
    auth?: SurfaceCfg;
}
interface PageProps extends Record<string, unknown> {
    brand?: BrandConfig;
    appName?: string;
    appLogo?: string;
}

const DEFAULTS: Record<BrandSurface, SurfaceCfg> = {
    landing: { mode: 'logo_text', size: 34 },
    dashboard: { mode: 'logo_text', size: 32 },
    auth: { mode: 'logo_text', size: 40 },
};

/** Resolve the branding config for a given surface from shared Inertia props. */
export function useBrand(surface: BrandSurface) {
    const { props } = usePage<PageProps>();
    const brand = props.brand;
    const cfg = brand?.[surface] ?? DEFAULTS[surface];
    // When the brand config is present, trust its (possibly null) logo — do NOT
    // fall back to props.appLogo, which is a config default path that may 404
    // and render its alt text. Only use appLogo in the legacy no-brand case.
    const light = brand ? (brand.logoLight ?? null) : (props.appLogo ?? null);
    const dark = brand ? (brand.logoDark ?? brand.logoLight ?? null) : (props.appLogo ?? null);
    return {
        name: brand?.name || props.appName || 'App',
        light,
        dark,
        mode: cfg.mode,
        size: cfg.size || DEFAULTS[surface].size,
        showLogo: cfg.mode !== 'text_only',
        showText: cfg.mode !== 'logo_only',
    };
}

function LogoImg({ src, size, alt, className }: { src: string; size: number; alt: string; className?: string }) {
    const style: CSSProperties = { height: size, width: 'auto', flex: 'none', objectFit: 'contain' };
    return <img src={src} alt={alt} style={style} className={className} />;
}

interface BrandLogoProps {
    surface: BrandSurface;
    /** When true, swap light/dark by the active theme (Tailwind `.dark`). Off for the always-light landing. */
    themeSwap?: boolean;
    /** Always use the dark logo (for a permanently dark background, e.g. the split auth panel). */
    forceDark?: boolean;
    className?: string;
    textClassName?: string;
    /** Mark shown when no logo image has been uploaded. */
    fallback?: ReactNode;
}

/**
 * Renders the brand mark for a surface honoring its display mode
 * (logo+text / text only / logo only), exact pixel size, and light/dark logo.
 */
export function BrandLogo({ surface, themeSwap = true, forceDark = false, className, textClassName, fallback }: BrandLogoProps) {
    const b = useBrand(surface);
    const hasTwo = !!(b.light && b.dark && b.dark !== b.light);

    return (
        <span className={className} style={{ display: 'inline-flex', alignItems: 'center', gap: 9 }}>
            {b.showLogo &&
                (b.light ? (
                    forceDark ? (
                        <LogoImg src={b.dark || b.light} size={b.size} alt={b.name} />
                    ) : !themeSwap || !hasTwo ? (
                        <LogoImg src={b.light} size={b.size} alt={b.name} />
                    ) : (
                        <>
                            <LogoImg src={b.light} size={b.size} alt={b.name} className="block dark:hidden" />
                            <LogoImg src={b.dark!} size={b.size} alt={b.name} className="hidden dark:block" />
                        </>
                    )
                ) : (
                    fallback ?? null
                ))}
            {b.showText && <span className={textClassName}>{b.name}</span>}
        </span>
    );
}
