import Heading from '@/components/heading';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { PageHelp } from '@/components/page-help';
import { toast } from 'sonner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import {
    Phone,
    Activity,
    AlertCircle,
    CheckCircle2,
    RefreshCw,
    ArrowLeft,
    PhoneCall,
    MessageSquare,
    Mail,
    Network,
    Info,
    UserPlus,
} from 'lucide-react';
import { useState, useEffect } from 'react';

interface PhoneNumber {
    id: number;
    phone_number: string;
    formatted: string;
    friendly_name: string | null;
    assigned_to: string;
    assigned_id: number | null;
    assigned_name: string | null;
    user_id: number | null;
    has_voice: boolean;
    has_sms: boolean;
    has_mms: boolean;
    is_available: boolean;
}

interface AssignmentOption {
    id: number;
    name: string;
    email?: string;
}

interface HealthLog {
    id: number;
    check_type: string;
    status: string;
    response_time_ms: number;
    error_message: string | null;
    checked_at: string;
}

interface Issue {
    severity: string;
    message: string;
}

interface TrunkData {
    id: number;
    friendly_name: string;
    status: string;
    health_status: string;
    trunk_sid: string;
    trunk_domain_name: string;
    origination_sip_url: string;
    termination_method: string;
    created_at: string;
    last_call_at: string | null;
    last_health_check_at: string | null;
    is_operational: boolean;
    phone_numbers: PhoneNumber[];
    // Local PBX fields
    routes_to_local_pbx?: boolean;
    local_pbx_sip_uri?: string;
    local_pbx_ip?: string;
    local_pbx_port?: number;
    local_pbx_transport?: string;
}

interface Statistics {
    total_calls: number;
    total_minutes: number;
    average_call_duration: number;
    active_numbers: number;
}

interface ShowProps {
    trunk: TrunkData;
    statistics: Statistics;
    healthHistory: HealthLog[];
    issues: Issue[];
}

const helpSections = [
    {
        title: 'Dashboard Overview',
        content: 'This dashboard provides real-time monitoring of your SIP trunk performance, phone numbers, and system health. Track usage statistics, view active phone numbers, and monitor health check results all in one place.',
    },
    {
        title: 'Statistics Cards',
        content: `Total Calls: Number of calls made through this trunk
Total Minutes: Cumulative call duration (helps calculate cost savings)
Avg Duration: Average call length in minutes
Active Numbers: Phone numbers currently on this trunk

These metrics update automatically after each call and help you monitor usage patterns.`,
    },
    {
        title: 'Available Actions',
        content: `Sync Phone Numbers: Manually fetch latest numbers from Twilio (useful after purchasing new numbers or making changes in Twilio Console)

Health Check: Run comprehensive diagnostics including API connectivity test, trunk configuration validation, phone number verification, inbound routing check, and outbound calling test. Results appear in Health History below.`,
    },
    {
        title: 'Configuration Details',
        content: `Trunk SID: Unique identifier in Twilio (for support reference)
Domain Name: Your trunk's SIP domain (used for routing)
Origination URL: Where incoming calls are routed to
Termination: How outbound calls are handled
Status: Current operational state (active, error, etc.)
Health Status: Overall system health (healthy, degraded, down)`,
    },
    {
        title: 'Phone Numbers Table',
        content: `Shows all phone numbers connected to this trunk with:
• Number: Formatted phone number
• Capabilities: Voice/SMS/MMS icons showing what the number can do
• Assignment: Where the number is currently used (Softphone, Campaign, AI Agent, or Unassigned)

Numbers with all capabilities enabled show all three icons. SMS-only or Voice-only numbers show limited icons.`,
    },
    {
        title: 'Health History',
        content: `Tracks all health check results with:
• Check Type: What was tested (api_connectivity, trunk_config, phone_numbers, inbound_routing, outbound_calling)
• Status: Success (green) or failure (red)
• Response Time: How long the test took in milliseconds
• Error Message: Details if test failed
• Timestamp: When the check was performed

Review this to troubleshoot issues or verify system reliability.`,
    },
    {
        title: 'Issue Alerts',
        content: `Red alerts at top of page indicate detected problems:
• High Severity: Immediate action required (trunk down, all numbers offline)
• Medium Severity: Performance impact (slow API, some numbers offline)
• Low Severity: Minor warnings (outdated config, stale data)

Click "Health Check" to run fresh diagnostics and update issue status.`,
    },
];

export default function Show({ trunk, statistics, healthHistory, issues }: ShowProps) {
  const { t } = useTranslation();
  const breadcrumbs: BreadcrumbItem[] = [
    { title: t('common.dashboard'), href: dashboard().url },
    { title: t('twilio.twilio_integration'), href: '/settings/twilio' },
    { title: t('twilio.sip_trunk'), href: '/sip-trunk' },
];
    const [syncing, setSyncing] = useState(false);
    const [checking, setChecking] = useState(false);

    // Assignment dialog state
    const [assignDialogOpen, setAssignDialogOpen] = useState(false);
    const [assigningNumber, setAssigningNumber] = useState<PhoneNumber | null>(null);
    const [assignType, setAssignType] = useState<string>('unassigned');
    const [assignTargetId, setAssignTargetId] = useState<string>('');
    const [assignSaving, setAssignSaving] = useState(false);
    const [assignmentOptions, setAssignmentOptions] = useState<{
        users: AssignmentOption[];
        campaigns: AssignmentOption[];
        ai_agents: AssignmentOption[];
    }>({ users: [], campaigns: [], ai_agents: [] });

    // Fetch assignment options when dialog opens
    useEffect(() => {
        if (assignDialogOpen) {
            window.axios.get(`/sip-trunk/${trunk.id}/assignment-options`)
                .then((res: any) => setAssignmentOptions(res.data))
                .catch(() => toast.error('Failed to load assignment options'));
        }
    }, [assignDialogOpen, trunk.id]);

    const openAssignDialog = (number: PhoneNumber) => {
        setAssigningNumber(number);
        setAssignType(number.assigned_to || 'unassigned');
        setAssignTargetId(
            number.assigned_to === 'softphone'
                ? (number.user_id?.toString() || '')
                : (number.assigned_id?.toString() || '')
        );
        setAssignDialogOpen(true);
    };

    const handleAssign = async () => {
        if (!assigningNumber) return;
        setAssignSaving(true);
        try {
            const payload: any = { assigned_to: assignType };
            if (assignType === 'softphone' && assignTargetId) {
                payload.user_id = parseInt(assignTargetId);
            } else if ((assignType === 'campaign' || assignType === 'ai_agent') && assignTargetId) {
                payload.assigned_id = parseInt(assignTargetId);
            }
            await window.axios.post(`/sip-trunk/${trunk.id}/numbers/${assigningNumber.id}/assign`, payload);
            toast.success('Number assigned successfully');
            setAssignDialogOpen(false);
            router.reload({ preserveScroll: true });
        } catch (error: any) {
            toast.error(error.response?.data?.message || 'Failed to assign number');
        } finally {
            setAssignSaving(false);
        }
    };

    const handleBack = () => {
        router.visit('/sip-trunk');
    };

    const handleSync = async () => {
        setSyncing(true);
        try {
            const response = await window.axios.post(`/sip-trunk/${trunk.id}/sync`);
            if (response.data.success) {
                toast.success('Phone numbers synced successfully');
                router.reload({ preserveScroll: true });
            }
        } catch (error: any) {
            console.error('Failed to sync:', error);
            toast.error(error.response?.data?.message || 'Failed to sync phone numbers');
        } finally {
            setSyncing(false);
        }
    };

    const handleHealthCheck = async () => {
        setChecking(true);
        try {
            const response = await window.axios.post(`/sip-trunk/${trunk.id}/health`);
            if (response.data.success) {
                toast.success('Health check completed successfully');
                router.reload({ preserveScroll: true });
            }
        } catch (error: any) {
            console.error('Failed to check health:', error);
            toast.error(error.response?.data?.message || 'Failed to perform health check');
        } finally {
            setChecking(false);
        }
    };

    const getStatusBadge = (status: string) => {
        const variants: Record<string, 'default' | 'success' | 'warning' | 'destructive'> = {
            active: 'success',
            setting_up: 'warning',
            error: 'destructive',
            suspended: 'default',
        };

        return (
            <Badge variant={variants[status] || 'default'}>
                {status.replace('_', ' ').toUpperCase()}
            </Badge>
        );
    };

    const getHealthBadge = (healthStatus: string) => {
        const colors: Record<string, string> = {
            healthy: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
            degraded: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
            down: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300',
        };

        return (
            <span
                className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${colors[healthStatus] || ''}`}
            >
                {healthStatus.toUpperCase()}
            </span>
        );
    };

    const getAssignmentBadge = (number: PhoneNumber) => {
        const assignedTo = number.assigned_to;
        const labels: Record<string, { text: string; icon: any }> = {
            softphone: { text: 'Softphone', icon: PhoneCall },
            campaign: { text: 'Campaign', icon: Activity },
            ai_agent: { text: 'AI Agent', icon: MessageSquare },
            unassigned: { text: 'Unassigned', icon: null },
        };

        const assignment = labels[assignedTo] || labels.unassigned;
        const Icon = assignment.icon;

        return (
            <div className="flex flex-col gap-1">
                <Badge variant={assignedTo === 'unassigned' ? 'outline' : 'default'}>
                    {Icon && <Icon className="mr-1 h-3 w-3" />}
                    {assignment.text}
                </Badge>
                {number.assigned_name && (
                    <span className="text-xs text-muted-foreground truncate max-w-[150px]">
                        {number.assigned_name}
                    </span>
                )}
            </div>
        );
    };

    const formatDate = (dateString: string | null) => {
        if (!dateString) return 'Never';
        return new Date(dateString).toLocaleString();
    };

    const getSeverityColor = (severity: string) => {
        const colors: Record<string, string> = {
            high: 'text-red-600 dark:text-red-400',
            medium: 'text-yellow-600 dark:text-yellow-400',
            low: 'text-blue-600 dark:text-blue-400',
        };
        return colors[severity] || colors.low;
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={trunk.friendly_name} />

            <div className="space-y-6 p-4 md:p-8">
                {/* Header */}
                <div className="flex items-start justify-between">
                    <div>
                        <Button variant="ghost" onClick={handleBack} className="mb-4 -ml-2">
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back to SIP Trunks
                        </Button>
                        <Heading title={trunk.friendly_name} />
                        <p className="mt-2 font-mono text-sm text-muted-foreground">{trunk.trunk_domain_name}</p>
                    </div>
                    <div className="flex items-center gap-2">
                        <PageHelp 
                            title={t('twilio.sip_trunk_dashboard_help')} 
                            sections={helpSections}
                            documentationUrl="/docs/documentation.html#sip-trunk"
                        />
                        <div className="flex flex-col items-end gap-2">
                            {getStatusBadge(trunk.status)}
                            {getHealthBadge(trunk.health_status)}
                        </div>
                    </div>
                </div>

                {/* Issues Alert */}
                {issues.length > 0 && (
                    <Alert variant="destructive">
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>
                            <p className="mb-2 font-semibold">Issues Detected</p>
                            <ul className="space-y-1 text-sm">
                                {issues.map((issue, index) => (
                                    <li key={index} className={getSeverityColor(issue.severity)}>
                                        [{issue.severity.toUpperCase()}] {issue.message}
                                    </li>
                                ))}
                            </ul>
                        </AlertDescription>
                    </Alert>
                )}

                {/* Statistics Cards */}
                <div className="grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">{t('twilio.total_calls')}</CardTitle>
                            <Activity className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{statistics.total_calls.toLocaleString()}</div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">{t('twilio.total_minutes')}</CardTitle>
                            <Activity className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {typeof statistics.total_minutes === 'number' 
                                    ? statistics.total_minutes.toFixed(2)
                                    : statistics.total_minutes}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">{t('twilio.avg_duration')}</CardTitle>
                            <Activity className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {statistics.average_call_duration > 0 
                                    ? `${statistics.average_call_duration.toFixed(1)}m`
                                    : '0.0m'}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">{t('twilio.active_numbers')}</CardTitle>
                            <Phone className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{statistics.active_numbers}</div>
                        </CardContent>
                    </Card>
                </div>

                {/* Trunk Configuration */}
                <Card>
                    <CardHeader>
                        <CardTitle>{t('twilio.trunk_configuration')}</CardTitle>
                        <CardDescription>Technical details and settings</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <dl className="grid gap-4 md:grid-cols-2">
                            <div>
                                <dt className="text-sm font-medium text-muted-foreground">Trunk SID</dt>
                                <dd className="mt-1 font-mono text-sm">{trunk.trunk_sid}</dd>
                            </div>
                            <div>
                                <dt className="text-sm font-medium text-muted-foreground">Domain Name</dt>
                                <dd className="mt-1 font-mono text-sm">{trunk.trunk_domain_name}</dd>
                            </div>
                            <div>
                                <dt className="text-sm font-medium text-muted-foreground">Origination URL</dt>
                                <dd className="mt-1 font-mono text-xs break-all">{trunk.origination_sip_url}</dd>
                            </div>
                            <div>
                                <dt className="text-sm font-medium text-muted-foreground">Termination Method</dt>
                                <dd className="mt-1 text-sm">{trunk.termination_method.toUpperCase()}</dd>
                            </div>
                            <div>
                                <dt className="text-sm font-medium text-muted-foreground">Created At</dt>
                                <dd className="mt-1 text-sm">{formatDate(trunk.created_at)}</dd>
                            </div>
                            <div>
                                <dt className="text-sm font-medium text-muted-foreground">Last Call</dt>
                                <dd className="mt-1 text-sm">{formatDate(trunk.last_call_at)}</dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                {/* Local PBX Configuration */}
                {trunk.routes_to_local_pbx && trunk.local_pbx_sip_uri && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Network className="h-5 w-5" />
                                Local PBX Routing
                            </CardTitle>
                            <CardDescription>
                                Calls are routed to/from your local SIP server
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <dl className="space-y-3 text-sm">
                                <div className="flex justify-between border-b pb-2">
                                    <dt className="font-medium text-muted-foreground">SIP URI:</dt>
                                    <dd className="font-mono text-xs">{trunk.local_pbx_sip_uri}</dd>
                                </div>
                                <div className="flex justify-between border-b pb-2">
                                    <dt className="font-medium text-muted-foreground">IP Address:</dt>
                                    <dd className="font-mono">{trunk.local_pbx_ip}</dd>
                                </div>
                                <div className="flex justify-between border-b pb-2">
                                    <dt className="font-medium text-muted-foreground">Port:</dt>
                                    <dd>{trunk.local_pbx_port}</dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt className="font-medium text-muted-foreground">Transport:</dt>
                                    <dd className="uppercase">{trunk.local_pbx_transport}</dd>
                                </div>
                            </dl>

                            <Alert className="mt-4">
                                <Info className="h-4 w-4" />
                                <AlertDescription className="text-xs">
                                    <strong>Inbound:</strong> Calls to your Twilio numbers forward to {trunk.local_pbx_ip}<br/>
                                    <strong>Outbound:</strong> Your PBX can send calls to Twilio at: {trunk.trunk_domain_name}
                                </AlertDescription>
                            </Alert>
                        </CardContent>
                    </Card>
                )}

                {/* Phone Numbers */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>{t('twilio.phone_numbers')}</CardTitle>
                                <CardDescription>
                                    {trunk.phone_numbers.length} numbers assigned to this trunk
                                </CardDescription>
                            </div>
                            <Button onClick={handleSync} variant="outline" size="sm" disabled={syncing}>
                                {syncing ? (
                                    <>
                                        <RefreshCw className="mr-2 h-4 w-4 animate-spin" />
                                        Syncing...
                                    </>
                                ) : (
                                    <>
                                        <RefreshCw className="mr-2 h-4 w-4" />
                                        Sync Numbers
                                    </>
                                )}
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {trunk.phone_numbers.length > 0 ? (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Phone Number</TableHead>
                                        <TableHead>Capabilities</TableHead>
                                        <TableHead>Assignment</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {trunk.phone_numbers.map((number) => (
                                        <TableRow key={number.id}>
                                            <TableCell>
                                                <div className="font-mono">{number.formatted}</div>
                                                {number.friendly_name && (
                                                    <div className="text-xs text-muted-foreground">{number.friendly_name}</div>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex gap-2">
                                                    {number.has_voice && (
                                                        <Badge variant="outline">
                                                            <Phone className="mr-1 h-3 w-3" />
                                                            Voice
                                                        </Badge>
                                                    )}
                                                    {number.has_sms && (
                                                        <Badge variant="outline">
                                                            <MessageSquare className="mr-1 h-3 w-3" />
                                                            SMS
                                                        </Badge>
                                                    )}
                                                    {number.has_mms && (
                                                        <Badge variant="outline">
                                                            <Mail className="mr-1 h-3 w-3" />
                                                            MMS
                                                        </Badge>
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell>{getAssignmentBadge(number)}</TableCell>
                                            <TableCell>
                                                {number.is_available ? (
                                                    <CheckCircle2 className="h-4 w-4 text-green-600" />
                                                ) : (
                                                    <Activity className="h-4 w-4 text-yellow-600" />
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => openAssignDialog(number)}
                                                >
                                                    <UserPlus className="mr-1 h-3 w-3" />
                                                    Assign
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        ) : (
                            <div className="py-8 text-center text-muted-foreground">
                                No phone numbers found. Click "Sync Numbers" to import them.
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Health History */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>{t('twilio.health_monitoring')}</CardTitle>
                                <CardDescription>Recent health check results</CardDescription>
                            </div>
                            <Button onClick={handleHealthCheck} variant="outline" size="sm" disabled={checking}>
                                {checking ? (
                                    <>
                                        <Activity className="mr-2 h-4 w-4 animate-spin" />
                                        Checking...
                                    </>
                                ) : (
                                    <>
                                        <Activity className="mr-2 h-4 w-4" />
                                        Run Health Check
                                    </>
                                )}
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {healthHistory.length > 0 ? (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Check Type</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Response Time</TableHead>
                                        <TableHead>Checked At</TableHead>
                                        <TableHead>Error</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {healthHistory.map((log) => (
                                        <TableRow key={log.id}>
                                            <TableCell className="capitalize">
                                                {log.check_type.replace('_', ' ')}
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={
                                                        log.status === 'success'
                                                            ? 'success'
                                                            : log.status === 'warning'
                                                              ? 'warning'
                                                              : 'destructive'
                                                    }
                                                >
                                                    {log.status.toUpperCase()}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>{log.response_time_ms}ms</TableCell>
                                            <TableCell>{formatDate(log.checked_at)}</TableCell>
                                            <TableCell>
                                                {log.error_message ? (
                                                    <span className="text-sm text-destructive">{log.error_message}</span>
                                                ) : (
                                                    '-'
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        ) : (
                            <div className="py-8 text-center text-muted-foreground">
                                No health checks performed yet. Click "Run Health Check" to start.
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Assignment Dialog */}
            <Dialog open={assignDialogOpen} onOpenChange={setAssignDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Assign Phone Number</DialogTitle>
                        <DialogDescription>
                            Assign <span className="font-mono font-semibold">{assigningNumber?.formatted}</span> to a user, campaign, or AI agent.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4 py-4">
                        <div className="space-y-2">
                            <label className="text-sm font-medium">Assignment Type</label>
                            <Select value={assignType} onValueChange={(val) => { setAssignType(val); setAssignTargetId(''); }}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select assignment type" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="unassigned">Unassigned</SelectItem>
                                    <SelectItem value="softphone">Softphone (User)</SelectItem>
                                    <SelectItem value="campaign">Campaign</SelectItem>
                                    <SelectItem value="ai_agent">AI Agent</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        {assignType === 'softphone' && (
                            <div className="space-y-2">
                                <label className="text-sm font-medium">Assign to User</label>
                                <Select value={assignTargetId} onValueChange={setAssignTargetId}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select a user" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {assignmentOptions.users.map((user) => (
                                            <SelectItem key={user.id} value={user.id.toString()}>
                                                {user.name} {user.email ? `(${user.email})` : ''}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        )}

                        {assignType === 'campaign' && (
                            <div className="space-y-2">
                                <label className="text-sm font-medium">Assign to Campaign</label>
                                <Select value={assignTargetId} onValueChange={setAssignTargetId}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select a campaign" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {assignmentOptions.campaigns.map((campaign) => (
                                            <SelectItem key={campaign.id} value={campaign.id.toString()}>
                                                {campaign.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        )}

                        {assignType === 'ai_agent' && (
                            <div className="space-y-2">
                                <label className="text-sm font-medium">Assign to AI Agent</label>
                                <Select value={assignTargetId} onValueChange={setAssignTargetId}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select an AI agent" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {assignmentOptions.ai_agents.map((agent) => (
                                            <SelectItem key={agent.id} value={agent.id.toString()}>
                                                {agent.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        )}
                    </div>

                    <DialogFooter>
                        <Button variant="outline" onClick={() => setAssignDialogOpen(false)}>
                            Cancel
                        </Button>
                        <Button onClick={handleAssign} disabled={assignSaving || (assignType !== 'unassigned' && !assignTargetId)}>
                            {assignSaving ? 'Saving...' : 'Save Assignment'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
