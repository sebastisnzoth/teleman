import { useTranslation } from 'react-i18next';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Link, Head } from '@inertiajs/react';
import { ArrowLeft, Download, Phone, Clock, TrendingUp, Users } from 'lucide-react';
import {
  BarChart,
  Bar,
  LineChart,
  Line,
  PieChart,
  Pie,
  Cell,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  Legend,
  ResponsiveContainer,
} from 'recharts';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';

interface Campaign {
  id: number;
  name: string;
  type: string;
  status: string;
}

interface Analytics {
  overview: {
    total_contacts: number;
    total_calls: number;
    answered_calls: number;
    failed_calls: number;
    in_progress_calls: number;
    answer_rate: number;
    completion_rate: number;
    average_duration: number;
    total_duration: number;
    total_duration_formatted: string;
  };
  status_distribution: Array<{
    status: string;
    count: number;
    label: string;
  }>;
  hourly_stats: Array<{
    hour: number;
    label: string;
    total_calls: number;
    answered_calls: number;
    answer_rate: number;
    avg_duration: number;
  }>;
  daily_stats: Array<{
    date: string;
    total_calls: number;
    answered_calls: number;
    answer_rate: number;
    avg_duration: number;
  }>;
  performance_metrics: {
    peak_hour: string | null;
    peak_hour_calls: number;
    best_hour: string | null;
    best_hour_answer_rate: number;
    avg_retry_attempts: number;
    max_retry_attempts: number;
    contacts_with_retries: number;
    dtmf_responses: Record<string, number>;
  };
}

interface Props {
  campaign?: Campaign;
  analytics?: Analytics;
}

const COLORS = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'];

export default function CampaignAnalytics({
  campaign = {} as Campaign,
  analytics = {} as Analytics
}: Props) {
  const { t } = useTranslation();

  if (!campaign?.id || !analytics?.overview) {
    return (
      <AppLayout>
        <div className="container max-w-7xl py-8">
          <div className="text-center">
            <h3 className="text-lg font-medium">{t('analytics.loading_analytics')}</h3>
          </div>
        </div>
      </AppLayout>
    );
  }

  const handleExportCalls = () => {
    window.location.href = `/analytics/campaigns/${campaign.id}/export/calls`;
  };

  const handleExportContacts = () => {
    window.location.href = `/analytics/campaigns/${campaign.id}/export/contacts`;
  };

  const breadcrumbs: BreadcrumbItem[] = [
    {
      title: t('dashboard.title'),
      href: dashboard().url,
    },
    {
      title: t('nav.campaigns'),
      href: '/campaigns',
    },
    {
      title: campaign.name,
      href: `/campaigns/${campaign.id}`,
    },
    {
      title: t('nav.analytics'),
      href: `/analytics/campaigns/${campaign.id}`,
    },
  ];

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={`${t('nav.analytics')} - ${campaign.name}`} />
      <div className="mx-auto max-w-7xl">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-4">
            <Link href={`/campaigns/${campaign.id}`}>
              <Button variant="outline" size="icon">
                <ArrowLeft className="h-4 w-4" />
              </Button>
            </Link>
            <div>
              <h1 className="text-3xl font-bold tracking-tight">{t('analytics.campaign_analytics_title')}</h1>
              <p className="mt-1 text-sm text-slate-500">{campaign.name}</p>
            </div>
          </div>

          <div className="flex gap-2">
            <Button variant="outline" onClick={handleExportContacts}>
              <Download className="mr-2 h-4 w-4" />
              {t('analytics.export_contacts')}
            </Button>
            <Button onClick={handleExportCalls}>
              <Download className="mr-2 h-4 w-4" />
              {t('analytics.export_calls')}
            </Button>
          </div>
        </div>

        {/* Overview Stats */}
        <div className="mb-6 grid gap-4 md:grid-cols-4">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">{t('analytics.total_calls')}</CardTitle>
              <Phone className="h-4 w-4 text-slate-500" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{analytics.overview.total_calls}</div>
              <p className="text-xs text-slate-500">
                {t('analytics.completion_rate_pct', { rate: analytics.overview.completion_rate })}
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">{t('analytics.answer_rate')}</CardTitle>
              <TrendingUp className="h-4 w-4 text-slate-500" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{analytics.overview.answer_rate}%</div>
              <p className="text-xs text-slate-500">
                {t('analytics.calls_answered_count', { count: analytics.overview.answered_calls })}
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">{t('analytics.avg_duration')}</CardTitle>
              <Clock className="h-4 w-4 text-slate-500" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{analytics.overview.average_duration}s</div>
              <p className="text-xs text-slate-500">
                {t('analytics.total_label')}: {analytics.overview.total_duration_formatted}
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">{t('analytics.total_contacts')}</CardTitle>
              <Users className="h-4 w-4 text-slate-500" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{analytics.overview.total_contacts}</div>
              <p className="text-xs text-slate-500">
                {t('analytics.failed_calls_count', { count: analytics.overview.failed_calls })}
              </p>
            </CardContent>
          </Card>
        </div>

        <div className="grid gap-6 md:grid-cols-2">
          {/* Status Distribution */}
          <Card>
            <CardHeader>
              <CardTitle>{t('analytics.call_status_distribution')}</CardTitle>
              <CardDescription>{t('analytics.breakdown_of_call_outcomes')}</CardDescription>
            </CardHeader>
            <CardContent>
              <ResponsiveContainer width="100%" height={300}>
                <PieChart>
                  <Pie
                    data={analytics.status_distribution}
                    cx="50%"
                    cy="50%"
                    labelLine={false}
                    label
                    outerRadius={80}
                    fill="#8884d8"
                    dataKey="count"
                    nameKey="label"
                  >
                    {analytics.status_distribution.map((entry, index) => (
                      <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                    ))}
                  </Pie>
                  <Tooltip />
                </PieChart>
              </ResponsiveContainer>
            </CardContent>
          </Card>

          {/* Performance Metrics */}
          <Card>
            <CardHeader>
              <CardTitle>{t('analytics.performance_metrics')}</CardTitle>
              <CardDescription>{t('analytics.key_performance_indicators')}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="flex items-center justify-between border-b pb-2">
                <span className="text-sm font-medium">{t('analytics.peak_hour')}</span>
                <Badge variant="secondary">
                  {analytics.performance_metrics.peak_hour || t('common.unknown')} ({analytics.performance_metrics.peak_hour_calls} {t('analytics.calls_label')})
                </Badge>
              </div>
              <div className="flex items-center justify-between border-b pb-2">
                <span className="text-sm font-medium">{t('analytics.best_hour')}</span>
                <Badge variant="secondary">
                  {analytics.performance_metrics.best_hour || t('common.unknown')} ({analytics.performance_metrics.best_hour_answer_rate}% {t('analytics.answer_rate')})
                </Badge>
              </div>
              <div className="flex items-center justify-between border-b pb-2">
                <span className="text-sm font-medium">{t('analytics.avg_retry_attempts')}</span>
                <Badge variant="secondary">{analytics.performance_metrics.avg_retry_attempts}</Badge>
              </div>
              <div className="flex items-center justify-between border-b pb-2">
                <span className="text-sm font-medium">{t('analytics.contacts_with_retries')}</span>
                <Badge variant="secondary">{analytics.performance_metrics.contacts_with_retries}</Badge>
              </div>
              {Object.keys(analytics.performance_metrics.dtmf_responses).length > 0 && (
                <div className="pt-2">
                  <span className="text-sm font-medium">{t('analytics.dtmf_responses')}</span>
                  <div className="mt-2 space-y-1">
                    {Object.entries(analytics.performance_metrics.dtmf_responses).map(([key, value]) => (
                      <div key={key} className="flex items-center justify-between text-sm">
                        <span className="text-slate-600">{t('analytics.key_label')} {key}</span>
                        <span className="font-medium">{value}</span>
                      </div>
                    ))}
                  </div>
                </div>
              )}
            </CardContent>
          </Card>

          {/* Hourly Stats */}
          {analytics.hourly_stats.length > 0 && (
            <Card className="md:col-span-2">
              <CardHeader>
                <CardTitle>{t('analytics.hourly_call_statistics')}</CardTitle>
                <CardDescription>{t('analytics.calls_and_answer_rates_by_hour')}</CardDescription>
              </CardHeader>
              <CardContent>
                <ResponsiveContainer width="100%" height={300}>
                  <BarChart data={analytics.hourly_stats}>
                    <CartesianGrid strokeDasharray="3 3" />
                    <XAxis dataKey="label" />
                    <YAxis yAxisId="left" />
                    <YAxis yAxisId="right" orientation="right" />
                    <Tooltip />
                    <Legend />
                    <Bar yAxisId="left" dataKey="total_calls" fill="#3b82f6" name={t('analytics.total_calls')} />
                    <Bar yAxisId="left" dataKey="answered_calls" fill="#10b981" name={t('analytics.answered_label')} />
                    <Line yAxisId="right" type="monotone" dataKey="answer_rate" stroke="#f59e0b" name={t('analytics.answer_rate_pct_label')} />
                  </BarChart>
                </ResponsiveContainer>
              </CardContent>
            </Card>
          )}

          {/* Daily Stats */}
          {analytics.daily_stats.length > 1 && (
            <Card className="md:col-span-2">
              <CardHeader>
                <CardTitle>{t('analytics.daily_call_trends')}</CardTitle>
                <CardDescription>{t('analytics.performance_over_time')}</CardDescription>
              </CardHeader>
              <CardContent>
                <ResponsiveContainer width="100%" height={300}>
                  <LineChart data={analytics.daily_stats}>
                    <CartesianGrid strokeDasharray="3 3" />
                    <XAxis dataKey="date" />
                    <YAxis yAxisId="left" />
                    <YAxis yAxisId="right" orientation="right" />
                    <Tooltip />
                    <Legend />
                    <Line yAxisId="left" type="monotone" dataKey="total_calls" stroke="#3b82f6" name={t('analytics.total_calls')} />
                    <Line yAxisId="left" type="monotone" dataKey="answered_calls" stroke="#10b981" name={t('analytics.answered_label')} />
                    <Line yAxisId="right" type="monotone" dataKey="answer_rate" stroke="#f59e0b" name={t('analytics.answer_rate_pct_label')} />
                  </LineChart>
                </ResponsiveContainer>
              </CardContent>
            </Card>
          )}
        </div>
      </div>
    </AppLayout>
  );
}
