import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AppLayout from '@/layouts/app-layout';
import { HotLeadsDashboard } from '@/components/analytics/hot-leads-dashboard';
import { router } from '@inertiajs/react';
import { Flame } from 'lucide-react';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { PageHelp } from '@/components/page-help';

export default function HotLeads() {
  const { t } = useTranslation();
    const handleContactClick = (contactId: number) => {
        router.visit(`/contacts/${contactId}`);
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('analytics.home'), href: dashboard() },
        { title: t('analytics.title'), href: '/analytics' },
        { title: 'Hot Leads' }
    ];

    const helpSections = [
        {
            title: 'What are Hot Leads?',
            content: 'Hot Leads are AI-identified high-priority contacts who have shown strong engagement signals during calls. Our AI analyzes conversation patterns, sentiment, and engagement metrics to identify leads most likely to convert.',
        },
        {
            title: 'AI Detection Criteria',
            content: 'The AI evaluates multiple factors: positive sentiment in conversation, high engagement duration, key buying signals, questions about products/services, and request for follow-up or additional information.',
        },
        {
            title: 'Priority Score',
            content: 'Each hot lead is assigned a priority score from 1-100 based on AI analysis. Higher scores indicate stronger buying signals and conversion potential. Focus on leads with scores above 70 for best results.',
        },
        {
            title: 'Taking Action',
            content: 'Click on any lead to view full contact details and call history. You can schedule follow-up calls, add notes, or immediately contact high-priority leads. Act quickly on fresh hot leads for best conversion rates.',
        },
        {
            title: 'Best Practices',
            content: 'Review hot leads daily, prioritize by score and recency, personalize your follow-up based on call notes, and track conversion rates to refine your approach.',
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('analytics.hot_leads')} />

            <div className="mx-auto max-w-7xl space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                            <Flame className="h-6 w-6 text-primary" />
                            Hot Leads
                        </h1>
                        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            AI-identified high-priority leads from all campaigns
                        </p>
                    </div>
                    <PageHelp title={t('analytics.hot_leads_help')} sections={helpSections} />
                </div>

                <HotLeadsDashboard onContactClick={handleContactClick} />
            </div>
        </AppLayout>
    );
}
