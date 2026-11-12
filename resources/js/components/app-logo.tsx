import AppLogoIcon from './app-logo-icon';
import { useBrand } from './brand-logo';

export default function AppLogo() {
    const b = useBrand('dashboard');
    const hasTwo = !!(b.light && b.dark && b.dark !== b.light);

    return (
        <>
            {b.showLogo && (
                <div className="flex items-center justify-center" style={{ height: b.size, minWidth: b.size }}>
                    {b.light ? (
                        hasTwo ? (
                            <>
                                <img src={b.light} alt={b.name} className="block w-auto object-contain dark:hidden" style={{ height: b.size }} />
                                <img src={b.dark!} alt={b.name} className="hidden w-auto object-contain dark:block" style={{ height: b.size }} />
                            </>
                        ) : (
                            <img src={b.light} alt={b.name} className="w-auto object-contain" style={{ height: b.size }} />
                        )
                    ) : (
                        <div className="flex aspect-square size-8 items-center justify-center rounded-md text-sidebar-primary-foreground">
                            <AppLogoIcon className="size-5 fill-current text-white dark:text-black" />
                        </div>
                    )}
                </div>
            )}
            {b.showText && (
                <div className="ml-1 grid flex-1 text-left text-sm">
                    <span className="mb-0.5 truncate leading-tight font-semibold">{b.name}</span>
                </div>
            )}
        </>
    );
}
