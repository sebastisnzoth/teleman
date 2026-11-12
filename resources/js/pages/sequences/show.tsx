import AppLayout from '@/layouts/app-layout';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Progress } from '@/components/ui/progress';
import { Link, router, Head } from '@inertiajs/react';
import { Edit, Trash2, Play, Pause, ArrowLeft, Clock, CheckCircle2, XCircle, Activity } from 'lucide-react';
import { type BreadcrumbItem } from '@/types';
import { ConfirmationModal } from '@/components/confirmation-modal';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

interface Sequence {
  id: number;
  name: string;
  description: string;
  trigger_type: string;
  is_active: boolean;
  use_smart_timing: boolean;
  priority: number;
  total_enrolled: number;
  total_completed: number;
  total_converted: number;
  conversion_rate: number;
  steps: Step[];
  enrollments: Enrollment[];
}

interface Step {
  id: number;
  step_number: number;
  step_name: string;
  delay_amount: number;
  delay_unit: string;
  action_type: string;
  total_executed: number;
  total_successful: number;
  success_rate: number;
}

interface Enrollment {
  id: number;
  status: string;
  current_step: number;
  steps_completed: number;
  next_step_at: string;
  enrolled_at: string;
  contact: {
    id: number;
    first_name: string;
    last_name: string;
    phone_number: string;
  };
}

interface Props {
  sequence: Sequence;
  analytics: {
    total_enrolled: number;
    active: number;
    completed: number;
    converted: number;
    stopped: number;
    conversion_rate: number;
    success_rate: number;
    avg_completion_time: number;
    steps_performance: Array<{
      step_number: number;
      step_name: string;
      action_type: string;
      total_executed: number;
      total_successful: number;
      success_rate: number;
    }>;
  };
}

const statusColors: Record<string, string> = {
  active: 'bg-blue-100 text-blue-800',
  paused: 'bg-yellow-100 text-yellow-800',
  completed: 'bg-green-100 text-green-800',
  stopped: 'bg-red-100 text-red-800',
  converted: 'bg-purple-100 text-purple-800',
};

export default function SequencesShow({ sequence, analytics }: Props) {
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
    { title: sequence.name, href: `/sequences/${sequence.id}` },
  ];

  const handleToggleActive = () => {
    const action = sequence.is_active ? 'deactivate' : 'activate';
    
    setConfirmAction({
      open: true,
      title: `${action === 'activate' ? 'Activate' : 'Deactivate'} Sequence`,
      description: `Are you sure you want to ${action} "${sequence.name}"?`,
      variant: 'default',
      onConfirm: () => {
        router.post(`/api/v1/sequences/${sequence.id}/${action}`, {}, {
          preserveScroll: true,
          onSuccess: () => router.reload({ only: ['sequence'] }),
        });
        setConfirmAction({ ...confirmAction, open: false });
      },
    });
  };

  const formatDuration = (seconds: number) => {
    if (!seconds) return 'N/A';
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    if (hours > 24) {
      const days = Math.floor(hours / 24);
      return `${days}d ${hours % 24}h`;
    }
    return hours > 0 ? `${hours}h ${minutes}m` : `${minutes}m`;
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={sequence.name} />

      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-4">
            <Link href="/sequences">
              <Button variant="outline" size="sm">
                <ArrowLeft className="h-4 w-4" />
              </Button>
            </Link>
            <div>
              <div className="flex items-center gap-2 mb-1">
                <Heading title={sequence.name} />
                <Badge variant={sequence.is_active ? 'default' : 'secondary'}>
                  {sequence.is_active ? 'Active' : 'Inactive'}
                </Badge>
                {sequence.use_smart_timing && (
                  <Badge variant="outline" className="bg-purple-100 text-purple-800">
                    AI Timing
                  </Badge>
                )}
              </div>
              <p className="text-sm text-muted-foreground">{sequence.description}</p>
            </div>
          </div>
          <div className="flex items-center gap-2">
            <Link href={`/sequences/${sequence.id}/edit`}>
              <Button variant="outline">
                <Edit className="mr-2 h-4 w-4" />
                {t('common.edit')}
              </Button>
            </Link>
            <Button variant="outline" onClick={handleToggleActive}>
              {sequence.is_active ? (
                <>
                  <Pause className="mr-2 h-4 w-4" />
                  Deactivate
                </>
              ) : (
                <>
                  <Play className="mr-2 h-4 w-4" />
                  Activate
                </>
              )}
            </Button>
          </div>
        </div>

        {/* Stats */}
        <div className="grid gap-4 md:grid-cols-5">
          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium">Total Enrolled</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{analytics.total_enrolled}</div>
            </CardContent>
          </Card>
          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium">Active</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-blue-600">{analytics.active}</div>
            </CardContent>
          </Card>
          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium">Completed</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{analytics.completed}</div>
            </CardContent>
          </Card>
          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium">Converted</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-green-600">{analytics.converted}</div>
            </CardContent>
          </Card>
          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium">Conversion Rate</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{analytics.conversion_rate.toFixed(1)}%</div>
            </CardContent>
          </Card>
        </div>

        {/* Tabs */}
        <Tabs defaultValue="steps" className="space-y-4">
          <TabsList>
            <TabsTrigger value="steps">{t('sequences.steps')}</TabsTrigger>
            <TabsTrigger value="enrollments">Enrollments</TabsTrigger>
            <TabsTrigger value="analytics">Analytics</TabsTrigger>
          </TabsList>

          {/* Steps Tab */}
          <TabsContent value="steps" className="space-y-4">
            {sequence.steps.map((step, index) => (
              <Card key={step.id}>
                <CardHeader>
                  <div className="flex items-center justify-between">
                    <div>
                      <CardTitle className="text-base">
                        Step {step.step_number}: {step.step_name}
                      </CardTitle>
                      <CardDescription>
                        Wait {step.delay_amount} {step.delay_unit}, then {step.action_type}
                      </CardDescription>
                    </div>
                    <Badge variant="outline">
                      {step.action_type}
                    </Badge>
                  </div>
                </CardHeader>
                <CardContent>
                  <div className="grid gap-4 md:grid-cols-4">
                    <div>
                      <div className="text-sm text-muted-foreground">Executed</div>
                      <div className="text-xl font-bold">{step.total_executed}</div>
                    </div>
                    <div>
                      <div className="text-sm text-muted-foreground">Successful</div>
                      <div className="text-xl font-bold text-green-600">{step.total_successful}</div>
                    </div>
                    <div>
                      <div className="text-sm text-muted-foreground">Success Rate</div>
                      <div className="text-xl font-bold">{step.success_rate.toFixed(1)}%</div>
                    </div>
                    <div>
                      <Progress value={step.success_rate} className="mt-2" />
                    </div>
                  </div>
                </CardContent>
              </Card>
            ))}
          </TabsContent>

          {/* Enrollments Tab */}
          <TabsContent value="enrollments" className="space-y-4">
            {sequence.enrollments && sequence.enrollments.length > 0 ? (
              sequence.enrollments.map((enrollment) => (
                <Card key={enrollment.id}>
                  <CardHeader>
                    <div className="flex items-center justify-between">
                      <div>
                        <CardTitle className="text-base">
                          {enrollment.contact.first_name} {enrollment.contact.last_name}
                        </CardTitle>
                        <CardDescription>{enrollment.contact.phone_number}</CardDescription>
                      </div>
                      <Badge className={statusColors[enrollment.status]}>
                        {enrollment.status}
                      </Badge>
                    </div>
                  </CardHeader>
                  <CardContent>
                    <div className="grid gap-4 md:grid-cols-4">
                      <div>
                        <div className="text-sm text-muted-foreground">Current Step</div>
                        <div className="text-lg font-semibold">{enrollment.current_step} of {sequence.steps.length}</div>
                      </div>
                      <div>
                        <div className="text-sm text-muted-foreground">Steps Completed</div>
                        <div className="text-lg font-semibold">{enrollment.steps_completed}</div>
                      </div>
                      <div>
                        <div className="text-sm text-muted-foreground">Enrolled At</div>
                        <div className="text-sm">{new Date(enrollment.enrolled_at).toLocaleDateString()}</div>
                      </div>
                      <div>
                        <div className="text-sm text-muted-foreground">Next Step</div>
                        <div className="text-sm">
                          {enrollment.next_step_at 
                            ? new Date(enrollment.next_step_at).toLocaleDateString()
                            : 'N/A'}
                        </div>
                      </div>
                    </div>
                    <Progress 
                      value={(enrollment.steps_completed / sequence.steps.length) * 100} 
                      className="mt-4" 
                    />
                  </CardContent>
                </Card>
              ))
            ) : (
              <Card>
                <CardContent className="flex flex-col items-center justify-center py-12">
                  <Activity className="h-12 w-12 text-muted-foreground mb-4" />
                  <h3 className="text-lg font-semibold mb-2">No enrollments yet</h3>
                  <p className="text-sm text-muted-foreground text-center">
                    Contacts will be automatically enrolled when they match the trigger conditions.
                  </p>
                </CardContent>
              </Card>
            )}
          </TabsContent>

          {/* Analytics Tab */}
          <TabsContent value="analytics" className="space-y-4">
            <div className="grid gap-4 md:grid-cols-2">
              <Card>
                <CardHeader>
                  <CardTitle>Performance Overview</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="flex items-center justify-between">
                    <span className="text-sm text-muted-foreground">Success Rate</span>
                    <span className="text-lg font-bold">{analytics.success_rate.toFixed(1)}%</span>
                  </div>
                  <div className="flex items-center justify-between">
                    <span className="text-sm text-muted-foreground">Conversion Rate</span>
                    <span className="text-lg font-bold">{analytics.conversion_rate.toFixed(1)}%</span>
                  </div>
                  <div className="flex items-center justify-between">
                    <span className="text-sm text-muted-foreground">Avg Completion Time</span>
                    <span className="text-lg font-bold">{formatDuration(analytics.avg_completion_time)}</span>
                  </div>
                </CardContent>
              </Card>

              <Card>
                <CardHeader>
                  <CardTitle>Status Breakdown</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="flex items-center justify-between">
                    <span className="text-sm text-muted-foreground">Active</span>
                    <span className="text-lg font-bold text-blue-600">{analytics.active}</span>
                  </div>
                  <div className="flex items-center justify-between">
                    <span className="text-sm text-muted-foreground">Completed</span>
                    <span className="text-lg font-bold">{analytics.completed}</span>
                  </div>
                  <div className="flex items-center justify-between">
                    <span className="text-sm text-muted-foreground">Converted</span>
                    <span className="text-lg font-bold text-green-600">{analytics.converted}</span>
                  </div>
                  <div className="flex items-center justify-between">
                    <span className="text-sm text-muted-foreground">Stopped</span>
                    <span className="text-lg font-bold text-red-600">{analytics.stopped}</span>
                  </div>
                </CardContent>
              </Card>
            </div>

            <Card>
              <CardHeader>
                <CardTitle>Step Performance</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="space-y-4">
                  {analytics.steps_performance.map((step) => (
                    <div key={step.step_number} className="space-y-2">
                      <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                          <span className="font-medium">Step {step.step_number}</span>
                          <span className="text-sm text-muted-foreground">{step.step_name}</span>
                          <Badge variant="outline">{step.action_type}</Badge>
                        </div>
                        <span className="text-sm font-medium">{step.success_rate.toFixed(1)}%</span>
                      </div>
                      <div className="flex items-center gap-4">
                        <Progress value={step.success_rate} className="flex-1" />
                        <span className="text-xs text-muted-foreground">
                          {step.total_successful}/{step.total_executed}
                        </span>
                      </div>
                    </div>
                  ))}
                </div>
              </CardContent>
            </Card>
          </TabsContent>
        </Tabs>
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
