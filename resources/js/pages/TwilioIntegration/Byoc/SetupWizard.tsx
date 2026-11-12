import Heading from '@/components/heading';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import { PageHelp } from '@/components/page-help';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Check, Loader2, Shield, Network, Settings, DollarSign, Zap, Globe } from 'lucide-react';
import { FormEventHandler, useState, useEffect } from 'react';

interface SetupWizardProps {
    user: {
        id: number;
        name: string;
        email: string;
    };
    twilioConfigured: boolean;
    twilioAccountSid: string | null;
    providerDefaults: {
        zadarma: any;
        voipms: any;
        custom: any;
    };
}

const helpSections = [
    {
        title: 'What is BYOC (Bring Your Own Carrier)?',
        content: 'BYOC allows you to route calls through your own 3rd party SIP providers (like Zadarma, VoIP.ms, or any SIP carrier) while still using Twilio\'s powerful API and platform. This provides massive cost savings - up to 60% cheaper than standard Twilio rates!',
    },
    {
        title: 'How It Works',
        content: `Your Application → Twilio REST API → BYOC Trunk → Your SIP Provider (Zadarma/VoIP.ms) → PSTN

When you make a call through campaigns or AI agents, Twilio automatically routes it through your configured SIP provider at their cheaper rates. You still use the same TwilioService code - no changes needed!`,
    },
    {
        title: 'Cost Comparison',
        content: `• Standard Twilio Voice: $0.013/min
• Elastic SIP Trunk (BYOT): $0.0085/min (35% savings)  
• BYOC via Zadarma: ~$0.005/min (62% savings!)
• BYOC via VoIP.ms: ~$0.0049/min (62% savings!)

For 10,000 minutes/month, BYOC can save you $80-$100 compared to standard Twilio!`,
    },
    {
        title: 'Supported Providers',
        content: `Pre-configured providers:
• Zadarma - Popular international provider with excellent rates
• VoIP.ms - Canadian/US provider with great reliability
• Custom - Any SIP provider with a SIP URI

You can add multiple providers for failover and load balancing!`,
    },
    {
        title: 'Compatibility',
        content: `✅ Campaign Calls - Works perfectly with MakeCampaignCallJob
✅ AI Agent Calls - Full support for AI-powered calls  
✅ Inbound Calls - Route through your SIP provider
✅ Call Recording - Twilio records as normal
✅ Status Callbacks - All webhooks work normally
⚠️ Softphone WebRTC - Cannot use BYOC (Twilio SDK limitation)`,
    },
    {
        title: 'What You Need',
        content: `1. Active account with a SIP provider (Zadarma, VoIP.ms, etc.)
2. Your SIP credentials from the provider
3. SIP URI format (we provide templates for popular providers)
4. Twilio account configured in Teleman

Setup takes about 90 seconds and is completely automatic!`,
    },
];

export default function ByocSetupWizard({ user, twilioConfigured, twilioAccountSid, providerDefaults }: SetupWizardProps) {
  const { t } = useTranslation();
  const breadcrumbs: BreadcrumbItem[] = [
    { title: t('common.dashboard'), href: dashboard().url },
    { title: t('twilio.twilio_integration'), href: '/settings/twilio' },
    { title: t('twilio.byoc_setup'), href: '/byoc/setup' },
];
    const [step, setStep] = useState<'config' | 'setup' | 'success'>('config');
    const [setupProgress, setSetupProgress] = useState(0);
    const [setupStep, setSetupStep] = useState('');
    const [setupError, setSetupError] = useState<string | null>(null);
    const [trunkId, setTrunkId] = useState<number | null>(null);

    const { data, setData, processing, errors } = useForm({
        friendly_name: `${user.name}'s BYOC Trunk`,
        provider_type: 'zadarma' as 'zadarma' | 'voipms' | 'custom',
        sip_uri: '',
        sip_username: '',
        sip_password: '',
        cost_per_minute: '0.005',
        priority: 1,
        weight: 100,
        add_backup: true,
    });

    // Update SIP URI template when provider changes
    useEffect(() => {
        const provider = providerDefaults[data.provider_type];
        if (provider && provider.sip_uri_template) {
            // Don't overwrite if user has typed something
            if (!data.sip_uri || data.sip_uri.includes('{username}')) {
                setData('sip_uri', provider.sip_uri_template);
            }
            if (provider.default_cost_per_minute) {
                setData('cost_per_minute', provider.default_cost_per_minute.toString());
            }
        }
    }, [data.provider_type]);

    // Poll setup status
    useEffect(() => {
        if (step === 'setup' && trunkId) {
            const interval = setInterval(async () => {
                try {
                    const response = await window.axios.get(`/byoc/${trunkId}/setup-status`);
                    
                    setSetupProgress(response.data.setup_progress || 0);
                    setSetupStep(response.data.setup_step || '');
                    
                    if (response.data.is_setup_complete) {
                        clearInterval(interval);
                        setTimeout(() => setStep('success'), 1000);
                    }
                    
                    if (response.data.setup_error) {
                        clearInterval(interval);
                        setSetupError(response.data.setup_error);
                    }
                } catch (error) {
                    console.error('Failed to fetch setup status:', error);
                }
            }, 2000);

            return () => clearInterval(interval);
        }
    }, [step, trunkId]);

    const submit: FormEventHandler = async (e) => {
        e.preventDefault();
        setSetupError(null);

        try {
            const response = await window.axios.post('/byoc/setup', data);
            
            if (response.data.success) {
                setTrunkId(response.data.trunk.id);
                setStep('setup');
            } else {
                setSetupError(response.data.message || 'Failed to setup BYOC trunk');
            }
        } catch (error: any) {
            setSetupError(
                error.response?.data?.message ||
                'Failed to setup BYOC trunk. Please check your configuration and try again.'
            );
        }
    };

    const currentProvider = providerDefaults[data.provider_type];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('twilio.byoc_trunk_setup')} />

            <div className="mx-auto max-w-4xl space-y-6 p-4 md:p-8">
                <div className="flex items-center justify-between">
                    <Heading title={t('twilio.byoc_trunk_setup')} />
                    <PageHelp 
                        title={t('twilio.byoc_setup_help')} 
                        sections={helpSections}
                        documentationUrl="/docs/documentation.html#byoc"
                    />
                </div>

                {/* Cost Savings Banner */}
                <Alert className="border-l-4 border-green-500 bg-green-50 dark:bg-green-950">
                    <DollarSign className="h-5 w-5 text-green-600" />
                    <AlertTitle className="text-green-900 dark:text-green-100">Save Up To 62% on Call Costs!</AlertTitle>
                    <AlertDescription className="text-green-800 dark:text-green-200">
                        Route calls through your own SIP provider (Zadarma, VoIP.ms, etc.) and pay as low as $0.005/min instead of Twilio's $0.013/min standard rate.
                    </AlertDescription>
                </Alert>

                {/* Step Indicators */}
                <div className="flex items-center justify-center space-x-4">
                    <div className="flex flex-col items-center">
                        <div className={`flex h-12 w-12 items-center justify-center rounded-full ${
                            step === 'config' ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'
                        }`}>
                            <Settings className="h-6 w-6" />
                        </div>
                        <span className="mt-2 text-sm font-medium">Configure</span>
                    </div>

                    <div className="h-px w-20 bg-muted" />

                    <div className="flex flex-col items-center">
                        <div className={`flex h-12 w-12 items-center justify-center rounded-full ${
                            step === 'setup' ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'
                        }`}>
                            <Zap className="h-6 w-6" />
                        </div>
                        <span className="mt-2 text-sm font-medium">Setup</span>
                    </div>

                    <div className="h-px w-20 bg-muted" />

                    <div className="flex flex-col items-center">
                        <div className={`flex h-12 w-12 items-center justify-center rounded-full ${
                            step === 'success' ? 'bg-primary text-primary-foreground' : 'bg-muted text-muted-foreground'
                        }`}>
                            <Check className="h-6 w-6" />
                        </div>
                        <span className="mt-2 text-sm font-medium">Complete</span>
                    </div>
                </div>

                {/* Step 1: Configuration */}
                {step === 'config' && (
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('twilio.configure_your_sip_provider')}</CardTitle>
                            <CardDescription>
                                Connect Zadarma, VoIP.ms, or any SIP provider to save on call costs
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {!twilioConfigured ? (
                                <Alert variant="destructive">
                                    <AlertDescription>
                                        Please configure your Twilio account first.{' '}
                                        <Link href="/settings/twilio" className="underline">Go to Twilio Settings</Link>
                                    </AlertDescription>
                                </Alert>
                            ) : (
                                <form onSubmit={submit} className="space-y-6">
                                    {setupError && (
                                        <Alert variant="destructive">
                                            <AlertDescription>{setupError}</AlertDescription>
                                        </Alert>
                                    )}

                                    <div className="space-y-2">
                                        <Label htmlFor="friendly_name">Trunk Name</Label>
                                        <Input
                                            id="friendly_name"
                                            value={data.friendly_name}
                                            onChange={(e) => setData('friendly_name', e.target.value)}
                                            placeholder="e.g., Zadarma Production Trunk"
                                            disabled={processing}
                                        />
                                        {errors.friendly_name && <p className="text-sm text-destructive">{errors.friendly_name}</p>}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="provider_type">SIP Provider</Label>
                                        <Select
                                            value={data.provider_type}
                                            onValueChange={(value: any) => setData('provider_type', value)}
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="zadarma">
                                                    <div className="flex items-center gap-2">
                                                        <Globe className="h-4 w-4" />
                                                        Zadarma (~$0.005/min)
                                                    </div>
                                                </SelectItem>
                                                <SelectItem value="voipms">
                                                    <div className="flex items-center gap-2">
                                                        <Globe className="h-4 w-4" />
                                                        VoIP.ms (~$0.0049/min)
                                                    </div>
                                                </SelectItem>
                                                <SelectItem value="custom">
                                                    <div className="flex items-center gap-2">
                                                        <Settings className="h-4 w-4" />
                                                        Custom SIP Provider
                                                    </div>
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        {currentProvider?.notes && (
                                            <p className="text-xs text-muted-foreground">{currentProvider.notes}</p>
                                        )}
                                    </div>

                                    <div className="grid gap-4 md:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label htmlFor="sip_username">SIP Username</Label>
                                            <Input
                                                id="sip_username"
                                                value={data.sip_username}
                                                onChange={(e) => setData('sip_username', e.target.value)}
                                                placeholder="Your SIP username"
                                                disabled={processing}
                                            />
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="sip_password">SIP Password</Label>
                                            <Input
                                                id="sip_password"
                                                type="password"
                                                value={data.sip_password}
                                                onChange={(e) => setData('sip_password', e.target.value)}
                                                placeholder="Your SIP password"
                                                disabled={processing}
                                            />
                                        </div>
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="sip_uri">SIP URI</Label>
                                        <Input
                                            id="sip_uri"
                                            value={data.sip_uri}
                                            onChange={(e) => setData('sip_uri', e.target.value)}
                                            placeholder="sip:username@provider.com"
                                            disabled={processing}
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            Replace {'{username}'} with your actual username
                                        </p>
                                        {errors.sip_uri && <p className="text-sm text-destructive">{errors.sip_uri}</p>}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="cost_per_minute">Cost Per Minute (USD)</Label>
                                        <Input
                                            id="cost_per_minute"
                                            type="number"
                                            step="0.0001"
                                            value={data.cost_per_minute}
                                            onChange={(e) => setData('cost_per_minute', e.target.value)}
                                            placeholder="0.005"
                                            disabled={processing}
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            For cost tracking and statistics
                                        </p>
                                    </div>

                                    <div className="flex items-center space-x-2">
                                        <Checkbox
                                            id="add_backup"
                                            checked={data.add_backup}
                                            onCheckedChange={(checked) => setData('add_backup', !!checked)}
                                        />
                                        <Label htmlFor="add_backup" className="text-sm font-normal">
                                            Add Twilio as backup provider (recommended)
                                        </Label>
                                    </div>

                                    <div className="rounded-lg border bg-muted/50 p-4">
                                        <h4 className="mb-2 text-sm font-semibold">What happens next?</h4>
                                        <ul className="space-y-1 text-xs text-muted-foreground">
                                            <li>• Create connection policy for routing</li>
                                            <li>• Add your SIP provider as primary target</li>
                                            <li>• Configure Twilio backup (if selected)</li>
                                            <li>• Create BYOC trunk and test connection</li>
                                            <li>• Your campaigns will automatically use cheaper rates!</li>
                                        </ul>
                                    </div>

                                    <Button type="submit" disabled={processing} className="w-full">
                                        {processing ? (
                                            <>
                                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                Starting Setup...
                                            </>
                                        ) : (
                                            'Setup BYOC Trunk'
                                        )}
                                    </Button>
                                </form>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Step 2: Setup in Progress */}
                {step === 'setup' && (
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('twilio.setting_up_your_byoc_trunk')}</CardTitle>
                            <CardDescription>
                                Configuring connection to your SIP provider...
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            {setupError ? (
                                <Alert variant="destructive">
                                    <AlertDescription>
                                        <p className="mb-2 font-semibold">Setup Failed</p>
                                        <p>{setupError}</p>
                                    </AlertDescription>
                                </Alert>
                            ) : (
                                <>
                                    <div className="space-y-2">
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="font-medium">{setupStep || 'Initializing...'}</span>
                                            <span className="text-muted-foreground">{setupProgress}%</span>
                                        </div>
                                        <Progress value={setupProgress} className="h-2" />
                                    </div>

                                    <div className="flex items-center justify-center py-8">
                                        <Loader2 className="h-12 w-12 animate-spin text-primary" />
                                    </div>

                                    <div className="space-y-2 text-center text-sm text-muted-foreground">
                                        <p>Do not close or refresh this page.</p>
                                        <p>Setting up connection policy and routing...</p>
                                    </div>
                                </>
                            )}

                            {setupError && (
                                <Button onClick={() => router.visit('/byoc/setup')} className="w-full">
                                    Try Again
                                </Button>
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
                                    <CardTitle>{t('twilio.byoc_trunk_connected')}</CardTitle>
                                    <CardDescription>Your calls will now route through your SIP provider</CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <Alert className="border-l-4 border-green-500">
                                <DollarSign className="h-4 w-4 text-green-600" />
                                <AlertTitle>Start Saving on Every Call</AlertTitle>
                                <AlertDescription>
                                    <ul className="mt-2 space-y-1 text-sm">
                                        <li>• Campaign calls now use your provider's rates</li>
                                        <li>• AI agent calls automatically routed</li>
                                        <li>• Twilio backup active for reliability</li>
                                        <li>• Track savings in the dashboard</li>
                                    </ul>
                                </AlertDescription>
                            </Alert>

                            <div className="flex gap-4">
                                <Button onClick={() => router.visit('/byoc')} className="w-full">
                                    View BYOC Dashboard
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
