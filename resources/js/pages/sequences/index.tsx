import AppLayout from '@/layouts/app-layout';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Link, router, Head } from '@inertiajs/react';
import { Plus, Play, Pause, Edit, Trash2, Eye, Activity, Users, TrendingUp } from 'lucide-react';
import { PageHelp } from '@/components/page-help';
import { ConfirmationModal } from '@/components/confirmation-modal';
import { useState } from 'react';
import { type BreadcrumbItem } from '@/types';
import { useTranslation } from 'react-i18next';

interface Sequence {
  id: number;
  name: string;
  description: string;
  trigger_type: 'no_answer' | 'interested' | 'callback_requested' | 'not_interested' | 'manual' | 'completed';
  is_active: boolean;
  use_smart_timing: boolean;
  priority: number;
  total_enrolled: number;
  total_completed: number;
  total_converted: number;
  conversion_rate: number;
  steps_count: number;
  enrollments_count: number;
  created_at: string;
}

interface Props {
  sequences: {
    data: Sequence[];
    meta: {
      current_page: number;
      total: number;
      per_page: number;
    };
  };
  stats: {
    total: number;
    enrolled: number;
    converted: number;
    avg_conversion_rate: number;
  };
}

const triggerConfig: Record<Sequence['trigger_type'], { label: string; color: string }> = {
  no_answer: { label: 'No Answer', color: 'bg-yellow-100 text-yellow-800' },
  interested: { label: 'Interested', color: 'bg-green-100 text-green-800' },
  callback_requested: { label: 'Callback Requested', color: 'bg-blue-100 text-blue-800' },
  not_interested: { label: 'Not Interested', color: 'bg-red-100 text-red-800' },
  manual: { label: 'Manual', color: 'bg-purple-100 text-purple-800' },
  completed: { label: 'Completed', color: 'bg-gray-100 text-gray-800' },
};

export default function SequencesIndex({ sequences, stats }: Props) {
  const { t } = useTranslation();
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
    variant: 'default',
  });

  const breadcrumbs: BreadcrumbItem[] = [
    { title: t('common.dashboard'), href: '/dashboard' },
    { title: t('sequences.title'), href: '/sequences' },
  ];

  const handleToggleActive = async (sequence: Sequence) => {
    const action = sequence.is_active ? 'deactivate' : 'activate';
    
    setConfirmAction({
      open: true,
      title: `${action === 'activate' ? 'Activate' : 'Deactivate'} Sequence`,
      description: `Are you sure you want to ${action} "${sequence.name}"? ${
        action === 'activate' 
          ? 'New contacts matching the trigger will be automatically enrolled.' 
          : 'No new contacts will be enrolled, but active enrollments will continue.'
      }`,
      variant: 'default',
      onConfirm: () => {
        router.post(`/api/v1/sequences/${sequence.id}/${action}`, {}, {
          preserveScroll: true,
          onSuccess: () => router.reload({ only: ['sequences'] }),
        });
        setConfirmAction({ ...confirmAction, open: false });
      },
    });
  };

  const handleDelete = (sequence: Sequence) => {
    setConfirmAction({
      open: true,
      title: 'Delete Sequence',
      description: `Are you sure you want to delete "${sequence.name}"? This action cannot be undone.`,
      variant: 'destructive',
      onConfirm: () => {
        router.delete(`/api/v1/sequences/${sequence.id}`, {
          preserveScroll: true,
          onSuccess: () => router.reload({ only: ['sequences'] }),
        });
        setConfirmAction({ ...confirmAction, open: false });
      },
    });
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={t('sequences.title')} />

      <div className="mx-auto max-w-7xl space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <Heading
              title={t('sequences.title')}
              description={t('sequences.description')}
            />
          </div>
          <div className="flex items-center gap-2">
            <PageHelp
              title="Follow-Up Sequences"
              sections={[
                {
                  title: "What are Sequences?",
                  content: "Follow-up sequences automatically nurture leads with multi-step campaigns. Contacts are enrolled based on call outcomes (no answer, interested, etc.) and receive calls, SMS, or emails at optimal times."
                },
                {
                  title: "Trigger Types",
                  content: "Sequences can be triggered by various call outcomes:\n• No Answer - Contact didn't answer the call\n• Interested - Positive sentiment or DTMF response\n• Callback Requested - Contact asked to be called back\n• Not Interested - Negative response\n• Manual - Manually add contacts\n• Completed - After a successful call"
                },
                {
                  title: "Smart Timing",
                  content: "Enable AI-powered timing to automatically schedule follow-ups at optimal times based on contact behavior and historical data."
                },
                {
                  title: "Step Actions",
                  content: "Each step can perform different actions:\n• Call - Make an automated phone call\n• SMS - Send a text message\n• Email - Send an email\n• Wait - Pause for manual intervention\n• Webhook - Trigger external integrations"
                }
              ]}
            />
            <Link href="/sequences/create">
              <Button>
                <Plus className="mr-2 h-4 w-4" />
                {t('sequences.add')}
              </Button>
            </Link>
          </div>
        </div>

        {/* Stats Overview */}
        <div className="grid gap-4 md:grid-cols-4">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Total Sequences</CardTitle>
              <Activity className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{stats.total}</div>
              <p className="text-xs text-muted-foreground">
                {sequences.data.filter(s => s.is_active).length} active
              </p>
            </CardContent>
          </Card>
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Total Enrolled</CardTitle>
              <Users className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{stats.enrolled}</div>
              <p className="text-xs text-muted-foreground">Contacts in sequences</p>
            </CardContent>
          </Card>
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Total Converted</CardTitle>
              <TrendingUp className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{stats.converted}</div>
              <p className="text-xs text-muted-foreground">Successful outcomes</p>
            </CardContent>
          </Card>
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">Avg Conversion Rate</CardTitle>
              <TrendingUp className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{stats.avg_conversion_rate}%</div>
              <p className="text-xs text-muted-foreground">Across all sequences</p>
            </CardContent>
          </Card>
        </div>

        {/* Sequences List */}
        {sequences.data.length === 0 ? (
          <Card>
            <CardContent className="flex flex-col items-center justify-center py-12">
              <Activity className="h-12 w-12 text-muted-foreground mb-4" />
              <h3 className="text-lg font-semibold mb-2">{t('sequences.no_sequences')}</h3>
              <p className="text-sm text-muted-foreground mb-4 text-center max-w-md">
                Create your first follow-up sequence to automatically nurture leads
                based on call outcomes.
              </p>
              <Link href="/sequences/create">
                <Button>
                  <Plus className="mr-2 h-4 w-4" />
                  {t('sequences.add')}
                </Button>
              </Link>
            </CardContent>
          </Card>
        ) : (
          <div className="grid gap-4">
            {sequences.data.map((sequence) => (
              <Card key={sequence.id}>
                <CardHeader>
                  <div className="flex items-start justify-between">
                    <div className="space-y-1">
                      <div className="flex items-center gap-2">
                        <CardTitle>{sequence.name}</CardTitle>
                        <Badge variant={sequence.is_active ? 'default' : 'secondary'}>
                          {sequence.is_active ? 'Active' : 'Inactive'}
                        </Badge>
                        <Badge variant="outline" className={triggerConfig[sequence.trigger_type].color}>
                          {triggerConfig[sequence.trigger_type].label}
                        </Badge>
                        {sequence.use_smart_timing && (
                          <Badge variant="outline" className="bg-purple-100 text-purple-800">
                            AI Timing
                          </Badge>
                        )}
                        <Badge variant="outline">
                          Priority: {sequence.priority}
                        </Badge>
                      </div>
                      <CardDescription>{sequence.description}</CardDescription>
                    </div>
                    <div className="flex items-center gap-2">
                      <Link href={`/sequences/${sequence.id}`}>
                        <Button variant="outline" size="sm">
                          <Eye className="mr-2 h-4 w-4" />
                          {t('common.view')}
                        </Button>
                      </Link>
                      <Link href={`/sequences/${sequence.id}/edit`}>
                        <Button variant="outline" size="sm">
                          <Edit className="h-4 w-4" />
                        </Button>
                      </Link>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => handleToggleActive(sequence)}
                      >
                        {sequence.is_active ? (
                          <Pause className="h-4 w-4" />
                        ) : (
                          <Play className="h-4 w-4" />
                        )}
                      </Button>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => handleDelete(sequence)}
                      >
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    </div>
                  </div>
                </CardHeader>
                <CardContent>
                  <div className="grid gap-4 md:grid-cols-5">
                    <div>
                      <div className="text-sm font-medium text-muted-foreground">Steps</div>
                      <div className="text-2xl font-bold">{sequence.steps_count}</div>
                    </div>
                    <div>
                      <div className="text-sm font-medium text-muted-foreground">Enrolled</div>
                      <div className="text-2xl font-bold">{sequence.total_enrolled}</div>
                    </div>
                    <div>
                      <div className="text-sm font-medium text-muted-foreground">Completed</div>
                      <div className="text-2xl font-bold">{sequence.total_completed}</div>
                    </div>
                    <div>
                      <div className="text-sm font-medium text-muted-foreground">Converted</div>
                      <div className="text-2xl font-bold text-green-600">{sequence.total_converted}</div>
                    </div>
                    <div>
                      <div className="text-sm font-medium text-muted-foreground">Conversion Rate</div>
                      <div className="text-2xl font-bold">{sequence.conversion_rate.toFixed(1)}%</div>
                    </div>
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>
        )}
      </div>

      <ConfirmationModal
        open={confirmAction.open}
        onOpenChange={(open) => setConfirmAction({ ...confirmAction, open })}
        title={confirmAction.title}
        description={confirmAction.description}
        onConfirm={confirmAction.onConfirm}
        variant={confirmAction.variant}
      />
    </AppLayout>
  );
}
