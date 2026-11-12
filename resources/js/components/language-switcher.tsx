import { Link, router, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Globe, Check } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Button } from '@/components/ui/button';
import type { ActiveLanguage } from '@/types';

interface PageProps {
    locale: string;
    activeLanguages: ActiveLanguage[];
}

export function LanguageSwitcher() {
    const { locale, activeLanguages } = usePage().props as unknown as PageProps;
    const { i18n } = useTranslation();

    const currentLang = activeLanguages?.find((l) => l.code === locale);
    const hasMultiple = activeLanguages && activeLanguages.length > 1;

    const handleSwitch = (langCode: string) => {
        if (langCode === locale) return;

        // Optimistic: apply cached translations instantly from localStorage
        const cached = localStorage.getItem(`i18n_${langCode}`);
        if (cached) {
            try {
                i18n.addResourceBundle(langCode, 'translation', JSON.parse(cached), true, true);
                i18n.changeLanguage(langCode);
            } catch {
                // Ignore parse errors
            }
        }

        // Apply RTL immediately
        const lang = activeLanguages?.find((l) => l.code === langCode);
        if (lang) {
            document.documentElement.dir = lang.is_rtl ? 'rtl' : 'ltr';
            document.documentElement.lang = langCode;
        }

        // Persist to server (Inertia partial reload — only refreshes translations)
        router.post(
            '/api/set-locale',
            { locale: langCode },
            {
                preserveState: true,
                preserveScroll: true,
                only: ['locale', 'translations', 'activeLanguages'],
            },
        );
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative h-9 w-9 rounded-full"
                    aria-label="Switch language"
                >
                    {currentLang?.flag ? (
                        <span className="text-lg leading-none">{currentLang.flag}</span>
                    ) : (
                        <Globe className="h-[18px] w-[18px]" />
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-48">
                {/* Header row */}
                <div className="flex items-center gap-2 px-2 py-1.5">
                    <Globe className="text-muted-foreground h-3.5 w-3.5" />
                    <span className="text-muted-foreground text-xs font-medium uppercase tracking-wider">
                        Language
                    </span>
                </div>
                <DropdownMenuSeparator />

                {hasMultiple ? (
                    activeLanguages.map((lang) => (
                        <DropdownMenuItem
                            key={lang.code}
                            onClick={() => handleSwitch(lang.code)}
                            className="flex items-center gap-2.5 py-2"
                        >
                            <span className="w-5 text-center text-base leading-none">
                                {lang.flag || '🌐'}
                            </span>
                            <span className="flex-1 text-sm">
                                {lang.native_name || lang.name}
                            </span>
                            {lang.code === locale && (
                                <Check className="text-primary h-3.5 w-3.5 shrink-0" />
                            )}
                        </DropdownMenuItem>
                    ))
                ) : (
                    <div className="px-2 py-2 text-sm">
                        <div className="flex items-center gap-2.5">
                            <span className="w-5 text-center text-base leading-none">
                                {currentLang?.flag || '🌐'}
                            </span>
                            <span className="text-sm">
                                {currentLang?.native_name || currentLang?.name || 'English'}
                            </span>
                            <Check className="text-primary ml-auto h-3.5 w-3.5" />
                        </div>
                        <p className="text-muted-foreground mt-2 text-xs">
                            Add more languages in{' '}
                            <Link href="/admin/languages" className="text-primary underline-offset-2 hover:underline">
                                Settings
                            </Link>
                            .
                        </p>
                    </div>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
