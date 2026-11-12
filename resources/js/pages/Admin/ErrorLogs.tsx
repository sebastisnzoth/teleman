import { Head, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AppLayout from '@/layouts/app-layout';
import Heading from '@/components/heading';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { RefreshCw, AlertTriangle, Clock, FileText } from 'lucide-react';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { format } from 'date-fns';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useState } from 'react';
import { providerLabel, useTelephony } from '@/lib/telephony';

interface ErrorLog {
    sid: string;
    error_code: string;
    log_level: string;
    alert_text: string;
    request_method: string | null;
    request_url: string | null;
    request_variables: string | null;
    response_body: string | null;
    response_headers: string | null;
    service_sid: string | null;
    resource_sid: string | null;
    date_created: string | null;
    date_generated: string | null;
    date_updated: string | null;
}

interface Props {
    errorLogs: ErrorLog[];
    count?: number;
    limit?: number;
    error?: string;
}

const logLevelColors: Record<string, string> = {
    error: 'destructive',
    warning: 'default',
    notice: 'secondary',
};

export default function ErrorLogs({ errorLogs = [], count = 0, limit = 10, error }: Props) {
  const { t } = useTranslation();
    const [selectedLog, setSelectedLog] = useState<ErrorLog | null>(null);
    // Every alert on this screen comes out of Twilio's Monitor API, and no
    // other provider has a feed this page can read — so the copy below
    // rightly names Twilio rather than whichever provider is locked.
    // Relabelling it would tell a Plivo admin to go and fix Plivo
    // credentials that this screen never reads. What an installation not
    // running Twilio needs instead is to be told that plainly, which is
    // the notice at the top of the page.
    const { provider, locked } = useTelephony();
    const readsThisInstallationsProvider = !locked || provider === 'twilio';
    const providerName = providerLabel(provider);
    
    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('common.dashboard'), href: dashboard().url },
        {
            title: t('admin.error_logs.error_logs'), href: '/admin/error-logs',
        },
    ];

    const handleRefresh = () => {
        router.reload();
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('admin.error_logs.error_logs')} />

            <div className="mx-auto max-w-7xl space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title={t('admin.error_logs.twilio_error_logs')}
                        description={t('admin.error_logs.monitor_and_troubleshoot_twilio_api_errors_and_ale')}
                    />
                    <Button onClick={handleRefresh} size="sm">
                        <RefreshCw className="mr-2 h-4 w-4" />
                        Refresh
                    </Button>
                </div>

                {!readsThisInstallationsProvider && (
                    <Alert>
                        <AlertTriangle className="h-4 w-4" />
                        <AlertTitle>These alerts come from Twilio</AlertTitle>
                        <AlertDescription>
                            This screen reads Twilio's Monitor API, the only alert feed it can read.
                            This installation runs on {providerName}, so there is nothing for it to
                            show here — {providerName}'s own logs live in the {providerName} console.
                        </AlertDescription>
                    </Alert>
                )}

                {error && (
                    <Alert variant="destructive">
                        <AlertTriangle className="h-4 w-4" />
                        <AlertTitle>Error Loading Logs</AlertTitle>
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="text-sm font-medium">{t('admin.error_logs.total_errors')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{count}</div>
                            <p className="text-xs text-muted-foreground">Last {limit} alerts</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="text-sm font-medium">{t('admin.error_logs.data_source')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">Live</div>
                            <p className="text-xs text-muted-foreground">Twilio Monitor API</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="text-sm font-medium">{t('admin.error_logs.auto_refresh')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">Manual</div>
                            <p className="text-xs text-muted-foreground">Click refresh to update</p>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('admin.error_logs.error_details')}</CardTitle>
                        <CardDescription>
                            Showing the latest {limit} error alerts from your Twilio account
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {errorLogs.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-12 text-center">
                                <FileText className="mb-4 h-12 w-12 text-muted-foreground" />
                                <h3 className="mb-2 text-lg font-semibold">No Error Logs Found</h3>
                                <p className="text-sm text-muted-foreground">
                                    {error
                                        ? 'Unable to fetch error logs. Please check your Twilio configuration.'
                                        : 'Great! There are no recent errors in your Twilio account.'}
                                </p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Error Code</TableHead>
                                            <TableHead>Level</TableHead>
                                            <TableHead>Alert Message</TableHead>
                                            <TableHead>Resource SID</TableHead>
                                            <TableHead>Date</TableHead>
                                            <TableHead>Details</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {errorLogs.map((log) => (
                                            <TableRow key={log.sid}>
                                                <TableCell className="font-mono text-sm">
                                                    <Badge variant="outline">{log.error_code}</Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant={logLevelColors[log.log_level] as any}>
                                                        {log.log_level}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="max-w-md">
                                                    <p className="truncate text-sm">{log.alert_text}</p>
                                                </TableCell>
                                                <TableCell>
                                                    {log.resource_sid ? (
                                                        <code className="text-xs">{log.resource_sid}</code>
                                                    ) : (
                                                        <span className="text-muted-foreground">-</span>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    {log.date_created ? (
                                                        <div className="flex items-center gap-1 text-sm">
                                                            <Clock className="h-3 w-3" />
                                                            {format(new Date(log.date_created), 'MMM dd, HH:mm')}
                                                        </div>
                                                    ) : (
                                                        <span className="text-muted-foreground">-</span>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => setSelectedLog(log)}
                                                        className="h-8 text-xs"
                                                    >
                                                        View Details
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {errorLogs.length > 0 && (
                    <Alert>
                        <AlertTriangle className="h-4 w-4" />
                        <AlertTitle>About Error Logs</AlertTitle>
                        <AlertDescription>
                            These logs are fetched directly from Twilio's Monitor API and are not stored in your
                            database. Refresh this page to get the latest errors. For detailed error code
                            documentation, visit{' '}
                            <a
                                href="https://www.twilio.com/docs/api/errors"
                                target="_blank"
                                rel="noopener noreferrer"
                                className="font-medium underline"
                            >
                                Twilio Error Codes Documentation
                            </a>
                            .
                        </AlertDescription>
                    </Alert>
                )}
            </div>

            {/* Error Details Modal */}
            <Dialog open={!!selectedLog} onOpenChange={() => setSelectedLog(null)}>
                <DialogContent className="max-w-3xl max-h-[80vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle className="flex items-center gap-2">
                            <Badge variant="outline">{selectedLog?.error_code}</Badge>
                            <span>Error Details</span>
                        </DialogTitle>
                        <DialogDescription>
                            Detailed information about this Twilio error
                        </DialogDescription>
                    </DialogHeader>

                    {selectedLog && (
                        <div className="space-y-4">
                            {/* Alert Text */}
                            <div className="rounded-lg border border-destructive/20 bg-destructive/5 p-4">
                                <h4 className="mb-2 font-semibold text-sm">Alert Message</h4>
                                <p className="text-sm">{selectedLog.alert_text}</p>
                            </div>

                            {/* Basic Info Grid */}
                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-1">
                                    <p className="text-xs font-medium text-muted-foreground">Error Code</p>
                                    <Badge variant="outline" className="font-mono">
                                        {selectedLog.error_code}
                                    </Badge>
                                </div>
                                <div className="space-y-1">
                                    <p className="text-xs font-medium text-muted-foreground">Log Level</p>
                                    <Badge variant={logLevelColors[selectedLog.log_level] as any}>
                                        {selectedLog.log_level}
                                    </Badge>
                                </div>
                                <div className="space-y-1">
                                    <p className="text-xs font-medium text-muted-foreground">Date Created</p>
                                    <p className="text-sm font-mono">
                                        {selectedLog.date_created
                                            ? format(new Date(selectedLog.date_created), 'MMM dd, yyyy HH:mm:ss')
                                            : '-'}
                                    </p>
                                </div>
                                <div className="space-y-1">
                                    <p className="text-xs font-medium text-muted-foreground">Resource SID</p>
                                    <p className="text-sm font-mono break-all">
                                        {selectedLog.resource_sid || '-'}
                                    </p>
                                </div>
                            </div>

                            {/* Request Details */}
                            {(selectedLog.request_url || selectedLog.request_method) && (
                                <div className="space-y-2">
                                    <h4 className="font-semibold text-sm">Request Details</h4>
                                    <div className="rounded-lg bg-muted p-4 space-y-3">
                                        {selectedLog.request_method && (
                                            <div className="space-y-1">
                                                <p className="text-xs font-medium text-muted-foreground">Method</p>
                                                <Badge variant="outline">{selectedLog.request_method}</Badge>
                                            </div>
                                        )}
                                        {selectedLog.request_url && (
                                            <div className="space-y-1">
                                                <p className="text-xs font-medium text-muted-foreground">URL</p>
                                                <p className="text-sm font-mono break-all rounded bg-background p-2">
                                                    {selectedLog.request_url}
                                                </p>
                                            </div>
                                        )}
                                        {selectedLog.request_variables && (
                                            <div className="space-y-1">
                                                <p className="text-xs font-medium text-muted-foreground">Variables</p>
                                                <pre className="text-xs font-mono rounded bg-background p-2 overflow-auto max-h-32">
                                                    {selectedLog.request_variables}
                                                </pre>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            )}

                            {/* Response Details */}
                            {(selectedLog.response_body || selectedLog.response_headers) && (
                                <div className="space-y-2">
                                    <h4 className="font-semibold text-sm">Response Details</h4>
                                    <div className="rounded-lg bg-muted p-4 space-y-3">
                                        {selectedLog.response_headers && (
                                            <div className="space-y-1">
                                                <p className="text-xs font-medium text-muted-foreground">Headers</p>
                                                <pre className="text-xs font-mono rounded bg-background p-2 overflow-auto max-h-32">
                                                    {selectedLog.response_headers}
                                                </pre>
                                            </div>
                                        )}
                                        {selectedLog.response_body && (
                                            <div className="space-y-1">
                                                <p className="text-xs font-medium text-muted-foreground">Body</p>
                                                <pre className="text-xs font-mono rounded bg-background p-2 overflow-auto max-h-48">
                                                    {selectedLog.response_body}
                                                </pre>
                                            </div>
                                        )}
                                    </div>
                                </div>
                            )}

                            {/* SID */}
                            <div className="space-y-1">
                                <p className="text-xs font-medium text-muted-foreground">Alert SID</p>
                                <p className="text-sm font-mono break-all rounded bg-muted p-2">
                                    {selectedLog.sid}
                                </p>
                            </div>

                            {/* Action Button */}
                            <div className="flex justify-end pt-2">
                                <Button variant="outline" onClick={() => setSelectedLog(null)}>
                                    Close
                                </Button>
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
