import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

import AppearanceTabs from '@/components/appearance-tabs';
import FontSelector from '@/components/font-selector';
import HeadingSmall from '@/components/heading-small';
import { type BreadcrumbItem } from '@/types';

import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { edit as editAppearance } from '@/routes/appearance';
import { PageHelp } from '@/components/page-help';

export default function Appearance() {
    const { t } = useTranslation();
  const breadcrumbs: BreadcrumbItem[] = [
    {
        title: t('settings.appearance'),
        href: editAppearance().url,
    },
];

    const helpSections = [
        {
            title: t('settings.appearance_theme'),
            content: t('settings.appearance_theme_description'),
        },
        {
            title: t('settings.appearance_font'),
            content: t('settings.appearance_font_description'),
        },
        {
            title: t('settings.appearance_color'),
            content: t('settings.appearance_color_description'),
        },
        {
            title: t('settings.appearance_accessibility'),
            content: t('settings.appearance_accessibility_description'),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('settings.appearance_title')} />

            <SettingsLayout>
                <div className="space-y-6">
                    <div className="flex items-center justify-between">
                        <HeadingSmall
                            title={t('settings.appearance_title')}
                            description={t('settings.appearance_update')}
                        />
                        <PageHelp title="Appearance Help" sections={helpSections} />
                    </div>
                    <AppearanceTabs />
                    <FontSelector />
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
