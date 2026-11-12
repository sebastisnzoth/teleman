import Heading from '@/components/heading';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { PageHelp } from '@/components/page-help';
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
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { Plus, Phone, Activity, AlertCircle, Trash2, RefreshCw } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

interface Trunk {
    id: number;
    friendly_name: string;
    status: string;
    health_status: string;
    trunk_domain_name: string;
    phone_numbers_count: number;
    total_calls_count: number;
    total_minutes_used: string | number;
    last_call_at: string | null;
    last_health_check_at: string | null;
    created_at: string;
    is_operational: boolean;
    is_setup_complete: boolean;
    setup_progress: number;
}

interface IndexProps {
    trunks: Trunk[];
    hasTrunk: boolean;
    hasActiveTrunk: boolean;
}

const helpSections = [
    {
        title: 'What is SIP Trunk?',
        content: 'SIP Trunking is a cost-effective way to make and receive calls through the internet. By connecting your Twilio account via SIP Trunk, you can significantly reduce call costs while maintaining full control of your phone numbers.',
    },
    {
        title: 'Cost Savings Explained',
        content: `Standard Twilio Pricing:
• $0.013 per minute for calls
• $1.00 per month per phone number

SIP Trunk Pricing:
• $0.0085 per minute (35% savings)
• $0 per month per phone number
• Same call quality and reliability

Example: With 50 numbers and 50,000 minutes/month, you save $275/month ($3,300/year)`,
    },
    {
        title: 'How to Get Started',
        content: `1. Click "Connect SIP Trunk" button
2. Enter your Twilio Account SID and Auth Token (found in Twilio Console)
3. Wait 60 seconds for automatic setup
4. View your dashboard and start making calls

The setup process is fully automatic - we create the trunk, import numbers, configure webhooks, and run health tests.`,
    },
    {
        title: 'Trunk Management Actions',
        content: `View: See detailed statistics, phone numbers, and health history
Sync: Manually sync phone numbers from Twilio (useful after adding new numbers)
Health Check: Run comprehensive tests to verify trunk functionality
Disconnect: Remove trunk connection (can reconnect anytime)`,
    },
    {
        title: 'Health Status Indicators',
        content: `Healthy (Green): All systems operational, trunk ready for calls
Degraded (Yellow): Some issues detected, calls may work but investigate recommended
Down (Red): Critical issues, immediate attention required

Health checks run automatically every 6 hours and test: API connectivity, trunk configuration, phone numbers, inbound routing, and outbound calling.`,
    },
    {
        title: 'Usage Statistics',
        content: `Each trunk card shows:
• Total calls made through this trunk
• Total minutes used
• Number of phone numbers connected
• Last call timestamp
• Last health check timestamp

These stats help you monitor usage and ensure optimal performance.`,
    },
];

export default function Index({ trunks, hasTrunk, hasActiveTrunk }: IndexProps) {
  const { t } = useTranslation();
  const breadcrumbs: BreadcrumbItem[] = [
    { title: t('common.dashboard'), href: dashboard().url },
    { title: t('twilio.twilio_integration'), href: '/settings/twilio' },
    { title: t('twilio.sip_trunk'), href: '/sip-trunk' },
];
    const [syncing, setSyncing] = useState<number | null>(null);
    const [checking, setChecking] = useState<number | null>(null);
    const [disconnectDialog, setDisconnectDialog] = useState<{ open: boolean; trunkId: number | null; trunkName: string }>({ open: false, trunkId: null, trunkName: '' });

    const handleSetup = () => {
        router.visit('/sip-trunk/setup');
    };

    const handleView = (trunkId: number) => {
        router.visit(`/sip-trunk/${trunkId}`);
    };

    const handleSync = async (trunkId: number) => {
        setSyncing(trunkId);
        try {
            const response = await window.axios.post(`/sip-trunk/${trunkId}/sync`);
            if (response.data.success) {
                toast.success('Phone numbers synced successfully');
                router.reload({ preserveScroll: true });
            }
        } catch (error: any) {
            console.error('Failed to sync:', error);
            toast.error(error.response?.data?.message || 'Failed to sync phone numbers');
        } finally {
            setSyncing(null);
        }
    };

    const handleHealthCheck = async (trunkId: number) => {
        setChecking(trunkId);
        try {
            const response = await window.axios.post(`/sip-trunk/${trunkId}/health`);
            if (response.data.success) {
                toast.success('Health check completed successfully');
                router.reload({ preserveScroll: true });
            }
        } catch (error: any) {
            console.error('Failed to check health:', error);
            toast.error(error.response?.data?.message || 'Failed to perform health check');
        } finally {
            setChecking(null);
        }
    };

    const handleDisconnect = (trunkId: number, trunkName: string) => {
        setDisconnectDialog({ open: true, trunkId, trunkName });
    };

    const confirmDisconnect = async (permanent: boolean = false) => {
        if (!disconnectDialog.trunkId) return;

        try {
            await window.axios.delete(`/sip-trunk/${disconnectDialog.trunkId}${permanent ? '?permanent=true' : ''}`);
            toast.success(permanent ? 'SIP Trunk permanently deleted' : 'SIP Trunk disconnected successfully');
            setDisconnectDialog({ open: false, trunkId: null, trunkName: '' });
            router.reload();
        } catch (error: any) {
            console.error('Failed to disconnect:', error);
            toast.error(error.response?.data?.message || 'Failed to disconnect trunk');
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
            <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${colors[healthStatus] || ''}`}>
                {healthStatus.toUpperCase()}
            </span>
        );
    };

    const formatDate = (dateString: string | null) => {
        if (!dateString) return 'Never';
        return new Date(dateString).toLocaleString();
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('twilio.sip_trunk')} />

            <div className="space-y-6 p-4 md:p-8">
                <div className="flex items-center justify-between">
                    <Heading title={t('twilio.sip_trunk_management')} />
                    <div className="flex items-center gap-2">
                        <PageHelp
                            title={t('twilio.sip_trunk_help')}
                            sections={helpSections}
                            documentationUrl="/docs/documentation.html#sip-trunk"
                        />
                        {!hasActiveTrunk && (
                            <Button onClick={handleSetup}>
                                <Plus className="mr-2 h-4 w-4" />
                                {hasTrunk ? 'Add New SIP Trunk' : 'Connect SIP Trunk Here'}
                            </Button>
                        )}
                    </div>
                </div>

                {!hasTrunk ? (
                    <Card>
                        <CardContent className="relative overflow-hidden p-12 text-center">
                            {/* Decorative background elements */}
                            <div className="absolute -right-20 -top-20 h-64 w-64 rounded-full bg-muted/30 blur-3xl" />
                            <div className="absolute -bottom-20 -left-20 h-56 w-56 rounded-full bg-muted/30 blur-3xl" />
                        
                            <div className="relative space-y-6">
                            {/* Icon */}
                            <div className="flex justify-center">
                                <div className="flex h-20 w-20 items-center justify-center rounded-3xl bg-primary shadow-xl">
                                    <Phone className="h-10 w-10 text-primary-foreground" />
                                </div>
                            </div>

                            {/* Title and Description */}
                            <div className="space-y-3">
                                <h3 className="text-2xl font-bold">
                                    No SIP Trunk Connected
                                </h3>
                                <p className="mx-auto max-w-md text-sm leading-relaxed text-muted-foreground">
                                    Connect your SIP Trunk Here.
                                </p>
                            </div>

                            {/* Features */}
                            <div className="flex flex-wrap items-center justify-center gap-3">
                                <span className="inline-flex items-center gap-1.5 rounded-full bg-secondary px-4 py-2 text-xs font-medium text-secondary-foreground shadow-sm">
                                    <Activity className="h-3.5 w-3.5" />
                                    Setup in 60 Seconds
                                </span>
                            </div>

                            {/* CTA Button */}
                            <div className="pt-2">
                                <Button 
                                    onClick={handleSetup} 
                                    size="lg" 
                                    className="group h-14 rounded-xl px-12 text-base font-semibold shadow-lg transition-all duration-200 hover:scale-[1.02] hover:shadow-xl"
                                >
                                    <Plus className="mr-2 h-5 w-5 transition-transform group-hover:rotate-90" />
                                    Connect Your SIP Trunk
                                </Button>
                            </div>
                        </div>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-6">
                        {trunks.map((trunk) => (
                            <Card key={trunk.id}>
                                <CardHeader>
                                    <div className="flex items-start justify-between">
                                        <div>
                                            <CardTitle>{trunk.friendly_name}</CardTitle>
                                            <CardDescription className="mt-1 font-mono text-xs">
                                                {trunk.trunk_domain_name}
                                            </CardDescription>
                                        </div>
                                        <div className="flex flex-col items-end gap-2">
                                            {getStatusBadge(trunk.status)}
                                            {getHealthBadge(trunk.health_status)}
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {/* Statistics Grid */}
                                    <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                                        <div className="rounded-lg border bg-card p-4">
                                            <div className="flex items-center gap-2 text-muted-foreground">
                                                <Phone className="h-4 w-4" />
                                                <span className="text-sm">Phone Numbers</span>
                                            </div>
                                            <p className="mt-2 text-2xl font-bold">{trunk.phone_numbers_count}</p>
                                        </div>

                                        <div className="rounded-lg border bg-card p-4">
                                            <div className="flex items-center gap-2 text-muted-foreground">
                                                <Activity className="h-4 w-4" />
                                                <span className="text-sm">Total Calls</span>
                                            </div>
                                            <p className="mt-2 text-2xl font-bold">{trunk.total_calls_count.toLocaleString()}</p>
                                        </div>

                                        <div className="rounded-lg border bg-card p-4">
                                            <div className="flex items-center gap-2 text-muted-foreground">
                                                <Activity className="h-4 w-4" />
                                                <span className="text-sm">Minutes Used</span>
                                            </div>
                                            <p className="mt-2 text-2xl font-bold">
                                                {typeof trunk.total_minutes_used === 'string' 
                                                    ? trunk.total_minutes_used 
                                                    : trunk.total_minutes_used.toFixed(2)}
                                            </p>
                                        </div>

                                        <div className="rounded-lg border bg-card p-4">
                                            <div className="flex items-center gap-2 text-muted-foreground">
                                                <Activity className="h-4 w-4" />
                                                <span className="text-sm">Last Call</span>
                                            </div>
                                            <p className="mt-2 text-sm font-medium">
                                                {trunk.last_call_at ? new Date(trunk.last_call_at).toLocaleDateString() : 'N/A'}
                                            </p>
                                        </div>
                                    </div>

                                    {/* Health Info */}
                                    {trunk.last_health_check_at && (
                                        <div className="text-sm text-muted-foreground">
                                            Last health check: {formatDate(trunk.last_health_check_at)}
                                        </div>
                                    )}

                                    {/* Actions */}
                                    <div className="flex flex-wrap gap-2">
                                        <Button onClick={() => handleView(trunk.id)} size="sm">
                                            View Details
                                        </Button>

                                        <Button
                                            onClick={() => handleSync(trunk.id)}
                                            variant="outline"
                                            size="sm"
                                            disabled={syncing === trunk.id}
                                        >
                                            {syncing === trunk.id ? (
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

                                        <Button
                                            onClick={() => handleHealthCheck(trunk.id)}
                                            variant="outline"
                                            size="sm"
                                            disabled={checking === trunk.id}
                                        >
                                            {checking === trunk.id ? (
                                                <>
                                                    <Activity className="mr-2 h-4 w-4 animate-spin" />
                                                    Checking...
                                                </>
                                            ) : (
                                                <>
                                                    <Activity className="mr-2 h-4 w-4" />
                                                    Health Check
                                                </>
                                            )}
                                        </Button>

                                        <Button
                                            onClick={() => handleDisconnect(trunk.id, trunk.friendly_name)}
                                            variant="destructive"
                                            size="sm"
                                        >
                                            <Trash2 className="mr-2 h-4 w-4" />
                                            Disconnect
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            {/* Disconnect Confirmation Dialog */}
            <AlertDialog open={disconnectDialog.open} onOpenChange={(open) => !open && setDisconnectDialog({ open: false, trunkId: null, trunkName: '' })}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Disconnect SIP Trunk?</AlertDialogTitle>
                        <AlertDialogDescription className="space-y-2">
                            <p>Are you sure you want to disconnect "{disconnectDialog.trunkName}"?</p>
                            <p className="text-sm"><strong>Disconnect:</strong> Temporarily disable the trunk. You can reconnect it later.</p>
                            <p className="text-sm"><strong>Permanently Delete:</strong> Remove from Twilio and delete all data. This cannot be undone.</p>
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter className="flex-col gap-2 sm:flex-row">
                        <AlertDialogCancel onClick={() => setDisconnectDialog({ open: false, trunkId: null, trunkName: '' })}>
                            Cancel
                        </AlertDialogCancel>
                        <AlertDialogAction onClick={() => confirmDisconnect(false)} className="bg-yellow-600 text-white hover:bg-yellow-700">
                            Disconnect
                        </AlertDialogAction>
                        <AlertDialogAction onClick={() => confirmDisconnect(true)} className="bg-destructive text-destructive-foreground hover:bg-destructive/90">
                            Permanently Delete
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}
