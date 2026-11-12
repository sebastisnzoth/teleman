import AppLayout from '@/layouts/app-layout';
import Heading from '@/components/heading';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/react';
import {
    Settings,
    Sparkles,
    BarChart3,
    Zap,
    Gift,
    Briefcase,
    DollarSign,
    HelpCircle,
    MessageSquare
} from 'lucide-react';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { useTranslation } from 'react-i18next';

interface ThemeIndexProps {
    stats: {
        settings: number;
        hero: number;
        stats: number;
        features: number;
        benefits: number;
        use_cases: number;
        pricing: number;
        faqs: number;
        footer: number;
    };
}

export default function Index({ stats }: ThemeIndexProps) {
    const { t } = useTranslation();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('nav.dashboard'), href: dashboard().url },
        { title: t('admin.theme.management'), href: '/admin/theme' },
    ];

    const sections = [
        {
            title: t('admin.theme.sections.settings'),
            description: t('admin.theme.sections.settings_desc'),
            icon: Settings,
            href: '/admin/theme/settings',
            count: stats.settings,
            color: 'bg-blue-500',
        },
        {
            title: t('admin.theme.sections.hero'),
            description: t('admin.theme.sections.hero_desc'),
            icon: Sparkles,
            href: '/admin/theme/hero',
            count: stats.hero,
            color: 'bg-purple-500',
        },
        {
            title: t('admin.theme.sections.stats'),
            description: t('admin.theme.sections.stats_desc'),
            icon: BarChart3,
            href: '/admin/theme/stats',
            count: stats.stats,
            color: 'bg-green-500',
        },
        {
            title: t('admin.theme.sections.features'),
            description: t('admin.theme.sections.features_desc'),
            icon: Zap,
            href: '/admin/theme/features',
            count: stats.features,
            color: 'bg-yellow-500',
        },
        {
            title: t('admin.theme.sections.benefits'),
            description: t('admin.theme.sections.benefits_desc'),
            icon: Gift,
            href: '/admin/theme/benefits',
            count: stats.benefits,
            color: 'bg-pink-500',
        },
        {
            title: t('admin.theme.sections.use_cases'),
            description: t('admin.theme.sections.use_cases_desc'),
            icon: Briefcase,
            href: '/admin/theme/use-cases',
            count: stats.use_cases,
            color: 'bg-indigo-500',
        },
        {
            title: t('admin.theme.sections.pricing'),
            description: t('admin.theme.sections.pricing_desc'),
            icon: DollarSign,
            href: '/admin/theme/pricing',
            count: stats.pricing,
            color: 'bg-emerald-500',
        },
        {
            title: t('admin.theme.sections.faqs'),
            description: t('admin.theme.sections.faqs_desc'),
            icon: HelpCircle,
            href: '/admin/theme/faqs',
            count: stats.faqs,
            color: 'bg-orange-500',
        },
        {
            title: t('admin.theme.sections.footer'),
            description: t('admin.theme.sections.footer_desc'),
            icon: MessageSquare,
            href: '/admin/theme/footer',
            count: stats.footer,
            color: 'bg-red-500',
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('admin.theme.management')} />

            <div className="mx-auto max-w-7xl space-y-6">
                <Heading
                    title={t('admin.theme.management')}
                    description={t('admin.theme.management_desc')}
                />

                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {sections.map((section) => {
                        const Icon = section.icon;
                        return (
                            <Card key={section.href} className="hover:shadow-lg transition-shadow">
                                <CardHeader>
                                    <div className="flex items-center justify-between">
                                        <div className={`w-12 h-12 rounded-lg ${section.color} flex items-center justify-center text-white`}>
                                            <Icon className="w-6 h-6" />
                                        </div>
                                        <span className="text-2xl font-bold text-muted-foreground">
                                            {section.count}
                                        </span>
                                    </div>
                                    <CardTitle className="mt-4">{section.title}</CardTitle>
                                    <CardDescription>{section.description}</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <Button asChild className="w-full">
                                        <Link href={section.href}>
                                            {t('common.manage')}
                                        </Link>
                                    </Button>
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>

                <Card className="border-dashed">
                    <CardHeader>
                        <CardTitle>{t('admin.theme.cache_info')}</CardTitle>
                        <CardDescription>
                            {t('admin.theme.cache_info_desc')}
                        </CardDescription>
                    </CardHeader>
                </Card>
            </div>
        </AppLayout>
    );
}
