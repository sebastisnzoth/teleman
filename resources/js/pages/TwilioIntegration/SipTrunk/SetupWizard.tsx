import AlertError from '@/components/alert-error';
import { useTranslation } from 'react-i18next';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { PageHelp } from '@/components/page-help';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Check, Loader2, Phone, Shield, Network, Settings, AlertCircle } from 'lucide-react';
import { FormEventHandler, useState, useEffect, useRef } from 'react';

interface SetupWizardProps {
    user: {
        id: number;
        name: string;
        email: string;
    };
    twilioConfigured: boolean;
    twilioAccountSid: string | null;
}

interface TrunkDetails {
    id: number;
    friendly_name: string;
    trunk_domain_name: string;
    phone_numbers_count: number;
}

const SETUP_STEP_LABELS: Record<string, string> = {
    credentials: 'Validating Credentials...',
    creating_trunk: 'Creating SIP Trunk...',
    configuring_origination: 'Configuring Inbound Routing...',
    configuring_termination: 'Configuring Outbound Authentication...',
    importing_numbers: 'Importing Phone Numbers...',
    testing: 'Testing Connection...',
    completed: 'Setup Complete!',
};

const helpSections = [
    {
        title: 'What is SIP Trunk Setup?',
        content: 'This wizard connects your Twilio account to Teleman using SIP Trunking technology. It automatically configures your trunk, imports phone numbers, sets up webhooks, and runs health tests - all in about 60 seconds.',
    },
    {
        title: 'What You Need',
        content: `• Twilio Account SID (starts with "AC"): Find it in your Twilio Console dashboard
• Auth Token: Located in your Twilio Console under Account Info
• Active Twilio account with phone numbers
• The setup is completely automatic - just provide credentials and we handle the rest`,
    },
    {
        title: 'Setup Process',
        content: `1. Validate Credentials: We verify your Twilio Account SID and Auth Token
2. Create Trunk: Your SIP trunk is created in Twilio with optimal settings
3. Configure Routing: Inbound and outbound call routing is set up
4. Import Numbers: All your Twilio phone numbers are automatically imported
5. Health Tests: Comprehensive tests ensure everything works perfectly`,
    },
    {
        title: 'Benefits of BYOT (Bring Your Own Trunk)',
        content: `• Cost Savings: Save 35% on per-minute calling rates compared to standard Twilio pricing
• No Per-Number Fees: Eliminate monthly charges for each phone number
• Full Control: Keep complete ownership of your Twilio account and numbers
• Universal Usage: Use for softphone calls, campaigns, and AI agents
• Real-time Monitoring: Built-in health checks and call statistics`,
    },
    {
        title: 'Troubleshooting',
        content: `Common Issues:
• Invalid Credentials: Double-check your Account SID starts with "AC" and Auth Token is correct
• API Access: Ensure your Twilio account has API access enabled
• Number Compatibility: Voice-enabled numbers are required (SMS-only numbers won't work)
• Stuck Setup: If setup hangs over 2 minutes, click "Start Over" and try again
• Connection Failed: Check your internet connection and Twilio account status`,
    },
];

export default function SetupWizard({ user, twilioConfigured, twilioAccountSid }: SetupWizardProps) {
  const { t } = useTranslation();
  const breadcrumbs: BreadcrumbItem[] = [
    { title: t('common.dashboard'), href: dashboard().url },
    { title: t('twilio.twilio_integration'), href: '/settings/twilio' },
    { title: t('twilio.sip_trunk_setup'), href: '/sip-trunk/setup' },
];
    const [step, setStep] = useState<'credentials' | 'setup' | 'success'>('credentials');
    const [setupProgress, setSetupProgress] = useState(0);
    const [setupStep, setSetupStep] = useState('');
    const [setupError, setSetupError] = useState<string | null>(null);
    const [trunkId, setTrunkId] = useState<number | null>(null);
    const [trunkDetails, setTrunkDetails] = useState<TrunkDetails | null>(null);
    const pollingRef = useRef<ReturnType<typeof setInterval> | null>(null);
    const [routesToLocalPbx, setRoutesToLocalPbx] = useState(false);

    const { data, setData, processing, errors } = useForm({
        friendly_name: `${user.name}'s SIP Trunk`,
        routes_to_local_pbx: false,
        local_pbx_ip: '',
        local_pbx_port: '5060',
        local_pbx_transport: 'udp',
        local_pbx_username: '',
        local_pbx_password: '',
    });

    // Cleanup polling on unmount
    useEffect(() => {
        return () => {
            if (pollingRef.current) {
                clearInterval(pollingRef.current);
            }
        };
    }, []);

    // Poll setup status when in setup step
    useEffect(() => {
        if (step !== 'setup' || !trunkId) return;

        const poll = async () => {
            try {
                const response = await window.axios.get(`/sip-trunk/${trunkId}/setup-status`);
                const data = response.data;

                setSetupProgress(data.setup_progress || 0);
                setSetupStep(data.setup_step || '');

                if (data.is_setup_complete) {
                    // Setup finished successfully
                    if (pollingRef.current) {
                        clearInterval(pollingRef.current);
                        pollingRef.current = null;
                    }
                    setTrunkDetails(data.trunk || null);
                    // Brief delay so user sees 100%
                    setTimeout(() => setStep('success'), 800);
                }

                if (data.is_failed) {
                    // Setup failed
                    if (pollingRef.current) {
                        clearInterval(pollingRef.current);
                        pollingRef.current = null;
                    }
                    setSetupError(data.setup_error || 'Setup failed. Please try again.');
                }
            } catch (error) {
                console.error('Failed to fetch setup status:', error);
            }
        };

        // Poll immediately, then every 2 seconds
        poll();
        pollingRef.current = setInterval(poll, 2000);

        return () => {
            if (pollingRef.current) {
                clearInterval(pollingRef.current);
                pollingRef.current = null;
            }
        };
    }, [step, trunkId]);

    const submit: FormEventHandler = async (e) => {
        e.preventDefault();
        setSetupError(null);

        try {
            const response = await window.axios.post('/sip-trunk/setup', data);

            if (response.data.success && response.data.trunk_id) {
                // Got trunk ID — move to setup step immediately
                setTrunkId(response.data.trunk_id);
                setStep('setup');
            } else {
                setSetupError(response.data.message || 'Failed to start SIP trunk setup.');
            }
        } catch (error: any) {
            setSetupError(
                error.response?.data?.message ||
                'Failed to start SIP trunk setup. Please check your credentials and try again.'
            );
        }
    };

    const handleStartOver = async () => {
        // If there's a failed trunk, delete it first
        if (trunkId) {
            try {
                await window.axios.delete(`/sip-trunk/${trunkId}?permanent=true`);
            } catch (error) {
                // Continue even if deletion fails
            }
        }

        setStep('credentials');
        setSetupProgress(0);
        setSetupStep('');
        setSetupError(null);
        setTrunkId(null);
        setTrunkDetails(null);
    };

    const handleViewDashboard = () => {
        router.visit('/sip-trunk');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('twilio.sip_trunk_setup')} />

            <div className="mx-auto max-w-3xl space-y-6 p-4 md:p-8">
                <div className="flex items-center justify-between">
                    <Heading title={t('twilio.sip_trunk_setup_wizard')} />
                    <PageHelp
                        title={t('twilio.sip_trunk_setup_help')}
                        sections={helpSections}
                        documentationUrl="/docs/documentation.html#sip-trunk"
                    />
                </div>

                {/* Step Indicators */}
                <div className="flex items-center justify-center space-x-4">
                    <div className="flex flex-col items-center">
                        <div
                            className={`flex h-12 w-12 items-center justify-center rounded-full ${
                                step === 'credentials'
                                    ? 'bg-primary text-primary-foreground'
                                    : step === 'setup' || step === 'success'
                                      ? 'bg-green-500 text-white'
                                      : 'bg-muted text-muted-foreground'
                            }`}
                        >
                            {step === 'setup' || step === 'success' ? (
                                <Check className="h-6 w-6" />
                            ) : (
                                <Shield className="h-6 w-6" />
                            )}
                        </div>
                        <span className="mt-2 text-sm font-medium">Credentials</span>
                    </div>

                    <div className="h-px w-16 bg-muted" />

                    <div className="flex flex-col items-center">
                        <div
                            className={`flex h-12 w-12 items-center justify-center rounded-full ${
                                step === 'setup'
                                    ? 'bg-primary text-primary-foreground'
                                    : step === 'success'
                                      ? 'bg-green-500 text-white'
                                      : 'bg-muted text-muted-foreground'
                            }`}
                        >
                            {step === 'success' ? (
                                <Check className="h-6 w-6" />
                            ) : (
                                <Network className="h-6 w-6" />
                            )}
                        </div>
                        <span className="mt-2 text-sm font-medium">Setup</span>
                    </div>

                    <div className="h-px w-16 bg-muted" />

                    <div className="flex flex-col items-center">
                        <div
                            className={`flex h-12 w-12 items-center justify-center rounded-full ${
                                step === 'success'
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-muted text-muted-foreground'
                            }`}
                        >
                            <Check className="h-6 w-6" />
                        </div>
                        <span className="mt-2 text-sm font-medium">Complete</span>
                    </div>
                </div>

                {/* Step 1: Credentials */}
                {step === 'credentials' && (
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('twilio.connect_your_twilio_sip_trunk')}</CardTitle>
                            <CardDescription>
                                We'll automatically configure your SIP trunk, import your phone numbers, and test the connection.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {!twilioConfigured ? (
                                <div className="relative overflow-hidden rounded-xl border bg-muted/50 p-8 shadow-sm">
                                    <div className="absolute -right-16 -top-16 h-48 w-48 rounded-full bg-muted/30 blur-3xl" />
                                    <div className="absolute -bottom-12 -left-12 h-40 w-40 rounded-full bg-muted/30 blur-3xl" />

                                    <div className="relative space-y-6">
                                        <div className="flex items-start gap-4">
                                            <div className="flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-2xl bg-primary shadow-lg">
                                                <Settings className="h-7 w-7 text-primary-foreground" />
                                            </div>
                                            <div className="flex-1 space-y-1">
                                                <h3 className="text-xl font-bold">
                                                    Twilio Configuration Required
                                                </h3>
                                                <p className="text-sm text-muted-foreground">
                                                    Connect your Twilio account to get started
                                                </p>
                                            </div>
                                        </div>

                                        <div className="rounded-lg border bg-card p-4 shadow-sm">
                                            <p className="text-sm leading-relaxed text-muted-foreground">
                                                Before you can set up your SIP Trunk, you need to configure your Twilio account credentials.
                                                This is a quick one-time setup that takes less than a minute.
                                            </p>
                                        </div>

                                        <Link
                                            href="/settings/twilio"
                                            className="group inline-flex w-full items-center justify-center gap-3 rounded-xl bg-primary px-8 py-4 text-base font-semibold text-primary-foreground shadow-lg transition-all duration-200 hover:scale-[1.02] hover:bg-primary/90 hover:shadow-xl focus:outline-none focus:ring-4 focus:ring-ring active:scale-[0.98]"
                                        >
                                            <Settings className="h-5 w-5 transition-transform group-hover:rotate-90" />
                                            Configure Twilio Settings
                                        </Link>

                                        <div className="flex flex-wrap gap-2">
                                            <span className="inline-flex items-center gap-1.5 rounded-full bg-secondary px-3 py-1.5 text-xs font-medium text-secondary-foreground shadow-sm">
                                                <Check className="h-3.5 w-3.5" />
                                                One-time setup
                                            </span>
                                            <span className="inline-flex items-center gap-1.5 rounded-full bg-secondary px-3 py-1.5 text-xs font-medium text-secondary-foreground shadow-sm">
                                                <Check className="h-3.5 w-3.5" />
                                                Takes less than 1 minute
                                            </span>
                                            <span className="inline-flex items-center gap-1.5 rounded-full bg-secondary px-3 py-1.5 text-xs font-medium text-secondary-foreground shadow-sm">
                                                <Check className="h-3.5 w-3.5" />
                                                Secure connection
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            ) : (
                                <form onSubmit={submit} className="space-y-6">
                                    {setupError && (
                                        <Alert variant="destructive">
                                            <AlertCircle className="h-4 w-4" />
                                            <AlertDescription>{setupError}</AlertDescription>
                                        </Alert>
                                    )}

                                    <Alert className="border-l-4 border-primary bg-muted/50">
                                        <Check className="h-4 w-4" />
                                        <AlertTitle>Ready to Configure SIP Trunk</AlertTitle>
                                        <AlertDescription className="text-sm text-muted-foreground leading-relaxed">
                                            Your Twilio account{' '}
                                            <code className="rounded bg-muted px-1.5 py-0.5 text-xs font-mono">
                                                {twilioAccountSid}
                                            </code>{' '}
                                            is connected. Complete the form below to set up your SIP Trunk.
                                        </AlertDescription>
                                    </Alert>

                                    <div className="space-y-2">
                                        <Label htmlFor="friendly_name">Friendly Name (Optional)</Label>
                                        <Input
                                            id="friendly_name"
                                            type="text"
                                            value={data.friendly_name}
                                            onChange={(e) => setData('friendly_name', e.target.value)}
                                            placeholder="e.g., Production SIP Trunk"
                                            disabled={processing}
                                        />
                                        {errors.friendly_name && (
                                            <p className="text-sm text-destructive">{errors.friendly_name}</p>
                                        )}
                                    </div>

                                    {/* Local PBX Routing Toggle */}
                                    <div className="space-y-4 rounded-lg border bg-muted/30 p-4">
                                        <div className="flex items-start gap-3">
                                            <input
                                                type="checkbox"
                                                id="routes_to_local_pbx"
                                                checked={routesToLocalPbx}
                                                onChange={(e) => {
                                                    setRoutesToLocalPbx(e.target.checked);
                                                    setData('routes_to_local_pbx', e.target.checked);
                                                }}
                                                className="mt-1 h-4 w-4 rounded border-gray-300"
                                                disabled={processing}
                                            />
                                            <div className="flex-1">
                                                <Label htmlFor="routes_to_local_pbx" className="cursor-pointer font-semibold">
                                                    Route calls to my local PBX/SIP server
                                                </Label>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    Connect your on-premise Asterisk, FreePBX, 3CX, or other SIP server.
                                                    Inbound calls will be forwarded to your PBX, and your PBX can make outbound calls through Twilio.
                                                </p>
                                            </div>
                                        </div>

                                        {/* Show PBX fields when enabled */}
                                        {routesToLocalPbx && (
                                            <div className="space-y-4 border-l-4 border-primary pl-4">
                                                <Alert>
                                                    <AlertCircle className="h-4 w-4" />
                                                    <AlertDescription className="text-xs">
                                                        <strong>Requirements:</strong> Your PBX must have a public IP address or be accessible via VPN/tunnel.
                                                        Ensure port {data.local_pbx_port} is open for {data.local_pbx_transport.toUpperCase()} traffic.
                                                    </AlertDescription>
                                                </Alert>

                                                <div className="grid gap-4 md:grid-cols-2">
                                                    <div className="space-y-2">
                                                        <Label htmlFor="local_pbx_ip">PBX IP Address or Domain <span className="text-destructive">*</span></Label>
                                                        <Input
                                                            id="local_pbx_ip"
                                                            type="text"
                                                            value={data.local_pbx_ip}
                                                            onChange={(e) => setData('local_pbx_ip', e.target.value)}
                                                            placeholder="192.168.1.100 or pbx.example.com"
                                                            disabled={processing}
                                                            required={routesToLocalPbx}
                                                        />
                                                        {errors.local_pbx_ip && (
                                                            <p className="text-sm text-destructive">{errors.local_pbx_ip}</p>
                                                        )}
                                                    </div>

                                                    <div className="space-y-2">
                                                        <Label htmlFor="local_pbx_port">SIP Port</Label>
                                                        <Input
                                                            id="local_pbx_port"
                                                            type="number"
                                                            value={data.local_pbx_port}
                                                            onChange={(e) => setData('local_pbx_port', e.target.value)}
                                                            placeholder="5060"
                                                            disabled={processing}
                                                        />
                                                    </div>
                                                </div>

                                                <div className="space-y-2">
                                                    <Label htmlFor="local_pbx_transport">Transport Protocol</Label>
                                                    <select
                                                        id="local_pbx_transport"
                                                        value={data.local_pbx_transport}
                                                        onChange={(e) => setData('local_pbx_transport', e.target.value)}
                                                        className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                                        disabled={processing}
                                                    >
                                                        <option value="udp">UDP (Standard)</option>
                                                        <option value="tcp">TCP</option>
                                                        <option value="tls">TLS (Secure)</option>
                                                    </select>
                                                </div>

                                                <div className="grid gap-4 md:grid-cols-2">
                                                    <div className="space-y-2">
                                                        <Label htmlFor="local_pbx_username">Username (Optional)</Label>
                                                        <Input
                                                            id="local_pbx_username"
                                                            type="text"
                                                            value={data.local_pbx_username}
                                                            onChange={(e) => setData('local_pbx_username', e.target.value)}
                                                            placeholder="sip_user"
                                                            disabled={processing}
                                                        />
                                                        <p className="text-xs text-muted-foreground">
                                                            Only if your PBX requires SIP authentication
                                                        </p>
                                                    </div>

                                                    <div className="space-y-2">
                                                        <Label htmlFor="local_pbx_password">Password (Optional)</Label>
                                                        <Input
                                                            id="local_pbx_password"
                                                            type="password"
                                                            value={data.local_pbx_password}
                                                            onChange={(e) => setData('local_pbx_password', e.target.value)}
                                                            placeholder="••••••••"
                                                            disabled={processing}
                                                        />
                                                    </div>
                                                </div>
                                            </div>
                                        )}
                                    </div>

                                    <div className="rounded-lg bg-muted p-4">
                                        <h4 className="mb-2 font-semibold text-sm">What happens next?</h4>
                                        <ul className="space-y-1 text-sm text-muted-foreground">
                                            <li>1. Validate your Twilio credentials</li>
                                            <li>2. Create a SIP trunk in your account</li>
                                            <li>3. Configure inbound call routing</li>
                                            <li>4. Configure outbound authentication{routesToLocalPbx && ' (to your PBX)'}</li>
                                            <li>5. Import all your phone numbers</li>
                                            <li>6. Test the connection end-to-end</li>
                                        </ul>
                                    </div>

                                    <div className="flex gap-4">
                                        <Button type="submit" disabled={processing} className="w-full">
                                            {processing ? (
                                                <>
                                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                    Starting Setup...
                                                </>
                                            ) : (
                                                'Start Automatic Setup'
                                            )}
                                        </Button>
                                    </div>
                                </form>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Step 2: Automatic Setup in Progress */}
                {step === 'setup' && (
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('twilio.setting_up_your_sip_trunk')}</CardTitle>
                            <CardDescription>
                                Please wait while we configure your SIP trunk automatically. This usually takes about 60 seconds.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            {setupError ? (
                                <>
                                    <Alert variant="destructive">
                                        <AlertCircle className="h-4 w-4" />
                                        <AlertDescription>
                                            <p className="mb-2 font-semibold">Setup Failed</p>
                                            <p>{setupError}</p>
                                        </AlertDescription>
                                    </Alert>

                                    <div className="flex gap-4">
                                        <Button onClick={handleStartOver} variant="outline" className="w-full">
                                            Start Over
                                        </Button>
                                        <Button onClick={handleViewDashboard} variant="secondary" className="w-full">
                                            View Dashboard
                                        </Button>
                                    </div>
                                </>
                            ) : (
                                <>
                                    <div className="space-y-2">
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="font-medium">
                                                {SETUP_STEP_LABELS[setupStep] || 'Initializing...'}
                                            </span>
                                            <span className="text-muted-foreground">{setupProgress}%</span>
                                        </div>
                                        <Progress value={setupProgress} className="h-3" />
                                    </div>

                                    {/* Step checklist */}
                                    <div className="space-y-2">
                                        {Object.entries(SETUP_STEP_LABELS).map(([key, label]) => {
                                            if (key === 'completed') return null;
                                            const stepOrder = Object.keys(SETUP_STEP_LABELS);
                                            const currentIdx = stepOrder.indexOf(setupStep);
                                            const thisIdx = stepOrder.indexOf(key);
                                            const isDone = thisIdx < currentIdx;
                                            const isCurrent = key === setupStep;

                                            return (
                                                <div key={key} className="flex items-center gap-3 text-sm">
                                                    {isDone ? (
                                                        <Check className="h-4 w-4 text-green-500 flex-shrink-0" />
                                                    ) : isCurrent ? (
                                                        <Loader2 className="h-4 w-4 animate-spin text-primary flex-shrink-0" />
                                                    ) : (
                                                        <div className="h-4 w-4 rounded-full border border-muted-foreground/30 flex-shrink-0" />
                                                    )}
                                                    <span className={isDone ? 'text-muted-foreground line-through' : isCurrent ? 'font-medium' : 'text-muted-foreground'}>
                                                        {label}
                                                    </span>
                                                </div>
                                            );
                                        })}
                                    </div>

                                    <div className="space-y-2 text-center text-sm text-muted-foreground">
                                        <p>Do not close or refresh this page.</p>
                                    </div>
                                </>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Step 3: Success */}
                {step === 'success' && (
                    <Card>
                        <CardHeader>
                            <div className="flex items-center gap-4">
                                <div className="flex h-16 w-16 items-center justify-center rounded-full bg-green-100 dark:bg-green-900">
                                    <Check className="h-8 w-8 text-green-600 dark:text-green-400" />
                                </div>
                                <div>
                                    <CardTitle>{t('twilio.sip_trunk_connected_successfully')}</CardTitle>
                                    <CardDescription>Your trunk is ready to make and receive calls.</CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            {trunkDetails && (
                                <div className="rounded-lg border bg-card p-4">
                                    <h4 className="mb-4 font-semibold">Trunk Details</h4>
                                    <dl className="space-y-2 text-sm">
                                        <div className="flex justify-between">
                                            <dt className="text-muted-foreground">Trunk Name:</dt>
                                            <dd className="font-medium">{trunkDetails.friendly_name}</dd>
                                        </div>
                                        <div className="flex justify-between">
                                            <dt className="text-muted-foreground">Domain:</dt>
                                            <dd className="font-mono text-xs">{trunkDetails.trunk_domain_name}</dd>
                                        </div>
                                        <div className="flex justify-between">
                                            <dt className="text-muted-foreground">Phone Numbers:</dt>
                                            <dd className="font-medium">{trunkDetails.phone_numbers_count} imported</dd>
                                        </div>
                                    </dl>
                                </div>
                            )}

                            <Alert>
                                <Phone className="h-4 w-4" />
                                <AlertDescription>
                                    <p className="mb-2 font-semibold">What's Next?</p>
                                    <ul className="space-y-1 text-sm">
                                        <li>Your phone numbers are now connected to the trunk</li>
                                        <li>You can make calls at reduced rates ($0.0085/min)</li>
                                        <li>Inbound calls will route to your softphone or campaigns</li>
                                        <li>Monitor trunk health and statistics in the dashboard</li>
                                    </ul>
                                </AlertDescription>
                            </Alert>

                            <div className="flex gap-4">
                                <Button onClick={handleViewDashboard} className="w-full">
                                    View Trunk Dashboard
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
