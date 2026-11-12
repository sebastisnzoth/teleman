import { useTranslation } from 'react-i18next';
import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { MessageSquare, Bot, TrendingUp, Clock } from 'lucide-react';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';

interface Props {
  stats: {
    total_conversations: number;
    total_messages: number;
    active_conversations: number;
    ai_response_rate: number;
  };
}

export default function SmsAnalytics({ stats }: Props) {
  const { t } = useTranslation();
  const breadcrumbs: BreadcrumbItem[] = [
  {
    title: t('nav.home'),
    href: dashboard().url,
  },
  {
    title: t('sms.title'),
    href: '/sms',
  },
  {
    title: t('analytics.title'),
  },
];

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={t('sms.analytics_title')} />

      <div className="container mx-auto p-6 space-y-6">
        <div>
          <h1 className="text-3xl font-bold tracking-tight text-foreground">{t('sms.analytics_title')}</h1>
          <p className="text-muted-foreground mt-1">
            {t('sms.analytics_description')}
          </p>
        </div>

        <div className="grid gap-4 md:grid-cols-4">
          <Card className="border-border bg-card">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium text-foreground">{t('sms.total_conversations')}</CardTitle>
              <MessageSquare className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-foreground">{stats.total_conversations}</div>
              <p className="text-xs text-muted-foreground">{t('sms.all_time')}</p>
            </CardContent>
          </Card>

          <Card className="border-border bg-card">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium text-foreground">{t('sms.total_messages')}</CardTitle>
              <MessageSquare className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-foreground">{stats.total_messages}</div>
              <p className="text-xs text-muted-foreground">{t('sms.sent_and_received')}</p>
            </CardContent>
          </Card>

          <Card className="border-border bg-card">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium text-foreground">{t('sms.active_conversations')}</CardTitle>
              <TrendingUp className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-foreground">{stats.active_conversations}</div>
              <p className="text-xs text-muted-foreground">{t('sms.currently_active')}</p>
            </CardContent>
          </Card>

          <Card className="border-border bg-card">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium text-foreground">{t('sms.ai_responses')}</CardTitle>
              <Bot className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-foreground">{stats.ai_response_rate}</div>
              <p className="text-xs text-muted-foreground">{t('sms.generated_by_ai')}</p>
            </CardContent>
          </Card>
        </div>

        <Card className="border-border bg-card">
          <CardHeader>
            <CardTitle className="text-foreground">{t('sms.performance_metrics')}</CardTitle>
            <CardDescription>
              {t('sms.detailed_analytics_coming_soon')}
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="text-center py-12 text-muted-foreground">
              <Clock className="h-12 w-12 mx-auto mb-4 opacity-50" />
              <p>{t('sms.more_analytics_available')}</p>
            </div>
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  );
}
