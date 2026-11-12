import { Head, Link, usePage } from '@inertiajs/react';
import { type SharedData } from '@/types';
import { useTranslation } from 'react-i18next';
import { ArrowLeft, ArrowRight, Phone } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { dashboard, login } from '@/routes';

interface PageData {
    id: number;
    slug: string;
    title: string;
    content: string;
    meta_title: string | null;
    meta_description: string | null;
    updated_at: string;
}

interface Settings {
    site_name: string | null;
    logo_url: string | null;
}

interface Props {
    page: PageData;
    settings: Settings | null;
}

export default function PageShow({ page, settings }: Props) {
    const { t } = useTranslation();
    const { auth, appLogo } = usePage<SharedData>().props;

    return (
        <>
            <Head title={page.meta_title || page.title}>
                {page.meta_description && (
                    <meta name="description" content={page.meta_description} />
                )}
            </Head>

            <div className="min-h-screen bg-white">
                {/* Navigation */}
                <nav className="border-b bg-white/95 backdrop-blur supports-[backdrop-filter]:bg-white/60">
                    <div className="mx-auto flex max-w-4xl items-center justify-between px-6 py-3">
                        <Link href="/" className="flex items-center gap-2 group">
                            {(appLogo || settings?.logo_url) ? (
                                <img
                                    src={(appLogo || settings?.logo_url) as string}
                                    alt={settings?.site_name || 'Home'}
                                    className="h-8 w-auto"
                                />
                            ) : (
                                <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-black">
                                    <Phone className="h-4 w-4 text-white" />
                                </div>
                            )}
                            <span className="text-lg font-bold text-gray-900">
                                {settings?.site_name || 'Home'}
                            </span>
                        </Link>

                        <div className="flex items-center gap-2">
                            <Button variant="ghost" size="sm" asChild>
                                <Link href="/">
                                    <ArrowLeft className="mr-1 h-3.5 w-3.5" />
                                    {t('common.home')}
                                </Link>
                            </Button>
                            {auth.user ? (
                                <Button size="sm" asChild>
                                    <Link href={dashboard()}>
                                        {t('welcome.nav.dashboard')}
                                        <ArrowRight className="ml-1 h-3.5 w-3.5" />
                                    </Link>
                                </Button>
                            ) : (
                                <Button size="sm" asChild>
                                    <Link href={login()}>
                                        {t('welcome.nav.login')}
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </div>
                </nav>

                {/* Page Content */}
                <main className="mx-auto max-w-4xl px-6 py-12">
                    <h1 className="mb-8 text-4xl font-bold tracking-tight text-gray-900">
                        {page.title}
                    </h1>

                    <div
                        className="prose prose-lg prose-gray max-w-none
                            prose-headings:font-semibold prose-headings:tracking-tight
                            prose-h2:text-2xl prose-h2:mt-10 prose-h2:mb-4
                            prose-h3:text-xl prose-h3:mt-8 prose-h3:mb-3
                            prose-p:leading-relaxed prose-p:text-gray-600
                            prose-a:text-primary prose-a:no-underline hover:prose-a:underline
                            prose-li:text-gray-600
                            prose-strong:text-gray-900"
                        dangerouslySetInnerHTML={{ __html: page.content }}
                    />

                    <div className="mt-12 border-t pt-6 text-sm text-gray-500">
                        {t('admin.pages.last_updated')}: {new Date(page.updated_at).toLocaleDateString()}
                    </div>
                </main>

                {/* Simple Footer */}
                <footer className="border-t bg-gray-50">
                    <div className="mx-auto max-w-4xl px-6 py-6 text-center text-sm text-gray-500">
                        &copy; {new Date().getFullYear()} {settings?.site_name || 'Teleman'}. {t('common.all_rights_reserved')}
                    </div>
                </footer>
            </div>
        </>
    );
}
