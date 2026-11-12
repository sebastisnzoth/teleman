import AppLayout from '@/layouts/app-layout';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { Link, router, Head } from '@inertiajs/react';
import { Plus, Play, Pause, Edit, Trash2, Eye } from 'lucide-react';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { PageHelp } from '@/components/page-help';
import { ConfirmationModal } from '@/components/confirmation-modal';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { isHidden, useTelephony } from '@/lib/telephony';

interface Campaign {
  id: number;
  name: string;
  type: 'text_to_speech' | 'voice_to_voice';
  status: 'draft' | 'running' | 'paused' | 'completed' | 'failed';
  total_contacts: number;
  total_called: number;
  total_answered: number;
  total_failed: number;
  created_at: string;
  campaign_contacts_count?: number;
}

interface PaginationLink {
  url: string | null;
  label: string;
  active: boolean;
}

interface PaginationMeta {
  current_page: number;
  from: number | null;
  last_page: number;
  path: string;
  per_page: number;
  to: number | null;
  total: number;
}

interface Props {
  campaigns?: {
    data: Campaign[];
    links: PaginationLink[];
    meta: PaginationMeta;
  };
  filters?: {
    status?: string;
  };
}

const statusConfig: Record<Campaign['status'], { labelKey: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' }> = {
  draft: { labelKey: 'campaigns.draft_label', variant: 'outline' },
  running: { labelKey: 'campaigns.active_label', variant: 'default' },
  paused: { labelKey: 'campaigns.paused_label', variant: 'secondary' },
  completed: { labelKey: 'campaigns.completed_label', variant: 'secondary' },
  failed: { labelKey: 'campaigns.failed_label', variant: 'destructive' },
};

export default function CampaignsIndex({
  campaigns = {
    data: [],
    links: [],
    meta: { current_page: 1, from: null, last_page: 1, path: '', per_page: 15, to: null, total: 0 }
  },
  filters = {}
}: Props) {
  const { t } = useTranslation();
  const { hidden } = useTelephony();
  const [confirmAction, setConfirmAction] = useState<{
    open: boolean;
    title: string;
    description: string;
    onConfirm: () => void;
    variant?: 'default' | 'destructive';
  }>({
    open: false,
    title: '',
    description: '',
    onConfirm: () => {},
  });

  const handleLaunch = (id: number) => {
    setConfirmAction({
      open: true,
      title: 'Launch Campaign',
      description: 'Are you sure you want to launch this campaign? Calls will start being made to contacts.',
      onConfirm: () => {
        router.post(`/campaigns/${id}/launch`);
        setConfirmAction(prev => ({ ...prev, open: false }));
      },
    });
  };

  const handlePause = (id: number) => {
    setConfirmAction({
      open: true,
      title: 'Pause Campaign',
      description: 'Are you sure you want to pause this campaign? You can resume it later.',
      onConfirm: () => {
        router.post(`/campaigns/${id}/pause`);
        setConfirmAction(prev => ({ ...prev, open: false }));
      },
    });
  };

  const handleResume = (id: number) => {
    setConfirmAction({
      open: true,
      title: 'Resume Campaign',
      description: 'Are you sure you want to resume this campaign? Calls will continue.',
      onConfirm: () => {
        router.post(`/campaigns/${id}/resume`);
        setConfirmAction(prev => ({ ...prev, open: false }));
      },
    });
  };

  const handleDelete = (id: number) => {
    setConfirmAction({
      open: true,
      title: 'Delete Campaign',
      description: 'Are you sure you want to delete this campaign? This action cannot be undone.',
      variant: 'destructive',
      onConfirm: () => {
        router.delete(`/campaigns/${id}`);
        setConfirmAction(prev => ({ ...prev, open: false }));
      },
    });
  };

  const getProgress = (campaign: Campaign) => {
    const total = campaign.total_contacts || campaign.campaign_contacts_count || 0;
    if (total === 0) return 0;
    return Math.round((campaign.total_called / total) * 100);
  };

  const getAnswerRate = (campaign: Campaign) => {
    if (campaign.total_called === 0) return 0;
    return Math.round((campaign.total_answered / campaign.total_called) * 100);
  };

  // total_answered counts only the calls answering-machine detection
  // confirmed were picked up, so on an installation whose provider never
  // reports that, this rate stays 0% for ever beside a Called count that
  // works. See config('telephony.hidden_features').
  const showAnswerRate = !isHidden('answered_stats', hidden);

  const breadcrumbs: BreadcrumbItem[] = [
    {
      title: t('common.dashboard'),
      href: dashboard().url,
    },
    {
      title: t('campaigns.title'),
      href: '/campaigns',
    },
  ];

  const helpSections = [
    {
      title: 'Campaign Overview',
      content: 'This page shows all your voice calling campaigns. Each campaign represents an automated calling session to a list of contacts using either Text-to-Speech or Voice-to-Voice technology.',
    },
    {
      title: 'Campaign Types',
      content: 'Text-to-Speech: Converts written script to voice. Voice-to-Voice: Uses pre-recorded audio files for the call.',
    },
    {
      title: 'Campaign Status',
      content: 'Draft: Not yet launched. Running: Currently making calls. Paused: Temporarily stopped. Completed: All calls finished. Failed: Campaign encountered errors.',
    },
    {
      title: 'Managing Campaigns',
      content: 'Use the Launch button to start a draft campaign. Use Pause to temporarily stop a running campaign and Resume to continue. Edit campaigns before launching, or view details after completion.',
    },
    {
      title: 'Progress Tracking',
      content: 'The progress bar shows how many contacts have been called out of the total. Answer rate displays the percentage of answered calls. View detailed stats by clicking on a campaign card.',
    },
    {
      title: 'Filters',
      content: 'Use the filter buttons above the campaign grid to view campaigns by status (All, Draft, Running, Paused, Completed, Failed).',
    },
  ];

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={t('campaigns.title')} />
      <div className="mx-auto max-w-7xl space-y-6">
        <div className="flex items-center justify-between">
          <Heading title={t('campaigns.title')} description={t('campaigns.description')} />
          <div className="flex items-center gap-2">
            <PageHelp title="Campaigns Help" sections={helpSections} />
            <Link href="/campaigns/create">
              <Button>
                <Plus className="mr-2 h-4 w-4" />
                {t('campaigns.add')}
              </Button>
            </Link>
          </div>
        </div>

        {/* Filters */}
        <div className="flex gap-2">
          <Button
            variant={!filters.status ? 'default' : 'outline'}
            size="sm"
            onClick={() => router.get('/campaigns')}
          >
            {t('common.all')}
          </Button>
          {Object.entries(statusConfig).map(([status, config]) => (
            <Button
              key={status}
              variant={filters.status === status ? 'default' : 'outline'}
              size="sm"
              onClick={() => router.get('/campaigns', { status })}
            >
              {t(config.labelKey)}
            </Button>
          ))}
        </div>

        {/* Campaigns Grid */}
        <div className="mt-6 grid gap-6 md:grid-cols-2 lg:grid-cols-3">
          {campaigns.data.map((campaign) => (
            <Card key={campaign.id}>
              <CardHeader>
                <div className="flex items-start justify-between">
                  <div className="flex-1">
                    <CardTitle className="text-lg">{campaign.name}</CardTitle>
                    <CardDescription className="mt-1 capitalize">
                      {campaign.type.replace('_', ' ')}
                    </CardDescription>
                  </div>
                  <Badge variant={statusConfig[campaign.status].variant}>
                    {t(statusConfig[campaign.status].labelKey)}
                  </Badge>
                </div>
              </CardHeader>
              <CardContent className="space-y-4">
                {/* Progress */}
                <div>
                  <div className="mb-2 flex justify-between text-sm">
                    <span className="text-slate-600">{t('campaigns.progress')}</span>
                    <span className="font-medium">{getProgress(campaign)}%</span>
                  </div>
                  <Progress value={getProgress(campaign)} />
                </div>

                {/* Stats */}
                <div className={`grid ${showAnswerRate ? 'grid-cols-3' : 'grid-cols-2'} gap-2 text-center text-sm`}>
                  <div>
                    <div className="font-semibold">{campaign.total_contacts || campaign.campaign_contacts_count || 0}</div>
                    <div className="text-xs text-slate-500">Total</div>
                  </div>
                  <div>
                    <div className="font-semibold">{campaign.total_called}</div>
                    <div className="text-xs text-slate-500">Called</div>
                  </div>
                  {showAnswerRate && (
                    <div>
                      <div className="font-semibold">{getAnswerRate(campaign)}%</div>
                      <div className="text-xs text-slate-500">Answer</div>
                    </div>
                  )}
                </div>

                {/* Actions */}
                <div className="flex gap-2">
                  <Link href={`/campaigns/${campaign.id}`} className="flex-1">
                    <Button variant="outline" size="sm" className="w-full">
                      <Eye className="mr-1 h-3.5 w-3.5" />
                      {t('common.view')}
                    </Button>
                  </Link>

                  {campaign.status === 'draft' && (
                    <>
                      <Link href={`/campaigns/${campaign.id}/edit`}>
                        <Button variant="outline" size="sm">
                          <Edit className="h-3.5 w-3.5" />
                        </Button>
                      </Link>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => handleLaunch(campaign.id)}
                      >
                        <Play className="h-3.5 w-3.5" />
                      </Button>
                    </>
                  )}

                  {campaign.status === 'running' && (
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => handlePause(campaign.id)}
                    >
                      <Pause className="h-3.5 w-3.5" />
                    </Button>
                  )}

                  {campaign.status === 'paused' && (
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => handleResume(campaign.id)}
                    >
                      <Play className="h-3.5 w-3.5" />
                    </Button>
                  )}

                  {['draft', 'completed', 'failed'].includes(campaign.status) && (
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => handleDelete(campaign.id)}
                    >
                      <Trash2 className="h-3.5 w-3.5" />
                    </Button>
                  )}
                </div>
              </CardContent>
            </Card>
          ))}
        </div>

        {/* Empty State */}
        {campaigns.data.length === 0 && (
          <Card className="mt-6">
            <CardContent className="flex flex-col items-center justify-center py-12">
              <div className="text-center">
                <h3 className="text-lg font-semibold">{t('campaigns.no_campaigns')}</h3>
                <p className="mt-2 text-sm text-slate-500">
                  Get started by creating your first voice calling campaign
                </p>
                <Link href="/campaigns/create" className="mt-4 inline-block">
                  <Button>
                    <Plus className="mr-2 h-4 w-4" />
                    {t('campaigns.add')}
                  </Button>
                </Link>
              </div>
            </CardContent>
          </Card>
        )}
      </div>

      <ConfirmationModal
        open={confirmAction.open}
        onOpenChange={(open) => setConfirmAction(prev => ({ ...prev, open }))}
        title={confirmAction.title}
        description={confirmAction.description}
        onConfirm={confirmAction.onConfirm}
        variant={confirmAction.variant}
      />
    </AppLayout>
  );
}
