import { useTranslation } from 'react-i18next';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Link, Head } from '@inertiajs/react';
import { Phone, TrendingUp, Clock, Activity, BarChart3 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { PageHelp } from '@/components/page-help';
import { isHidden, useTelephony } from '@/lib/telephony';

interface Campaign {
  id: number;
  name: string;
  type: string;
  status: string;
  total_contacts: number;
  total_called: number;
  total_answered: number;
  created_at: string;
}

interface Call {
  id: number;
  to_number: string;
  status: string;
  duration: number | null;
  created_at: string;
}

interface Analytics {
  total_campaigns: number;
  active_campaigns: number;
  completed_campaigns: number;
  total_calls: number;
  total_answered: number;
  average_answer_rate: number;
  total_duration: number;
  total_duration_formatted: string;
  recent_calls: Call[];
}

interface Props {
  analytics: Analytics;
  recentCampaigns: Campaign[];
}

export default function AnalyticsDashboard({
  analytics = {
    total_campaigns: 0,
    active_campaigns: 0,
    completed_campaigns: 0,
    total_calls: 0,
    total_answered: 0,
    average_answer_rate: 0,
    total_duration: 0,
    total_duration_formatted: '0s',
    recent_calls: []
  },
  recentCampaigns = []
}: Props) {
  const { t } = useTranslation();
  const { hidden } = useTelephony();

  // A campaign's total_answered counts only the calls answering-machine
  // detection confirmed were picked up, so where the provider never
  // reports that it stays 0 for ever — "120 called, 0 answered" reads as
  // a broken page rather than as a measurement nobody switched on, so
  // the answered half is dropped and the called half, which is right on
  // every provider, is kept. The cards above this list are unaffected:
  // AnalyticsService counts those from call status, not from that column.
  // See config('telephony.hidden_features').
  const showAnswered = !isHidden('answered_stats', hidden);

  const statusConfig: Record<string, { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' }> = {
    draft: { label: t('common.draft'), variant: 'outline' },
    running: { label: t('analytics.status_running'), variant: 'default' },
    paused: { label: t('common.paused'), variant: 'secondary' },
    completed: { label: t('common.completed'), variant: 'secondary' },
    failed: { label: t('analytics.status_failed'), variant: 'destructive' },
  };

  const callStatusConfig: Record<string, { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' }> = {
    initiated: { label: t('analytics.call_status_initiated'), variant: 'outline' },
    ringing: { label: t('calls.status_ringing'), variant: 'default' },
    'in-progress': { label: t('calls.status_in_progress'), variant: 'default' },
    completed: { label: t('calls.status_completed'), variant: 'secondary' },
    busy: { label: t('calls.status_busy'), variant: 'secondary' },
    failed: { label: t('calls.status_failed'), variant: 'destructive' },
    'no-answer': { label: t('calls.status_no_answer'), variant: 'secondary' },
  };

  const formatDuration = (seconds: number | null) => {
    if (!seconds) return '0s';
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return mins > 0 ? `${mins}m ${secs}s` : `${secs}s`;
  };

  const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleString();
  };

  const breadcrumbs: BreadcrumbItem[] = [
    {
      title: t('nav.home'),
      href: dashboard().url,
    },
    {
      title: t('nav.analytics'),
      href: '/analytics/dashboard',
    },
  ];

  const helpSections = [
    {
      title: t('analytics.help_overview_title'),
      content: t('analytics.help_overview_content'),
    },
    {
      title: t('analytics.help_campaign_metrics_title'),
      content: t('analytics.help_campaign_metrics_content'),
    },
    {
      title: t('analytics.help_call_stats_title'),
      content: t('analytics.help_call_stats_content'),
    },
    {
      title: t('analytics.help_call_duration_title'),
      content: t('analytics.help_call_duration_content'),
    },
    {
      title: t('analytics.help_recent_activity_title'),
      content: t('analytics.help_recent_activity_content'),
    },
    {
      title: t('analytics.help_performance_insights_title'),
      content: t('analytics.help_performance_insights_content'),
    },
  ];

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={t('nav.analytics')} />
      <div className="mx-auto max-w-7xl space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold tracking-tight">{t('nav.analytics')}</h1>
            <p className="mt-1 text-sm text-slate-500">
              {t('analytics.dashboard_subtitle')}
            </p>
          </div>
          <PageHelp title={t('analytics.help_title')} sections={helpSections} />
        </div>

        {/* Overview Stats */}
        <div className="grid gap-4 md:grid-cols-4">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">{t('analytics.total_campaigns')}</CardTitle>
              <Activity className="h-4 w-4 text-slate-500" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{analytics.total_campaigns}</div>
              <p className="text-xs text-slate-500">
                {t('analytics.active_completed_summary', {
                  active: analytics.active_campaigns,
                  completed: analytics.completed_campaigns,
                })}
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">{t('analytics.total_calls')}</CardTitle>
              <Phone className="h-4 w-4 text-slate-500" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{analytics.total_calls}</div>
              <p className="text-xs text-slate-500">
                {t('analytics.answered_count', { count: analytics.total_answered })}
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">{t('analytics.average_answer_rate')}</CardTitle>
              <TrendingUp className="h-4 w-4 text-slate-500" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{analytics.average_answer_rate}%</div>
              <p className="text-xs text-slate-500">
                {t('analytics.across_all_campaigns')}
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">{t('analytics.total_call_duration')}</CardTitle>
              <Clock className="h-4 w-4 text-slate-500" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{analytics.total_duration_formatted}</div>
              <p className="text-xs text-slate-500">
                {t('analytics.seconds_count', { count: analytics.total_duration })}
              </p>
            </CardContent>
          </Card>
        </div>

        <div className="grid gap-6 md:grid-cols-2">
          {/* Recent Campaigns */}
          <Card>
            <CardHeader>
              <CardTitle>{t('dashboard.recent_campaigns')}</CardTitle>
              <CardDescription>{t('analytics.latest_campaign_activity')}</CardDescription>
            </CardHeader>
            <CardContent>
              {recentCampaigns.length > 0 ? (
                <div className="space-y-4">
                  {recentCampaigns.map((campaign) => (
                    <div key={campaign.id} className="flex items-center justify-between border-b pb-3 last:border-0">
                      <div className="flex-1">
                        <div className="flex items-center gap-2">
                          <Link
                            href={`/campaigns/${campaign.id}`}
                            className="font-medium hover:underline"
                          >
                            {campaign.name}
                          </Link>
                          <Badge variant={statusConfig[campaign.status]?.variant || 'outline'}>
                            {statusConfig[campaign.status]?.label || campaign.status}
                          </Badge>
                        </div>
                        <p className="mt-1 text-sm text-slate-500 capitalize">
                          {campaign.type.replace('_', ' ')} • {t('analytics.contacts_count', { count: campaign.total_contacts })}
                        </p>
                        {campaign.total_called > 0 && (
                          <p className="mt-1 text-xs text-slate-600">
                            {showAnswered
                              ? t('analytics.called_answered_summary', {
                                  called: campaign.total_called,
                                  answered: campaign.total_answered,
                                })
                              : t('analytics.called_summary', {
                                  called: campaign.total_called,
                                })}
                          </p>
                        )}
                      </div>
                      {(campaign.total_called > 0 || ['running', 'completed'].includes(campaign.status)) && (
                        <Link href={`/analytics/campaigns/${campaign.id}`}>
                          <Button variant="ghost" size="sm">
                            <BarChart3 className="h-4 w-4" />
                          </Button>
                        </Link>
                      )}
                    </div>
                  ))}
                </div>
              ) : (
                <p className="text-center text-sm text-slate-500 py-8">{t('dashboard.no_campaigns')}</p>
              )}
            </CardContent>
          </Card>

          {/* Recent Calls */}
          <Card>
            <CardHeader>
              <CardTitle>{t('dashboard.recent_calls')}</CardTitle>
              <CardDescription>{t('analytics.latest_call_activity')}</CardDescription>
            </CardHeader>
            <CardContent>
              {analytics.recent_calls.length > 0 ? (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>{t('analytics.phone_number')}</TableHead>
                      <TableHead>{t('common.status')}</TableHead>
                      <TableHead>{t('common.duration')}</TableHead>
                      <TableHead>{t('common.time')}</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {analytics.recent_calls.map((call) => (
                      <TableRow key={call.id}>
                        <TableCell className="font-medium">{call.to_number}</TableCell>
                        <TableCell>
                          <Badge variant={callStatusConfig[call.status]?.variant || 'outline'} className="text-xs">
                            {callStatusConfig[call.status]?.label || call.status}
                          </Badge>
                        </TableCell>
                        <TableCell>{formatDuration(call.duration)}</TableCell>
                        <TableCell className="text-sm text-slate-500">
                          {formatDate(call.created_at)}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              ) : (
                <p className="text-center text-sm text-slate-500 py-8">{t('dashboard.no_calls')}</p>
              )}
            </CardContent>
          </Card>
        </div>
      </div>
    </AppLayout>
  );
}
