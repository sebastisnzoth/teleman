import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { AnswerRateHeatmap, ScheduleOptimizer } from '@/components/scheduling';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Clock, TrendingUp } from 'lucide-react';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { PageHelp } from '@/components/page-help';

interface Campaign {
  id: number;
  name: string;
}

interface Props {
  campaigns: Campaign[];
}

export default function SmartScheduling({ campaigns }: Props) {
  const { t } = useTranslation();
  const [selectedCampaignId, setSelectedCampaignId] = useState<number | null>(
    campaigns.length > 0 ? campaigns[0].id : null
  );

  const breadcrumbs: BreadcrumbItem[] = [
    {
      title: t('analytics.home'), href: dashboard().url,
    },
    {
      title: t('analytics.title'), href: '/analytics/dashboard',
    },
    {
      title: t('analytics.smart_scheduling'), href: '/analytics/smart-scheduling',
    },
  ];

  const helpSections = [
    {
      title: 'Smart Call Scheduling',
      content: 'Smart Scheduling uses AI to analyze historical call data and identify the optimal times to reach contacts. The system learns from patterns in answer rates across different times and days.',
    },
    {
      title: 'Answer Rate Heatmap',
      content: 'The heatmap visualizes answer rates by hour and day of the week. Darker colors indicate higher answer rates. Use this to identify the best calling windows for your campaigns.',
    },
    {
      title: 'Schedule Optimizer',
      content: 'Get AI-powered recommendations for the best times to schedule your calls. The optimizer considers historical data, contact time zones, and industry best practices to maximize answer rates.',
    },
    {
      title: 'Time Zones',
      content: 'All times are displayed in your local timezone. The system automatically adjusts recommendations based on contact locations to ensure calls are made during appropriate hours.',
    },
    {
      title: 'Best Practices',
      content: 'Schedule calls during peak answer rate windows shown in green/yellow. Avoid low-performing times (red zones). Allow at least 24 hours of data before relying on recommendations.',
    },
  ];

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={t('analytics.smart_call_scheduling')} />

      <div className="mx-auto max-w-7xl space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
              <Clock className="h-6 w-6 text-primary" />
              Smart Call Scheduling
            </h1>
            <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
              AI-powered call timing optimization for maximum answer rates
            </p>
          </div>
          <PageHelp title={t('analytics.smart_scheduling_help')} sections={helpSections} />
        </div>

        {/* Campaign Selector */}
        {campaigns.length > 0 ? (
          <>
            <Card>
              <CardHeader>
                <CardTitle>{t('analytics.select_campaign')}</CardTitle>
                <CardDescription>
                  View analytics and optimize schedule for a specific campaign
                </CardDescription>
              </CardHeader>
              <CardContent>
                <Select
                  value={selectedCampaignId?.toString()}
                  onValueChange={(value) => setSelectedCampaignId(Number(value))}
                >
                  <SelectTrigger className="w-full max-w-md">
                    <SelectValue placeholder="Choose a campaign" />
                  </SelectTrigger>
                  <SelectContent>
                    {campaigns.map((campaign) => (
                      <SelectItem key={campaign.id} value={campaign.id.toString()}>
                        {campaign.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </CardContent>
            </Card>

            {selectedCampaignId && (
              <div className="space-y-6">
                {/* Schedule Optimizer */}
                <ScheduleOptimizer campaignId={selectedCampaignId} />

                {/* Answer Rate Heatmap */}
                <AnswerRateHeatmap campaignId={selectedCampaignId} />
              </div>
            )}
          </>
        ) : (
          <Card>
            <CardContent className="py-12">
              <div className="text-center">
                <TrendingUp className="mx-auto h-12 w-12 text-gray-400" />
                <h3 className="mt-4 text-lg font-semibold text-gray-900 dark:text-gray-100">
                  No Campaigns Yet
                </h3>
                <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
                  Create a campaign to start using smart scheduling
                </p>
                <a
                  href="/campaigns/create"
                  className="mt-4 inline-flex items-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow hover:bg-primary/90"
                >
                  Create Campaign
                </a>
              </div>
            </CardContent>
          </Card>
        )}
      </div>
    </AppLayout>
  );
}
