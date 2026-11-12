import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Link, router, Head, usePage } from '@inertiajs/react';
import { ArrowLeft, Play, Pause, Edit, Trash2, Phone, Clock, CheckCircle2, XCircle, RefreshCw, BarChart3, Save, Bot, Sparkles, PhoneCall } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import CSVUploadZone from '@/components/campaigns/csv-upload-zone';
import { DtmfAnalytics } from '@/components/campaigns/dtmf-analytics';
import { useEffect, useState } from 'react';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import axios from 'axios';
import { ConfirmationModal } from '@/components/confirmation-modal';
import { isHidden, useTelephony } from '@/lib/telephony';
import { toast } from 'sonner';

interface Call {
  id: number;
  to_number: string;
  status: string;
  duration: number | null;
  created_at: string;
}

interface Contact {
  id: number;
  phone_number: string;
  first_name: string | null;
  last_name: string | null;
  status: string;
}

interface Campaign {
  id: number;
  name: string;
  type: 'text_to_speech' | 'voice_to_voice';
  status: 'draft' | 'scheduled' | 'running' | 'paused' | 'completed' | 'failed';
  message: string | null;
  language: string | null;
  voice: string | null;
  from_number: string;
  assigned_agent_id: number | null;
  assigned_agent?: {
    id: number;
    name: string;
    email: string;
  } | null;
  ai_agent?: {
    id: number;
    name: string;
    model: string;
    voice: string;
    active: boolean;
  } | null;
  enable_recording: boolean;
  enable_dtmf: boolean;
  max_concurrent_calls: number;
  retry_attempts: number;
  retry_delay_minutes: number;
  scheduled_at: string | null;
  started_at: string | null;
  completed_at: string | null;
  total_contacts: number;
  total_called: number;
  total_answered: number;
  total_failed: number;
  campaign_contacts_count: number;
  calls_count: number;
  created_at: string;
  expected_variables: string[] | null;
  campaign_variables: Record<string, string> | null;
}

interface Props {
  campaign: Campaign & {
    calls: Call[];
    campaign_contacts: Contact[];
    audio_file?: {
      id: number;
      filename: string;
    } | null;
  };
  dtmfStats?: {
    total: number;
    by_digit: Array<{ digit: string; label: string; count: number; percentage: number }>;
    by_action: Array<{ action: string; count: number; percentage: number }>;
  } | null;
}

const statusConfig: Record<Campaign['status'], { labelKey: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' }> = {
  draft: { labelKey: 'campaigns.draft_label', variant: 'outline' },
  scheduled: { labelKey: 'campaigns.scheduled_label', variant: 'secondary' },
  running: { labelKey: 'campaigns.active_label', variant: 'default' },
  paused: { labelKey: 'campaigns.paused_label', variant: 'secondary' },
  completed: { labelKey: 'campaigns.completed_label', variant: 'secondary' },
  failed: { labelKey: 'campaigns.failed_label', variant: 'destructive' },
};

export default function CampaignShow({ campaign, dtmfStats }: Props) {
  const { t } = useTranslation();
  const { hidden } = useTelephony();
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [showTemplateDialog, setShowTemplateDialog] = useState(false);
  const [templateData, setTemplateData] = useState({
    name: '',
    description: '',
    category: 'general' as 'promotional' | 'notification' | 'survey' | 'lead_qualification' | 'personalized' | 'general',
  });
  const [isSavingTemplate, setIsSavingTemplate] = useState(false);
  const [templateError, setTemplateError] = useState('');
  
  // Confirmation modal states
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

  // Auto-refresh when campaign is running
  useEffect(() => {
    if (campaign.status !== 'running') return;

    const interval = setInterval(() => {
      router.reload({ only: ['campaign'] });
    }, 5000); // Refresh every 5 seconds

    return () => clearInterval(interval);
  }, [campaign.status]);

  const handleRefresh = () => {
    setIsRefreshing(true);
    router.reload({
      only: ['campaign'],
      onFinish: () => setIsRefreshing(false),
    });
  };

  const handleLaunch = () => {
    setConfirmAction({
      open: true,
      title: 'Launch Campaign',
      description: 'Are you sure you want to launch this campaign? Calls will start being made to contacts.',
      onConfirm: () => {
        router.post(`/campaigns/${campaign.id}/launch`);
        setConfirmAction(prev => ({ ...prev, open: false }));
      },
    });
  };

  const handlePause = () => {
    setConfirmAction({
      open: true,
      title: 'Pause Campaign',
      description: 'Are you sure you want to pause this campaign? You can resume it later.',
      onConfirm: () => {
        router.post(`/campaigns/${campaign.id}/pause`);
        setConfirmAction(prev => ({ ...prev, open: false }));
      },
    });
  };

  const handleResume = () => {
    setConfirmAction({
      open: true,
      title: 'Resume Campaign',
      description: 'Are you sure you want to resume this campaign? Calls will continue.',
      onConfirm: () => {
        router.post(`/campaigns/${campaign.id}/resume`);
        setConfirmAction(prev => ({ ...prev, open: false }));
      },
    });
  };

  const handleDelete = () => {
    setConfirmAction({
      open: true,
      title: 'Delete Campaign',
      description: 'Are you sure you want to delete this campaign? This action cannot be undone.',
      variant: 'destructive',
      onConfirm: () => {
        router.delete(`/campaigns/${campaign.id}`);
        setConfirmAction(prev => ({ ...prev, open: false }));
      },
    });
  };

  const handleOnDemandCall = () => {
    setConfirmAction({
      open: true,
      title: 'Make On-Demand Call',
      description: 'This will immediately call the next pending contact WITHOUT using the queue. Use this to test if the calling system works.',
      onConfirm: () => {
        router.post(`/campaigns/${campaign.id}/on-demand-call`, {}, {
          preserveScroll: true,
          onSuccess: () => {
            // Refresh campaign data
            router.reload({ only: ['campaign'] });
          },
        });
        setConfirmAction(prev => ({ ...prev, open: false }));
      },
    });
  };

  const handleSaveAsTemplate = async () => {
    if (!templateData.name.trim()) {
      setTemplateError('Template name is required');
      return;
    }

    setIsSavingTemplate(true);
    setTemplateError('');

    try {
      await axios.post('/campaign-templates/from-campaign', {
        campaign_id: campaign.id,
        name: templateData.name,
        description: templateData.description,
        category: templateData.category,
      });

      setShowTemplateDialog(false);
      setTemplateData({ name: '', description: '', category: 'general' });
      
      toast.success(t('campaigns.template_created_successfully'));
    } catch {
      setTemplateError('Failed to create template');
    } finally {
      setIsSavingTemplate(false);
    }
  };

  const getProgress = () => {
    const total = campaign.total_contacts || campaign.campaign_contacts_count || 0;
    if (total === 0) return 0;
    return Math.round((campaign.total_called / total) * 100);
  };

  const getAnswerRate = () => {
    if (campaign.total_called === 0) return 0;
    return Math.round((campaign.total_answered / campaign.total_called) * 100);
  };

  // total_answered counts only the calls answering-machine detection
  // confirmed were picked up, so on an installation whose provider never
  // reports that, it stays 0 for ever — beside a Called count that does
  // work, a permanent zero reads as a broken page rather than as a
  // measurement nobody switched on. See config('telephony.hidden_features').
  const showAnswered = !isHidden('answered_stats', hidden);

  const formatDuration = (seconds: number | null) => {
    if (!seconds) return '0s';
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return mins > 0 ? `${mins}m ${secs}s` : `${secs}s`;
  };

  const breadcrumbs: BreadcrumbItem[] = [
    {
      title: t('common.dashboard'),
      href: dashboard().url,
    },
    {
      title: t('campaigns.title'),
      href: '/campaigns',
    },
    {
      title: campaign.name,
      href: `/campaigns/${campaign.id}`,
    },
  ];

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={campaign.name} />
      <div className="mx-auto max-w-7xl space-y-6">
        {/* Header */}
        <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
          <div className="flex items-start gap-4">
            <Link href="/campaigns">
              <Button variant="outline" size="icon" className="shrink-0">
                <ArrowLeft className="h-4 w-4" />
              </Button>
            </Link>
            <div className="min-w-0 flex-1">
              <div className="flex flex-wrap items-center gap-3">
                <h1 className="text-2xl font-bold tracking-tight break-words lg:text-3xl">{campaign.name}</h1>
                <Badge variant={statusConfig[campaign.status].variant} className="shrink-0">
                  {t(statusConfig[campaign.status].labelKey)}
                </Badge>
              </div>
              <p className="mt-1 text-sm text-muted-foreground capitalize">
                {campaign.type.replace('_', ' ')} Campaign
              </p>
            </div>
          </div>

          <div className="flex flex-wrap gap-2 lg:shrink-0">
            {campaign.status === 'running' && (
              <Button variant="outline" size="icon" onClick={handleRefresh} disabled={isRefreshing}>
                <RefreshCw className={`h-4 w-4 ${isRefreshing ? 'animate-spin' : ''}`} />
              </Button>
            )}

            {/* Save as Template: Available for all campaigns */}
            <Dialog open={showTemplateDialog} onOpenChange={setShowTemplateDialog}>
              <DialogTrigger asChild>
                <Button variant="outline">
                  <Save className="mr-2 h-4 w-4" />
                  Save as Template
                </Button>
              </DialogTrigger>
              <DialogContent>
                <DialogHeader>
                  <DialogTitle>Save as Template</DialogTitle>
                  <DialogDescription>
                    Create a reusable template from this campaign configuration.
                  </DialogDescription>
                </DialogHeader>
                <div className="space-y-4 py-4">
                  <div className="space-y-2">
                    <Label htmlFor="template-name">Template Name *</Label>
                    <Input
                      id="template-name"
                      value={templateData.name}
                      onChange={(e) => setTemplateData({ ...templateData, name: e.target.value })}
                      placeholder="e.g., Customer Follow-up Template"
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="template-description">Description</Label>
                    <Textarea
                      id="template-description"
                      value={templateData.description}
                      onChange={(e) => setTemplateData({ ...templateData, description: e.target.value })}
                      placeholder="Describe when to use this template..."
                      rows={3}
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="template-category">Category</Label>
                    <Select
                      value={templateData.category}
                      onValueChange={(value) => setTemplateData({ ...templateData, category: value as typeof templateData.category })}
                    >
                      <SelectTrigger id="template-category">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="promotional">Promotional</SelectItem>
                        <SelectItem value="notification">Notification</SelectItem>
                        <SelectItem value="survey">Survey</SelectItem>
                        <SelectItem value="lead_qualification">Lead Qualification</SelectItem>
                        <SelectItem value="personalized">Personalized</SelectItem>
                        <SelectItem value="general">General</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  {templateError && (
                    <p className="text-sm text-red-500">{templateError}</p>
                  )}
                </div>
                <DialogFooter>
                  <Button variant="outline" onClick={() => setShowTemplateDialog(false)} disabled={isSavingTemplate}>
                    Cancel
                  </Button>
                  <Button onClick={handleSaveAsTemplate} disabled={isSavingTemplate}>
                    {isSavingTemplate ? 'Saving...' : 'Save Template'}
                  </Button>
                </DialogFooter>
              </DialogContent>
            </Dialog>

            {(campaign.total_called > 0 || ['running', 'completed'].includes(campaign.status)) && (
              <Link href={`/analytics/campaigns/${campaign.id}`}>
                <Button variant="outline">
                  <BarChart3 className="mr-2 h-4 w-4" />
                  Analytics
                </Button>
              </Link>
            )}

            {/* Call Queue: Available when campaign has contacts and is assigned to an agent */}
            {campaign.assigned_agent_id && campaign.campaign_contacts_count > 0 && (
              <Link href={`/campaigns/${campaign.id}/queue`}>
                <Button variant="outline">
                  <PhoneCall className="mr-2 h-4 w-4" />
                  Call Queue
                </Button>
              </Link>
            )}

            {/* Edit: Only for draft or scheduled (not started yet) */}
            {(campaign.status === 'draft' || (campaign.status === 'scheduled' && !campaign.started_at)) && (
              <Link href={`/campaigns/${campaign.id}/edit`}>
                <Button variant="outline">
                  <Edit className="mr-2 h-4 w-4" />
                  {t('common.edit')}
                </Button>
              </Link>
            )}

            {/* Launch: Only for draft campaigns */}
            {campaign.status === 'draft' && (
              <Button onClick={handleLaunch}>
                <Play className="mr-2 h-4 w-4" />
                {t('campaigns.launch')}
              </Button>
            )}

            {/* Pause/Stop: Only for running campaigns */}
            {campaign.status === 'running' && (
              <Button variant="outline" onClick={handlePause}>
                <Pause className="mr-2 h-4 w-4" />
                {t('campaigns.pause')}
              </Button>
            )}

            {/* Resume: Only for paused campaigns */}
            {campaign.status === 'paused' && (
              <Button onClick={handleResume}>
                <Play className="mr-2 h-4 w-4" />
                {t('campaigns.resume')}
              </Button>
            )}

            {/* On-Demand Call: For testing (not for completed/failed campaigns) */}
            {!['completed', 'failed'].includes(campaign.status) && (
              <Button variant="secondary" onClick={handleOnDemandCall}>
                <Phone className="mr-2 h-4 w-4" />
                On Demand Start
              </Button>
            )}

            {/* Delete: Only allowed for draft, completed, or failed campaigns */}
            {['draft', 'completed', 'failed'].includes(campaign.status) && (
              <Button variant="destructive" onClick={handleDelete}>
                <Trash2 className="mr-2 h-4 w-4" />
                Delete
              </Button>
            )}
          </div>
        </div>

        {/* Stats Grid */}
        <div className={`grid gap-4 ${showAnswered ? 'md:grid-cols-4' : 'md:grid-cols-3'}`}>
          <Card>
            <CardHeader className="pb-3">
              <CardDescription>Total Contacts</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{campaign.total_contacts || campaign.campaign_contacts_count}</div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="pb-3">
              <CardDescription>Called</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{campaign.total_called}</div>
              <Progress value={getProgress()} className="mt-2" />
            </CardContent>
          </Card>

          {showAnswered && (
            <Card>
              <CardHeader className="pb-3">
                <CardDescription>Answered</CardDescription>
              </CardHeader>
              <CardContent>
                <div className="flex items-baseline gap-2">
                  <div className="text-2xl font-bold">{campaign.total_answered}</div>
                  <div className="text-sm text-slate-500">({getAnswerRate()}%)</div>
                </div>
              </CardContent>
            </Card>
          )}

          <Card>
            <CardHeader className="pb-3">
              <CardDescription>Failed</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{campaign.total_failed}</div>
            </CardContent>
          </Card>
        </div>

        {/* DTMF Analytics - Show if DTMF is enabled and has responses */}
        {campaign.enable_dtmf && dtmfStats && dtmfStats.total > 0 && (
          <DtmfAnalytics 
            dtmfResponses={dtmfStats}
            totalCalls={campaign.total_called}
          />
        )}

        {/* Campaign Details */}
        <Card>
          <CardHeader>
            <CardTitle>{t('campaigns.details')}</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid gap-4 md:grid-cols-2">
              <div>
                <label className="text-sm font-medium text-muted-foreground">Type</label>
                <p className="mt-1 capitalize">{campaign.type.replace('_', ' ')}</p>
              </div>

              <div>
                <label className="text-sm font-medium text-muted-foreground">From Number</label>
                <p className="mt-1">{campaign.from_number}</p>
              </div>

              {campaign.ai_agent && (
                <div className="md:col-span-2">
                  <label className="text-sm font-medium text-muted-foreground">AI Agent</label>
                  <div className="mt-2 p-3 rounded-lg border border-blue-200 bg-blue-50">
                    <div className="flex items-start gap-3">
                      <div className="rounded-full bg-blue-600 p-2">
                        <Bot className="h-4 w-4 text-white" />
                      </div>
                      <div className="flex-1">
                        <div className="flex items-center gap-2">
                          <p className="font-medium text-blue-900">{campaign.ai_agent.name}</p>
                          <Badge variant={campaign.ai_agent.active ? "default" : "secondary"} className="text-xs">
                            {campaign.ai_agent.active ? 'Active' : 'Inactive'}
                          </Badge>
                        </div>
                        <div className="mt-1 flex items-center gap-4 text-sm text-blue-700">
                          <span className="flex items-center gap-1">
                            <Sparkles className="h-3 w-3" />
                            {campaign.ai_agent.model}
                          </span>
                          <span>Voice: {campaign.ai_agent.voice}</span>
                        </div>
                        <p className="mt-2 text-xs text-blue-600">
                          ℹ️ This campaign uses AI conversation instead of static messages. The AI agent will handle calls dynamically based on customer responses.
                        </p>
                      </div>
                    </div>
                  </div>
                </div>
              )}

              {campaign.type === 'text_to_speech' && (
                <>
                  <div>
                    <label className="text-sm font-medium text-muted-foreground">Language</label>
                    <p className="mt-1">{campaign.language}</p>
                  </div>

                  <div>
                    <label className="text-sm font-medium text-muted-foreground">Voice</label>
                    <p className="mt-1">{campaign.voice}</p>
                  </div>
                </>
              )}

              {campaign.type === 'voice_to_voice' && campaign.audio_file && (
                <div>
                  <label className="text-sm font-medium text-muted-foreground">Audio File</label>
                  <p className="mt-1">{campaign.audio_file.filename}</p>
                </div>
              )}

              <div>
                <label className="text-sm font-medium text-muted-foreground">Recording</label>
                <p className="mt-1">{campaign.enable_recording ? 'Enabled' : 'Disabled'}</p>
              </div>

              <div>
                <label className="text-sm font-medium text-muted-foreground">DTMF</label>
                <p className="mt-1">{campaign.enable_dtmf ? 'Enabled' : 'Disabled'}</p>
              </div>

              <div>
                <label className="text-sm font-medium text-muted-foreground">Max Concurrent Calls</label>
                <p className="mt-1">{campaign.max_concurrent_calls}</p>
              </div>

              <div>
                <label className="text-sm font-medium text-muted-foreground">Retry Attempts</label>
                <p className="mt-1">{campaign.retry_attempts}</p>
              </div>
            </div>

            {campaign.message && (
              <div>
                <label className="text-sm font-medium text-muted-foreground">Message</label>
                <p className="mt-1 rounded-md bg-muted/50 p-3 text-sm">{campaign.message}</p>
              </div>
            )}
          </CardContent>
        </Card>

        {/* Variable Dashboard - Beautiful UI with detailed information */}
        {(campaign.expected_variables?.length > 0 || Object.keys(campaign.campaign_variables || {}).length > 0) && (
          <Card className="border-2 border-blue-200 dark:border-blue-800">
            <CardHeader className="border-b border-border">
              <div className="flex items-center justify-between">
                <div>
                  <CardTitle className="flex items-center gap-2 text-xl">
                    <span className="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 text-blue-600">
                      {'{'}'{'}'}
                    </span>
                    Dynamic Variables Dashboard
                  </CardTitle>
                  <CardDescription className="mt-2">
                    Personalization variables used in this campaign
                  </CardDescription>
                </div>
                <Badge variant="outline" className="h-8 px-4 text-sm font-semibold">
                  {(campaign.expected_variables?.length || 0) + Object.keys(campaign.campaign_variables || {}).length} Variables
                </Badge>
              </div>
            </CardHeader>
            <CardContent className="space-y-6 pt-6">
              {/* Campaign-Level Variables */}
              {Object.keys(campaign.campaign_variables || {}).length > 0 && (
                <div className="space-y-3">
                  <div className="flex items-center gap-2">
                    <Badge className="bg-purple-100 text-purple-700 hover:bg-purple-100">
                      Campaign Variables
                    </Badge>
                    <span className="text-sm text-muted-foreground">
                      Same value for all contacts in this campaign
                    </span>
                  </div>
                  <div className="grid gap-3 md:grid-cols-2">
                    {Object.entries(campaign.campaign_variables || {}).map(([key, value]) => (
                      <div
                        key={key}
                        className="group relative overflow-hidden rounded-lg border border-purple-200 dark:border-purple-800 bg-purple-50/50 dark:bg-purple-950/20 p-4 transition-all hover:border-purple-300 dark:hover:border-purple-700 hover:shadow-md"
                      >
                        <div className="absolute right-2 top-2 opacity-0 transition-opacity group-hover:opacity-100">
                          <Badge variant="outline" className="text-xs">
                            Campaign
                          </Badge>
                        </div>
                        <div className="space-y-2">
                          <div className="flex items-center gap-2">
                            <code className="rounded bg-purple-100 px-2 py-0.5 text-sm font-mono text-purple-900">
                              {'{{' + key + '}}'}
                            </code>
                          </div>
                          <div className="flex items-center gap-2">
                            <span className="text-xs font-medium text-slate-500">Value:</span>
                            <span className="flex-1 truncate rounded-md bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 shadow-sm">
                              {value}
                            </span>
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {/* Contact-Level Variables */}
              {campaign.expected_variables?.length > 0 && (
                <div className="space-y-3">
                  <div className="flex items-center gap-2">
                    <Badge className="bg-emerald-100 text-emerald-700 hover:bg-emerald-100">
                      Contact Variables
                    </Badge>
                    <span className="text-sm text-muted-foreground">
                      Unique value for each contact (from CSV or contact data)
                    </span>
                  </div>
                  <div className="grid gap-3 md:grid-cols-3 lg:grid-cols-4">
                    {campaign.expected_variables.map((variable) => (
                      <div
                        key={variable}
                        className="group relative overflow-hidden rounded-lg border border-emerald-200 dark:border-emerald-800 bg-emerald-50/50 dark:bg-emerald-950/20 p-4 transition-all hover:border-emerald-300 dark:hover:border-emerald-700 hover:shadow-md"
                      >
                        <div className="absolute right-2 top-2 opacity-0 transition-opacity group-hover:opacity-100">
                          <Badge variant="outline" className="text-xs">
                            Contact
                          </Badge>
                        </div>
                        <div className="flex items-center justify-center">
                          <code className="text-center font-mono text-sm font-semibold text-emerald-900">
                            {'{{' + variable + '}}'}
                          </code>
                        </div>
                        <div className="mt-2 text-center">
                          <span className="text-xs text-slate-500">Per contact</span>
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {/* Variable Statistics */}
              {campaign.message && (
                <div className="rounded-lg border border-border bg-card p-4">
                  <div className="mb-3 flex items-center gap-2">
                    <Badge variant="outline">
                      Variable Usage in Message
                    </Badge>
                  </div>
                  <div className="space-y-2">
                    <div className="flex items-start gap-3">
                      <div className="flex-1 rounded-md bg-muted/50 p-3">
                        <p className="whitespace-pre-wrap text-sm">
                          {campaign.message.split(/(\{\{\w+\}\})/).map((part, index) => {
                            const match = part.match(/\{\{(\w+)\}\}/);
                            if (match) {
                              const varName = match[1];
                              const isCampaignVar = varName in (campaign.campaign_variables || {});
                              const isContactVar = campaign.expected_variables?.includes(varName);
                              
                              return (
                                <span
                                  key={index}
                                  className={`inline-flex items-center gap-1 rounded px-2 py-0.5 font-mono text-sm font-semibold ${
                                    isCampaignVar
                                      ? 'bg-purple-100 text-purple-800'
                                      : isContactVar
                                      ? 'bg-emerald-100 text-emerald-800'
                                      : 'bg-red-100 text-red-800'
                                  }`}
                                >
                                  {part}
                                  {isCampaignVar && campaign.campaign_variables && (
                                    <span className="text-xs opacity-75">
                                      = {campaign.campaign_variables[varName]}
                                    </span>
                                  )}
                                </span>
                              );
                            }
                            return <span key={index}>{part}</span>;
                          })}
                        </p>
                      </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                      <div className="flex items-center gap-1.5 text-xs">
                        <div className="h-3 w-3 rounded-sm bg-purple-100 dark:bg-purple-900 border border-purple-300 dark:border-purple-700"></div>
                        <span className="text-muted-foreground">Campaign variable (with value)</span>
                      </div>
                      <div className="flex items-center gap-1.5 text-xs">
                        <div className="h-3 w-3 rounded-sm bg-emerald-100 dark:bg-emerald-900 border border-emerald-300 dark:border-emerald-700"></div>
                        <span className="text-muted-foreground">Contact variable (per contact)</span>
                      </div>
                      <div className="flex items-center gap-1.5 text-xs">
                        <div className="h-3 w-3 rounded-sm bg-red-100 dark:bg-red-900 border border-red-300 dark:border-red-700"></div>
                        <span className="text-muted-foreground">Undefined variable (error)</span>
                      </div>
                    </div>
                  </div>
                </div>
              )}

              {/* Summary Stats */}
              <div className="grid gap-4 md:grid-cols-3">
                <div className="flex items-center gap-3 rounded-lg border border-border bg-card p-4">
                  <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-400">
                    <span className="text-xl font-bold">{(campaign.expected_variables?.length || 0) + Object.keys(campaign.campaign_variables || {}).length}</span>
                  </div>
                  <div>
                    <div className="text-sm font-medium text-muted-foreground">Total Variables</div>
                    <div className="text-xs text-muted-foreground/70">Defined in campaign</div>
                  </div>
                </div>
                <div className="flex items-center gap-3 rounded-lg border border-purple-200 dark:border-purple-800 bg-purple-50/50 dark:bg-purple-950/20 p-4">
                  <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-purple-100 dark:bg-purple-900 text-purple-600 dark:text-purple-400">
                    <span className="text-xl font-bold">{Object.keys(campaign.campaign_variables || {}).length}</span>
                  </div>
                  <div>
                    <div className="text-sm font-medium">Campaign Variables</div>
                    <div className="text-xs text-purple-600 dark:text-purple-400">Same for all contacts</div>
                  </div>
                </div>
                <div className="flex items-center gap-3 rounded-lg border border-emerald-200 dark:border-emerald-800 bg-emerald-50/50 dark:bg-emerald-950/20 p-4">
                  <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-emerald-100 dark:bg-emerald-900 text-emerald-600 dark:text-emerald-400">
                    <span className="text-xl font-bold">{campaign.expected_variables?.length || 0}</span>
                  </div>
                  <div>
                    <div className="text-sm font-medium">Contact Variables</div>
                    <div className="text-xs text-emerald-600 dark:text-emerald-400">Unique per contact</div>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>
        )}

        {/* Tabs for Contacts and Calls */}
        <Tabs defaultValue="contacts" className="mt-6">
          <TabsList className="grid w-full grid-cols-2">
            <TabsTrigger value="contacts">
              Contacts ({campaign.campaign_contacts_count})
            </TabsTrigger>
            <TabsTrigger value="calls">
              Calls ({campaign.calls_count})
            </TabsTrigger>
          </TabsList>

          {/* Contacts Tab */}
          <TabsContent value="contacts" className="space-y-4">
            <CSVUploadZone
              campaignId={campaign.id}
              onUploadSuccess={() => router.reload({ only: ['campaign'] })}
            />

            {campaign.campaign_contacts.length > 0 && (
              <Card>
                <CardHeader>
                  <div className="flex items-center justify-between">
                    <CardTitle>Contact List</CardTitle>
                    <CardDescription>
                      Showing {campaign.campaign_contacts.length} of {campaign.campaign_contacts_count}
                    </CardDescription>
                  </div>
                </CardHeader>
                <CardContent>
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Phone Number</TableHead>
                        <TableHead>Name</TableHead>
                        <TableHead>Status</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {campaign.campaign_contacts.map((contact) => (
                        <TableRow key={contact.id}>
                          <TableCell className="font-mono">{contact.phone_number}</TableCell>
                          <TableCell>
                            {contact.first_name || contact.last_name
                              ? `${contact.first_name || ''} ${contact.last_name || ''}`.trim()
                              : '-'}
                          </TableCell>
                          <TableCell>
                            <Badge variant="outline" className="capitalize">
                              {contact.status}
                            </Badge>
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </CardContent>
              </Card>
            )}
          </TabsContent>

          {/* Calls Tab */}
          <TabsContent value="calls">
            {campaign.calls.length > 0 ? (
              <Card>
                <CardHeader>
                  <div className="flex items-center justify-between">
                    <CardTitle>Call History</CardTitle>
                    <CardDescription>
                      Showing {campaign.calls.length} of {campaign.calls_count}
                    </CardDescription>
                  </div>
                </CardHeader>
                <CardContent>
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>To Number</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Duration</TableHead>
                        <TableHead>Time</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {campaign.calls.map((call) => (
                        <TableRow key={call.id}>
                          <TableCell className="font-mono">{call.to_number}</TableCell>
                          <TableCell>
                            <div className="flex items-center gap-2">
                              {call.status === 'completed' && (
                                <CheckCircle2 className="h-4 w-4 text-green-600" />
                              )}
                              {call.status === 'failed' && (
                                <XCircle className="h-4 w-4 text-red-600" />
                              )}
                              {call.status === 'in-progress' && (
                                <Phone className="h-4 w-4 text-blue-600" />
                              )}
                              <span className="capitalize">{call.status}</span>
                            </div>
                          </TableCell>
                          <TableCell>
                            <div className="flex items-center gap-1 text-muted-foreground">
                              <Clock className="h-3.5 w-3.5" />
                              {formatDuration(call.duration)}
                            </div>
                          </TableCell>
                          <TableCell className="text-muted-foreground">
                            {new Date(call.created_at).toLocaleString()}
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </CardContent>
              </Card>
            ) : (
              <Card>
                <CardContent className="py-12 text-center">
                  <p className="text-sm text-muted-foreground">No calls yet</p>
                </CardContent>
              </Card>
            )}
          </TabsContent>
        </Tabs>
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
