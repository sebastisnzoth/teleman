import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import AppLayout from '@/layouts/app-layout';
import Heading from '@/components/heading';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Head, Link, usePage } from '@inertiajs/react';
import { Phone, Users, Megaphone, PhoneCall, UserCheck, PhoneIncoming, Clock, Calendar, BarChart3, AlertTriangle, ShieldCheck, X } from 'lucide-react';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useAuth } from '@/hooks/useAuth';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

interface CustomerStats {
    total_agents: number;
    total_campaigns: number;
    active_campaigns: number;
    total_contacts: number;
    active_contacts: number;
    total_calls: number;
    calls_today: number;
    calls_this_week: number;
    calls_this_month: number;
    my_phone_numbers: number;
    my_pending_requests: number;
}

interface Campaign {
    id: number;
    name: string;
    status: string;
    total_contacts: number;
    contacted: number;
    created_at: string;
}

interface Call {
    id: number;
    to_number: string;
    status: string;
    duration_seconds: number | null;
    created_at: string;
}

interface ChartData {
    date: string;
    label: string;
    total: number;
    completed: number;
    failed: number;
}

interface CampaignsChart {
    draft: number;
    active: number;
    paused: number;
    completed: number;
}

interface AdminStats {
    total_users: number;
    total_customers: number;
    total_agents: number;
    total_campaigns: number;
    active_campaigns: number;
    total_contacts: number;
    total_calls: number;
    total_phone_numbers: number;
    purchased_phone_numbers: number;
    available_phone_numbers: number;
    assigned_phone_numbers: number;
    pending_requests: number;
    total_phone_cost: string;
    credits_sold: string;
    credits_used: string;
}

interface AgentStats {
    available_campaigns: number;
    available_contacts: number;
    calls_today: number;
    calls_this_week: number;
    total_calls: number;
}

interface Props {
    stats: CustomerStats | AdminStats | AgentStats;
    recentCampaigns: Campaign[];
    recentCalls: Call[];
    callsChart: ChartData[];
    campaignsChart: CampaignsChart;
}

export default function Index({ stats, recentCampaigns = [], recentCalls = [], callsChart = [], campaignsChart = { draft: 0, active: 0, paused: 0, completed: 0 } }: Props) {
    const { t } = useTranslation();
    const { auth } = usePage().props as any;
    const user = auth?.user;
    const kycData = auth?.kyc;

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Home',
            href: dashboard().url,
        },
    ];

    const getStatusBadge = (status: string) => {
        const variants: Record<string, "default" | "secondary" | "destructive" | "outline"> = {
            'active': 'default',
            'paused': 'secondary',
            'completed': 'outline',
            'draft': 'secondary',
        };
        return <Badge variant={variants[status] || 'outline'}>{status}</Badge>;
    };

    const getCallStatusBadge = (status: string) => {
        const variants: Record<string, "default" | "secondary" | "destructive"> = {
            'completed': 'default',
            'failed': 'destructive',
            'busy': 'destructive',
            'no-answer': 'destructive',
            'in-progress': 'secondary',
        };
        return <Badge variant={variants[status] || 'secondary'}>{status}</Badge>;
    };

    const formatDuration = (seconds: number | null) => {
        if (!seconds) return '0s';
        if (seconds < 60) return `${seconds}s`;
        const minutes = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${minutes}m ${secs}s`;
    };

    const getGreeting = () => {
        const hour = new Date().getHours();
        if (hour < 12) return t('dashboard.good_morning');
        if (hour < 18) return t('dashboard.good_afternoon');
        return t('dashboard.good_evening');
    };

    const getWelcomeMessage = (roleName: string) => {
        const messages: Record<string, string> = {
            'admin': t('dashboard.overview_description'),
            'customer': t('dashboard.ready_campaign'),
            'agent': t('dashboard.great_calls'),
        };
        return messages[roleName] || 'Welcome back!';
    };

    // KYC Warning Banner Component
    const KycWarningBanner = () => {
        const [isDismissed, setIsDismissed] = useState(false);
        const { kycEnabled } = usePage().props as any;

        // Don't show banner if KYC is disabled globally
        if (!kycEnabled) return null;

        // Check if banner was dismissed in this session
        useEffect(() => {
            const dismissed = sessionStorage.getItem('kyc-banner-dismissed');
            if (dismissed === 'true') {
                setIsDismissed(true);
            }
        }, []);

        if (!kycData || !kycData.is_unverified || isDismissed) return null;

        const isExpired = kycData.is_grace_period_expired;
        const daysRemaining = Math.ceil(kycData.days_remaining);

        const handleDismiss = () => {
            sessionStorage.setItem('kyc-banner-dismissed', 'true');
            setIsDismissed(true);
        };

        return (
            <Alert variant={isExpired ? "destructive" : "default"} className={`relative ${isExpired ? "" : "border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900/50 dark:bg-amber-900/20 dark:text-amber-400"}`}>
                {!isExpired && (
                    <button
                        onClick={handleDismiss}
                        className="absolute right-4 top-4 rounded-sm opacity-70 ring-offset-background transition-opacity hover:opacity-100 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:pointer-events-none"
                        aria-label="Close"
                    >
                        <X className="h-4 w-4" />
                    </button>
                )}
                <AlertTriangle className="h-4 w-4" />
                <AlertTitle className="font-semibold pr-8">
                    {isExpired ? t('dashboard.account_suspended') : t('dashboard.verify_your_account')}
                </AlertTitle>
                <AlertDescription className="mt-2">
                    {isExpired ? (
                        <>
                            <p className="mb-3">
                                {t('dashboard.grace_expired')}
                            </p>
                            <Button asChild size="sm" variant="destructive">
                                <Link href="/settings/kyc">
                                    <ShieldCheck className="mr-2 h-4 w-4" />
                                    {t('dashboard.verify_now')}
                                </Link>
                            </Button>
                        </>
                    ) : (
                        <>
                            <p className="mb-3">
                                {t('dashboard.you_have')} <span className="font-bold">{daysRemaining} {t('dashboard.days')} {t('dashboard.remaining')}</span> {t('dashboard.grace_period')}.
                                {t('dashboard.complete_kyc')}
                            </p>
                            <Button asChild size="sm" className="bg-amber-600 hover:bg-amber-700 dark:bg-amber-700 dark:hover:bg-amber-800">
                                <Link href="/settings/kyc">
                                    <ShieldCheck className="mr-2 h-4 w-4" />
                                    {t('dashboard.verify_account')} ({daysRemaining} {t('dashboard.days')} {t('dashboard.left')})
                                </Link>
                            </Button>
                        </>
                    )}
                </AlertDescription>
            </Alert>
        );
    };

    const { isAdmin, isCustomer, isAgent } = useAuth();

    // Admin Dashboard
    if (isAdmin()) {
        const adminStats = stats as AdminStats;

        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title={t('dashboard.title')} />

                <div className="flex h-full flex-1 flex-col gap-4 p-4 md:gap-8 md:p-8">
                    <div className="flex items-center justify-between">
                        <Heading
                            title={`${getGreeting()}, ${user?.name || 'Admin'}! 👋`}
                            description={getWelcomeMessage('admin')}
                        />
                    </div>

                    {/* System Overview */}
                    <div>
                        <h3 className="text-lg font-semibold mb-3">{t('dashboard.system_overview')}</h3>
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">{t('dashboard.total_users')}</CardTitle>
                                    <Users className="h-4 w-4 text-muted-foreground" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">{adminStats.total_users}</div>
                                    <p className="text-xs text-muted-foreground">
                                        {adminStats.total_customers} {t('dashboard.customers_agents').replace('{agents}', String(adminStats.total_agents))}
                                    </p>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">{t('common.campaigns')}</CardTitle>
                                    <Megaphone className="h-4 w-4 text-muted-foreground" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">{adminStats.total_campaigns}</div>
                                    <p className="text-xs text-muted-foreground">
                                        {adminStats.active_campaigns} {t('dashboard.active_campaigns')}
                                    </p>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">{t('common.contacts')}</CardTitle>
                                    <UserCheck className="h-4 w-4 text-muted-foreground" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">{adminStats.total_contacts.toLocaleString()}</div>
                                    <p className="text-xs text-muted-foreground">{t('dashboard.total_contacts')}</p>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">{t('dashboard.total_calls')}</CardTitle>
                                    <PhoneCall className="h-4 w-4 text-muted-foreground" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">{adminStats.total_calls.toLocaleString()}</div>
                                    <p className="text-xs text-muted-foreground">{t('dashboard.all_time_calls')}</p>
                                </CardContent>
                            </Card>
                        </div>
                    </div>

                    {/* Phone Number Inventory */}
                    <div>
                        <h3 className="text-lg font-semibold mb-3">{t('dashboard.phone_inventory')}</h3>
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">{t('dashboard.total_numbers')}</CardTitle>
                                    <Phone className="h-4 w-4 text-muted-foreground" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">{adminStats.total_phone_numbers}</div>
                                    <p className="text-xs text-muted-foreground">{t('dashboard.in_system')}</p>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">{t('dashboard.purchased')}</CardTitle>
                                    <Phone className="h-4 w-4 text-muted-foreground" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">{adminStats.purchased_phone_numbers}</div>
                                    <p className="text-xs text-muted-foreground">{t('dashboard.from_twilio')}</p>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">{t('dashboard.available')}</CardTitle>
                                    <Phone className="h-4 w-4 text-green-600" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">{adminStats.available_phone_numbers}</div>
                                    <p className="text-xs text-muted-foreground">{t('dashboard.ready_to_assign')}</p>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">Assigned</CardTitle>
                                    <PhoneIncoming className="h-4 w-4 text-blue-600" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">{adminStats.assigned_phone_numbers}</div>
                                    <p className="text-xs text-muted-foreground">{t('dashboard.in_use')}</p>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">{t('dashboard.pending_requests')}</CardTitle>
                                    <Clock className="h-4 w-4 text-orange-600" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">{adminStats.pending_requests}</div>
                                    <p className="text-xs text-muted-foreground">{t('dashboard.awaiting_assignment')}</p>
                                </CardContent>
                            </Card>
                        </div>
                    </div>

                    {/* Financial Overview */}
                    <div>
                        <h3 className="text-lg font-semibold mb-3">{t('dashboard.financial_overview')}</h3>
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">{t('dashboard.phone_cost')}</CardTitle>
                                    <Phone className="h-4 w-4 text-muted-foreground" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">${adminStats.total_phone_cost}</div>
                                    <p className="text-xs text-muted-foreground">{t('dashboard.monthly_phone_cost')}</p>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">{t('dashboard.credits_sold')}</CardTitle>
                                    <BarChart3 className="h-4 w-4 text-green-600" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">${adminStats.credits_sold}</div>
                                    <p className="text-xs text-muted-foreground">{t('dashboard.total_credits_purchased')}</p>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">{t('dashboard.credits_used')}</CardTitle>
                                    <BarChart3 className="h-4 w-4 text-blue-600" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">${adminStats.credits_used}</div>
                                    <p className="text-xs text-muted-foreground">{t('dashboard.total_credits_consumed')}</p>
                                </CardContent>
                            </Card>
                        </div>
                    </div>

                    {/* Charts */}
                    <div className="grid gap-4 md:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>{t('dashboard.call_trends')}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="flex items-end justify-between h-64 gap-2">
                                    {callsChart.map((day, i) => {
                                        const maxCalls = Math.max(...callsChart.map(d => d.total), 1);
                                        const height = (day.total / maxCalls) * 100;

                                        return (
                                            <div key={i} className="flex-1 flex flex-col items-center gap-2">
                                                <div className="w-full flex flex-col gap-1" style={{ height: `${height}%` }}>
                                                    <div
                                                        className="w-full bg-green-500 rounded-t"
                                                        style={{ height: `${day.total > 0 ? (day.completed / day.total) * 100 : 0}%` }}
                                                    />
                                                    <div
                                                        className="w-full bg-red-500 rounded-b"
                                                        style={{ height: `${day.total > 0 ? (day.failed / day.total) * 100 : 0}%` }}
                                                    />
                                                </div>
                                                <span className="text-xs text-muted-foreground">{day.label}</span>
                                            </div>
                                        );
                                    })}
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>{t('dashboard.campaign_status')}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-4">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <div className="h-3 w-3 rounded-full bg-green-500" />
                                            <span className="text-sm">{t('common.active')}</span>
                                        </div>
                                        <span className="font-semibold">{campaignsChart.active}</span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <div className="h-3 w-3 rounded-full bg-gray-400" />
                                            <span className="text-sm">{t('common.draft')}</span>
                                        </div>
                                        <span className="font-semibold">{campaignsChart.draft}</span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <div className="h-3 w-3 rounded-full bg-yellow-500" />
                                            <span className="text-sm">{t('common.paused')}</span>
                                        </div>
                                        <span className="font-semibold">{campaignsChart.paused}</span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <div className="h-3 w-3 rounded-full bg-blue-500" />
                                            <span className="text-sm">{t('common.completed')}</span>
                                        </div>
                                        <span className="font-semibold">{campaignsChart.completed}</span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Recent Activity */}
                    <div className="grid gap-4 md:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>{t('dashboard.recent_campaigns')}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-4">
                                    {recentCampaigns.slice(0, 5).map((campaign) => (
                                        <div key={campaign.id} className="flex items-center justify-between">
                                            <div>
                                                <p className="text-sm font-medium">{campaign.name}</p>
                                                <p className="text-xs text-muted-foreground">
                                                    {campaign.contacted} / {campaign.total_contacts} contacted
                                                </p>
                                            </div>
                                            {getStatusBadge(campaign.status)}
                                        </div>
                                    ))}
                                    {!recentCampaigns?.length && (
                                        <p className="text-sm text-muted-foreground text-center py-8">{t('dashboard.no_campaigns')}</p>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>{t('dashboard.recent_calls')}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-4">
                                    {recentCalls.slice(0, 5).map((call) => (
                                        <div key={call.id} className="flex items-center justify-between">
                                            <div>
                                                <p className="text-sm font-medium">{call.to_number}</p>
                                                <p className="text-xs text-muted-foreground">
                                                    {formatDuration(call.duration_seconds)} • {new Date(call.created_at).toLocaleDateString()}
                                                </p>
                                            </div>
                                            {getCallStatusBadge(call.status)}
                                        </div>
                                    ))}
                                    {!recentCalls?.length && (
                                        <p className="text-sm text-muted-foreground text-center py-8">{t('dashboard.no_calls')}</p>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </AppLayout>
        );
    }

    // Agent Dashboard
    if (isAgent()) {
        const agentStats = stats as AgentStats;

        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title={t('dashboard.title')} />

                <div className="flex h-full flex-1 flex-col gap-4 p-4 md:gap-8 md:p-8">
                    <div className="flex items-center justify-between">
                        <Heading
                            title={`${getGreeting()}, ${user?.name || 'Agent'}! 👋`}
                            description={getWelcomeMessage('agent')}
                        />
                    </div>

                    {/* KYC Warning Banner */}
                    <KycWarningBanner />

                    {/* Available Work */}
                    <div>
                        <h3 className="text-lg font-semibold mb-3">{t('dashboard.available_work')}</h3>
                        <div className="grid gap-4 md:grid-cols-2">
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">{t('dashboard.available_campaigns')}</CardTitle>
                                    <Megaphone className="h-4 w-4 text-muted-foreground" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">{agentStats.available_campaigns}</div>
                                    <p className="text-xs text-muted-foreground">{t('dashboard.campaigns_you_can_work')}</p>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">{t('dashboard.available_contacts')}</CardTitle>
                                    <UserCheck className="h-4 w-4 text-muted-foreground" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">{agentStats.available_contacts?.toLocaleString()}</div>
                                    <p className="text-xs text-muted-foreground">{t('dashboard.contacts_ready')}</p>
                                </CardContent>
                            </Card>
                        </div>
                    </div>

                    {/* Call Performance */}
                    <div>
                        <h3 className="text-lg font-semibold mb-3">{t('dashboard.your_performance')}</h3>
                        <div className="grid gap-4 md:grid-cols-3">
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">{t('dashboard.todays_calls')}</CardTitle>
                                    <PhoneCall className="h-4 w-4 text-muted-foreground" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">{agentStats.calls_today}</div>
                                    <p className="text-xs text-muted-foreground">{t('dashboard.calls_made_today')}</p>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">{t('dashboard.this_week')}</CardTitle>
                                    <Calendar className="h-4 w-4 text-muted-foreground" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">{agentStats.calls_this_week}</div>
                                    <p className="text-xs text-muted-foreground">{t('dashboard.calls_this_week')}</p>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">{t('dashboard.total_calls')}</CardTitle>
                                    <PhoneCall className="h-4 w-4 text-muted-foreground" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">{agentStats.total_calls}</div>
                                    <p className="text-xs text-muted-foreground">{t('dashboard.all_time_calls')}</p>
                                </CardContent>
                            </Card>
                        </div>
                    </div>

                    {/* Quick Actions */}
                    <div>
                        <h3 className="text-lg font-semibold mb-3">{t('dashboard.quick_actions')}</h3>
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            <Card className="hover:shadow-md transition-shadow cursor-pointer">
                                <CardContent className="pt-6">
                                    <Link href="/campaigns" className="flex items-center gap-3">
                                        <Megaphone className="h-8 w-8 text-primary" />
                                        <div>
                                            <p className="font-semibold">{t('dashboard.view_campaigns')}</p>
                                            <p className="text-xs text-muted-foreground">{t('dashboard.browse_campaigns')}</p>
                                        </div>
                                    </Link>
                                </CardContent>
                            </Card>

                            <Card className="hover:shadow-md transition-shadow cursor-pointer">
                                <CardContent className="pt-6">
                                    <Link href="/calls" className="flex items-center gap-3">
                                        <PhoneCall className="h-8 w-8 text-primary" />
                                        <div>
                                            <p className="font-semibold">{t('dashboard.make_calls')}</p>
                                            <p className="text-xs text-muted-foreground">{t('dashboard.start_calling')}</p>
                                        </div>
                                    </Link>
                                </CardContent>
                            </Card>

                            <Card className="hover:shadow-md transition-shadow cursor-pointer">
                                <CardContent className="pt-6">
                                    <Link href="/calls/history" className="flex items-center gap-3">
                                        <Clock className="h-8 w-8 text-primary" />
                                        <div>
                                            <p className="font-semibold">{t('nav.call_history')}</p>
                                            <p className="text-xs text-muted-foreground">{t('dashboard.view_call_logs')}</p>
                                        </div>
                                    </Link>
                                </CardContent>
                            </Card>
                        </div>
                    </div>

                    {/* Recent Activity */}
                    <div className="grid gap-4 md:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>{t('dashboard.available_campaigns')}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-4">
                                    {recentCampaigns.slice(0, 5).map((campaign) => (
                                        <div key={campaign.id} className="flex items-center justify-between">
                                            <div>
                                                <p className="text-sm font-medium">{campaign.name}</p>
                                                <p className="text-xs text-muted-foreground">
                                                    {campaign.total_contacts - campaign.contacted} {t('dashboard.contacts_remaining')}
                                                </p>
                                            </div>
                                            <Button size="sm" asChild>
                                                <Link href={`/campaigns/${campaign.id}`}>{t('common.view')}</Link>
                                            </Button>
                                        </div>
                                    ))}
                                    {!recentCampaigns?.length && (
                                        <p className="text-sm text-muted-foreground text-center py-8">{t('dashboard.no_campaigns_available')}</p>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>{t('dashboard.your_recent_calls')}</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-4">
                                    {recentCalls.slice(0, 5).map((call) => (
                                        <div key={call.id} className="flex items-center justify-between">
                                            <div>
                                                <p className="text-sm font-medium">{call.to_number}</p>
                                                <p className="text-xs text-muted-foreground">
                                                    {formatDuration(call.duration_seconds)} • {new Date(call.created_at).toLocaleDateString()}
                                                </p>
                                            </div>
                                            {getCallStatusBadge(call.status)}
                                        </div>
                                    ))}
                                    {!recentCalls?.length && (
                                        <p className="text-sm text-muted-foreground text-center py-8">{t('dashboard.no_calls')}</p>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </AppLayout>
        );
    }

    // Customer Dashboard (default)
    const customerStats = stats as CustomerStats;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('dashboard.title')} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4 md:gap-8 md:p-8">
                <div className="flex items-center justify-between">
                    <Heading
                        title={`${getGreeting()}, ${user?.name || 'there'}! 👋`}
                        description={getWelcomeMessage('customer')}
                    />
                    <Button asChild>
                        <Link href="/campaigns/create">
                            <Megaphone className="mr-2 h-4 w-4" />
                            Create Campaign
                        </Link>
                    </Button>
                </div>

                {/* KYC Warning Banner */}
                <KycWarningBanner />

                {/* Quick Stats */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">{t('common.campaigns')}</CardTitle>
                            <Megaphone className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{customerStats.total_campaigns}</div>
                            <p className="text-xs text-muted-foreground">
                                {customerStats.active_campaigns} {t('dashboard.active_campaigns')}
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">{t('common.contacts')}</CardTitle>
                            <Users className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{customerStats.total_contacts?.toLocaleString()}</div>
                            <p className="text-xs text-muted-foreground">
                                {customerStats.active_contacts} {t('dashboard.active_campaigns')}
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">{t('dashboard.total_calls')}</CardTitle>
                            <Phone className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{customerStats.total_calls?.toLocaleString()}</div>
                            <p className="text-xs text-muted-foreground">
                                {customerStats.calls_today} {t('dashboard.today')}
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">{t('dashboard.my_team')}</CardTitle>
                            <UserCheck className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{customerStats.total_agents}</div>
                            <p className="text-xs text-muted-foreground">{t('dashboard.active_agents')}</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Call Activity */}
                <div>
                    <h3 className="text-lg font-semibold mb-4">{t('dashboard.call_activity')}</h3>
                    <div className="grid gap-4 md:grid-cols-3">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">{t('dashboard.today')}</CardTitle>
                                <PhoneCall className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{customerStats.calls_today}</div>
                                <p className="text-xs text-muted-foreground">{t('dashboard.calls_made')}</p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">{t('dashboard.this_week')}</CardTitle>
                                <Calendar className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{customerStats.calls_this_week}</div>
                                <p className="text-xs text-muted-foreground">{t('dashboard.calls_made')}</p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">{t('dashboard.this_month')}</CardTitle>
                                <BarChart3 className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{customerStats.calls_this_month}</div>
                                <p className="text-xs text-muted-foreground">{t('dashboard.calls_made')}</p>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                {/* Phone Numbers */}
                <div>
                    <h3 className="text-lg font-semibold mb-4">{t('nav.phone_numbers')}</h3>
                    <div className="grid gap-4 md:grid-cols-2">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">{t('dashboard.my_numbers')}</CardTitle>
                                <PhoneIncoming className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{customerStats.my_phone_numbers}</div>
                                <p className="text-xs text-muted-foreground">
                                    <Link href="/numbers/my-numbers" className="text-primary hover:underline">
                                        {t('dashboard.view_all_numbers')}
                                    </Link>
                                </p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">{t('dashboard.pending_requests')}</CardTitle>
                                <Clock className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{customerStats.my_pending_requests}</div>
                                <p className="text-xs text-muted-foreground">
                                    <Link href="/numbers/available" className="text-primary hover:underline">
                                        {t('dashboard.browse_available')}
                                    </Link>
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                {/* Charts */}
                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('dashboard.call_trends')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="h-[200px] flex items-end justify-between gap-2">
                                {callsChart?.map((day) => (
                                    <div key={day.date} className="flex flex-col items-center flex-1">
                                        <div className="flex flex-col items-center justify-end w-full space-y-1" style={{ height: '180px' }}>
                                            <div
                                                className="w-full bg-green-500 rounded-t"
                                                style={{ height: `${(day.completed / Math.max(...callsChart.map(d => d.total), 1)) * 100}%` }}
                                                title={`Completed: ${day.completed}`}
                                            />
                                            <div
                                                className="w-full bg-red-500"
                                                style={{ height: `${(day.failed / Math.max(...callsChart.map(d => d.total), 1)) * 100}%` }}
                                                title={`Failed: ${day.failed}`}
                                            />
                                        </div>
                                        <p className="text-xs text-muted-foreground mt-2">{day.label}</p>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{t('dashboard.campaign_status')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <div className="h-3 w-3 rounded-full bg-green-500" />
                                        <span className="text-sm">{t('common.active')}</span>
                                    </div>
                                    <span className="text-sm font-bold">{campaignsChart?.active || 0}</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <div className="h-3 w-3 rounded-full bg-gray-500" />
                                        <span className="text-sm">{t('common.draft')}</span>
                                    </div>
                                    <span className="text-sm font-bold">{campaignsChart?.draft || 0}</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <div className="h-3 w-3 rounded-full bg-yellow-500" />
                                        <span className="text-sm">{t('common.paused')}</span>
                                    </div>
                                    <span className="text-sm font-bold">{campaignsChart?.paused || 0}</span>
                                </div>
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <div className="h-3 w-3 rounded-full bg-blue-500" />
                                        <span className="text-sm">{t('common.completed')}</span>
                                    </div>
                                    <span className="text-sm font-bold">{campaignsChart?.completed || 0}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Recent Activity */}
                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <CardTitle>{t('dashboard.recent_campaigns')}</CardTitle>
                                <Button variant="outline" size="sm" asChild>
                                    <Link href="/campaigns">View All</Link>
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                {recentCampaigns?.slice(0, 5).map((campaign) => (
                                    <div key={campaign.id} className="flex items-center justify-between">
                                        <div className="space-y-1">
                                            <p className="text-sm font-medium">{campaign.name}</p>
                                            <p className="text-xs text-muted-foreground">
                                                {campaign.contacted}/{campaign.total_contacts} contacted
                                            </p>
                                        </div>
                                        {getStatusBadge(campaign.status)}
                                    </div>
                                ))}
                                {!recentCampaigns?.length && (
                                    <p className="text-sm text-muted-foreground text-center py-8">{t('dashboard.no_campaigns')}</p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <CardTitle>{t('dashboard.recent_calls')}</CardTitle>
                                <Button variant="outline" size="sm" asChild>
                                    <Link href="/calls">View All</Link>
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                {recentCalls?.slice(0, 5).map((call) => (
                                    <div key={call.id} className="flex items-center justify-between">
                                        <div className="space-y-1">
                                            <p className="text-sm font-medium">{call.to_number}</p>
                                            <p className="text-xs text-muted-foreground">
                                                {formatDuration(call.duration_seconds)}
                                            </p>
                                        </div>
                                        {getCallStatusBadge(call.status)}
                                    </div>
                                ))}
                                {!recentCalls?.length && (
                                    <p className="text-sm text-muted-foreground text-center py-8">{t('dashboard.no_calls')}</p>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
