import { Head, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AppLayout from '@/layouts/app-layout';
import Heading from '@/components/heading';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Activity, CheckCircle2, XCircle, Clock, Trash2, RefreshCw } from 'lucide-react';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { toast } from 'sonner';
import { useState } from 'react';

interface CronJob {
    name: string;
    is_healthy: boolean;
    last_run: {
        completed_at?: string;
        completed_at_human?: string;
        execution_time_ms?: number;
        jobs_processed?: number;
    } | null;
    stats?: {
        total_runs?: number;
        completed?: number;
        failed?: number;
        success_rate?: number;
        avg_execution_time_ms?: number;
        total_jobs_processed?: number;
    };
    recent_failures: Array<{
        started_at: string;
        started_at_human: string;
        output: string;
    }>;
}

interface RecentLog {
    id: number;
    job_name: string;
    status: 'started' | 'completed' | 'failed';
    started_at: string;
    started_at_human: string;
    completed_at: string | null;
    execution_time_ms: number | null;
    jobs_processed: number | null;
    output: string | null;
}

interface Props {
    jobs: CronJob[];
    recent_logs: RecentLog[];
    hours: number;
}

export default function CronMonitor({ jobs, recent_logs, hours }: Props) {
  const { t } = useTranslation();
    const [showCleanupDialog, setShowCleanupDialog] = useState(false);

    const handleCleanup = () => {
        setShowCleanupDialog(true);
    };

    const confirmCleanup = () => {
        router.post('/admin/cron-monitor/cleanup', { days: 7 }, {
            onSuccess: () => {
                toast.success('Old logs cleaned up successfully');
                setShowCleanupDialog(false);
            },
            onError: () => {
                toast.error('Failed to cleanup logs');
            }
        });
    };

    const handleRefresh = () => {
        router.reload({
            onSuccess: () => {
                toast.success('Data refreshed');
            }
        });
    };

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: t('common.dashboard'), href: dashboard().url,
        },
        {
            title: t('admin.cron.cron_monitor'), href: '/admin/cron-monitor',
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('admin.cron.cron_job_monitor')} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4 md:gap-8 md:p-8">
                <div className="flex items-center justify-between">
                    <Heading
                        title={t('admin.cron.cron_job_monitor')}
                        description={t('admin.cron.monitor_scheduled_tasks_and_queue_workers')}
                    />
                    <div className="flex gap-2">
                        <Button variant="outline" size="sm" onClick={handleRefresh}>
                            <RefreshCw className="h-4 w-4 mr-2" />
                            Refresh
                        </Button>
                        <Button variant="outline" size="sm" onClick={handleCleanup}>
                            <Trash2 className="h-4 w-4 mr-2" />
                            Clean Up Old Logs
                        </Button>
                    </div>
                </div>

                <div className="space-y-6">
                {/* Job Status Cards */}
                <div className="grid gap-6 md:grid-cols-2">
                    {jobs.map((job) => (
                        <Card key={job.name}>
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <CardTitle className="text-lg font-medium flex items-center gap-2">
                                        <Activity className="h-5 w-5" />
                                        {job.name}
                                    </CardTitle>
                                    <Badge variant={job.is_healthy ? 'default' : 'destructive'}>
                                        {job.is_healthy ? (
                                            <>
                                                <CheckCircle2 className="h-3 w-3 mr-1" />
                                                Healthy
                                            </>
                                        ) : (
                                            <>
                                                <XCircle className="h-3 w-3 mr-1" />
                                                Issues
                                            </>
                                        )}
                                    </Badge>
                                </div>
                                <CardDescription>
                                    {job.last_run ? (
                                        <span className="flex items-center gap-1 text-sm">
                                            <Clock className="h-3 w-3" />
                                            Last run: {job.last_run.completed_at_human}
                                        </span>
                                    ) : (
                                        <span className="text-destructive">Never executed</span>
                                    )}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-4">
                                    {/* Last Run Details */}
                                    {job.last_run && (
                                        <div className="grid grid-cols-2 gap-4 text-sm">
                                            <div>
                                                <p className="text-muted-foreground">Execution Time</p>
                                                <p className="font-medium">{job.last_run.execution_time_ms || 0}ms</p>
                                            </div>
                                            <div>
                                                <p className="text-muted-foreground">Jobs Processed</p>
                                                <p className="font-medium">{job.last_run.jobs_processed || 0}</p>
                                            </div>
                                        </div>
                                    )}

                                    {/* Statistics */}
                                    <div className="border-t pt-4">
                                        <p className="text-sm font-medium mb-3">Last {hours} Hours</p>
                                        <div className="grid grid-cols-3 gap-4 text-sm">
                                            <div>
                                                <p className="text-muted-foreground">Total Runs</p>
                                                <p className="text-lg font-semibold">{job.stats?.total_runs || 0}</p>
                                            </div>
                                            <div>
                                                <p className="text-muted-foreground">Success Rate</p>
                                                <p className="text-lg font-semibold text-green-600">
                                                    {job.stats?.success_rate || 0}%
                                                </p>
                                            </div>
                                            <div>
                                                <p className="text-muted-foreground">Failed</p>
                                                <p className="text-lg font-semibold text-red-600">
                                                    {job.stats?.failed || 0}
                                                </p>
                                            </div>
                                        </div>
                                        {job.name === 'queue:work' && (
                                            <div className="mt-3">
                                                <p className="text-muted-foreground">Total Jobs Processed</p>
                                                <p className="text-lg font-semibold">
                                                    {job.stats?.total_jobs_processed || 0}
                                                </p>
                                            </div>
                                        )}
                                        <div className="mt-3">
                                            <p className="text-muted-foreground">Avg Execution Time</p>
                                            <p className="text-lg font-semibold">
                                                {job.stats?.avg_execution_time_ms || 0}ms
                                            </p>
                                        </div>
                                    </div>

                                    {/* Recent Failures */}
                                    {job.recent_failures.length > 0 && (
                                        <div className="border-t pt-4">
                                            <p className="text-sm font-medium mb-2 text-destructive">
                                                Recent Failures
                                            </p>
                                            <div className="space-y-2">
                                                {job.recent_failures.slice(0, 3).map((failure, idx) => (
                                                    <div
                                                        key={idx}
                                                        className="text-xs p-2 bg-destructive/10 rounded"
                                                    >
                                                        <p className="font-medium">{failure.started_at_human}</p>
                                                        <p className="text-muted-foreground truncate">
                                                            {failure.output}
                                                        </p>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {/* Recent Activity Timeline */}
                <Card>
                    <CardHeader>
                        <CardTitle>{t('admin.cron.recent_activity')}</CardTitle>
                        <CardDescription>Last 50 executions</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-2">
                            {recent_logs.map((log) => (
                                <div
                                    key={log.id}
                                    className="flex items-center justify-between p-3 border rounded hover:bg-muted/50 transition-colors"
                                >
                                    <div className="flex items-center gap-3 flex-1">
                                        {log.status === 'completed' ? (
                                            <CheckCircle2 className="h-4 w-4 text-green-600" />
                                        ) : log.status === 'failed' ? (
                                            <XCircle className="h-4 w-4 text-red-600" />
                                        ) : (
                                            <Clock className="h-4 w-4 text-yellow-600" />
                                        )}
                                        <div className="flex-1">
                                            <p className="text-sm font-medium">{log.job_name}</p>
                                            <p className="text-xs text-muted-foreground">
                                                {log.started_at_human}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-4 text-sm">
                                        {log.execution_time_ms !== null && (
                                            <span className="text-muted-foreground">
                                                {log.execution_time_ms}ms
                                            </span>
                                        )}
                                        {log.jobs_processed !== null && log.jobs_processed > 0 && (
                                            <Badge variant="secondary">
                                                {log.jobs_processed} jobs
                                            </Badge>
                                        )}
                                        <Badge
                                            variant={
                                                log.status === 'completed'
                                                    ? 'default'
                                                    : log.status === 'failed'
                                                    ? 'destructive'
                                                    : 'secondary'
                                            }
                                        >
                                            {log.status}
                                        </Badge>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
                </div>
            </div>

            {/* Cleanup Confirmation Dialog */}
            <AlertDialog open={showCleanupDialog} onOpenChange={setShowCleanupDialog}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Clean Up Old Logs?</AlertDialogTitle>
                        <AlertDialogDescription>
                            This will permanently delete all cron job logs older than 7 days. 
                            This action cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={confirmCleanup} className="bg-destructive text-destructive-foreground hover:bg-destructive/90">
                            Delete Old Logs
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}
