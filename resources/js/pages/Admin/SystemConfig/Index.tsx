import { Head, useForm, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Switch } from '@/components/ui/switch';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Settings, CreditCard, Mail, Globe, Send, AlertCircle, CheckCircle2, Upload, Image as ImageIcon, Coins, RefreshCw, Shield } from 'lucide-react';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { useState, useRef, useEffect, useCallback } from 'react';

interface SystemConfigProps {
  config: {
    stripe: {
      key: string;
      secret: string;
      webhook_secret: string;
    };
    razorpay: {
      key: string;
      secret: string;
      webhook_secret: string;
      test_mode: boolean;
    };
    paystack: {
      public_key: string;
      secret_key: string;
      test_mode: boolean;
    };
    paypal: {
      client_id: string;
      client_secret: string;
      webhook_id: string;
      test_mode: boolean;
    };
    flutterwave: {
      public_key: string;
      secret_key: string;
      encryption_key: string;
      secret_hash: string;
      test_mode: boolean;
    };
    cryptomus: {
      merchant_uuid: string;
      payment_key: string;
      test_mode: boolean;
    };
    mail: {
      mailer: string;
      host: string;
      port: number;
      username: string;
      password: string;
      encryption: string;
      from_address: string;
      from_name: string;
    };
    currency: {
      base_currency: string;
      exchange_rate_mode: string;
      manual_rates: Record<string, Record<string, number>>;
    };
    turnstile: {
      site_key: string;
      secret_key: string;
      enabled: boolean;
    };
    application: {
      name: string;
      url: string;
      timezone: string;
      locale: string;
      debug: boolean;
      environment: string;
      logo?: string;
      logo_dark?: string;
      favicon?: string;
      branding?: {
        name?: string;
        logoLight?: string | null;
        logoDark?: string | null;
        landing?: { mode: string; size: number };
        dashboard?: { mode: string; size: number };
        auth?: { mode: string; size: number };
      };
    };
  };
}

export default function SystemConfig({ config }: SystemConfigProps) {
  const { t } = useTranslation();
  const breadcrumbs: BreadcrumbItem[] = [
  {
    title: t('common.dashboard'), href: dashboard().url,
  },
  {
    title: t('admin.system_config.system_configuration'), href: '/admin/system-config',
  },
];
  const { flash } = usePage().props as any;
  const [showSuccessAlert, setShowSuccessAlert] = useState(false);
  const [showErrorAlert, setShowErrorAlert] = useState(false);
  const [testEmailSending, setTestEmailSending] = useState(false);
  const [testEmailResult, setTestEmailResult] = useState<{ success: boolean; message: string } | null>(null);
  const [logoPreview, setLogoPreview] = useState<string | null>(config.application.logo || null);
  const [logoDarkPreview, setLogoDarkPreview] = useState<string | null>(config.application.logo_dark || null);
  const [faviconPreview, setFaviconPreview] = useState<string | null>(config.application.favicon || null);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const darkInputRef = useRef<HTMLInputElement>(null);
  const faviconInputRef = useRef<HTMLInputElement>(null);

  // Tab state from URL hash fragment
  const validTabs = ['stripe', 'razorpay', 'paystack', 'paypal', 'flutterwave', 'cryptomus', 'mail', 'currency', 'turnstile', 'application'];
  const getTabFromHash = useCallback(() => {
    const hash = window.location.hash.replace('#', '');
    return validTabs.includes(hash) ? hash : 'stripe';
  }, []);
  const [activeTab, setActiveTab] = useState(getTabFromHash);

  const handleTabChange = useCallback((value: string) => {
    setActiveTab(value);
    window.history.replaceState(null, '', `#${value}`);
  }, []);

  // Show flash messages
  useEffect(() => {
    if (flash?.success) {
      setShowSuccessAlert(true);
      setTimeout(() => setShowSuccessAlert(false), 5000);
    }
    if (flash?.error) {
      setShowErrorAlert(true);
      setTimeout(() => setShowErrorAlert(false), 5000);
    }
  }, [flash]);

  const stripeForm = useForm({
    section: 'stripe',
    config: {
      key: config.stripe.key,
      secret: config.stripe.secret,
      webhook_secret: config.stripe.webhook_secret,
    },
  });

  const razorpayForm = useForm({
    section: 'razorpay',
    config: {
      key: config.razorpay.key,
      secret: config.razorpay.secret,
      webhook_secret: config.razorpay.webhook_secret,
      test_mode: config.razorpay.test_mode,
    },
  });

  const paystackForm = useForm({
    section: 'paystack',
    config: {
      public_key: config.paystack.public_key,
      secret_key: config.paystack.secret_key,
      test_mode: config.paystack.test_mode,
    },
  });

  const paypalForm = useForm({
    section: 'paypal',
    config: {
      client_id: config.paypal.client_id,
      client_secret: config.paypal.client_secret,
      webhook_id: config.paypal.webhook_id,
      test_mode: config.paypal.test_mode,
    },
  });

  const flutterwaveForm = useForm({
    section: 'flutterwave',
    config: {
      public_key: config.flutterwave.public_key,
      secret_key: config.flutterwave.secret_key,
      encryption_key: config.flutterwave.encryption_key,
      secret_hash: config.flutterwave.secret_hash,
      test_mode: config.flutterwave.test_mode,
    },
  });

  const cryptomusForm = useForm({
    section: 'cryptomus',
    config: {
      merchant_uuid: config.cryptomus.merchant_uuid,
      payment_key: config.cryptomus.payment_key,
      test_mode: config.cryptomus.test_mode,
    },
  });

  const turnstileForm = useForm({
    section: 'turnstile',
    config: {
      site_key: config.turnstile.site_key,
      secret_key: config.turnstile.secret_key,
      enabled: config.turnstile.enabled,
    },
  });

  const [fetchingRates, setFetchingRates] = useState(false);

  const currencyForm = useForm({
    section: 'currency',
    config: {
      base_currency: config.currency?.base_currency || 'USD',
      exchange_rate_mode: config.currency?.exchange_rate_mode || 'auto',
      manual_rates: config.currency?.manual_rates || {},
    },
  });

  const mailForm = useForm({
    section: 'mail',
    config: {
      mailer: config.mail.mailer,
      host: config.mail.host,
      port: config.mail.port,
      username: config.mail.username,
      password: config.mail.password,
      encryption: config.mail.encryption,
      from_address: config.mail.from_address,
      from_name: config.mail.from_name,
    },
  });

  const appForm = useForm({
    section: 'application',
    config: {
      name: config.application.name,
      url: config.application.url,
      timezone: config.application.timezone,
      locale: config.application.locale,
      debug: config.application.debug,
      environment: config.application.environment,
      logo_display_landing: config.application.branding?.landing?.mode ?? 'logo_text',
      logo_display_dashboard: config.application.branding?.dashboard?.mode ?? 'logo_text',
      logo_display_auth: config.application.branding?.auth?.mode ?? 'logo_text',
      logo_size_landing: config.application.branding?.landing?.size ?? 34,
      logo_size_dashboard: config.application.branding?.dashboard?.size ?? 32,
      logo_size_auth: config.application.branding?.auth?.size ?? 40,
      remove_logo: false,
      remove_logo_dark: false,
    },
    logo: null as File | null,
    logo_dark: null as File | null,
    favicon: null as File | null,
  });

  const [testEmail, setTestEmail] = useState('');

  const handleStripeSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    stripeForm.post('/admin/system-config', {
      preserveScroll: true,
      onSuccess: () => {
        // Flash message will be shown via useEffect
      },
      onError: () => {
        // Flash message will be shown via useEffect
      },
    });
  };

  const handleRazorpaySubmit = (e: React.FormEvent) => {
    e.preventDefault();
    razorpayForm.post('/admin/system-config', {
      preserveScroll: true,
      onSuccess: () => {
        // Flash message will be shown via useEffect
      },
      onError: () => {
        // Flash message will be shown via useEffect
      },
    });
  };

  const handlePaystackSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    paystackForm.post('/admin/system-config', {
      preserveScroll: true,
    });
  };

  const handlePayPalSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    paypalForm.post('/admin/system-config', {
      preserveScroll: true,
    });
  };

  const handleFlutterwaveSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    flutterwaveForm.post('/admin/system-config', {
      preserveScroll: true,
    });
  };

  const handleCryptomusSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    cryptomusForm.post('/admin/system-config', {
      preserveScroll: true,
    });
  };

  const handleTurnstileSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    turnstileForm.post('/admin/system-config', {
      preserveScroll: true,
    });
  };

  const handleCurrencySubmit = (e: React.FormEvent) => {
    e.preventDefault();
    currencyForm.post('/admin/system-config', {
      preserveScroll: true,
    });
  };

  const handleMailSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    mailForm.post('/admin/system-config', {
      preserveScroll: true,
      onSuccess: () => {
        // Flash message will be shown via useEffect
      },
      onError: () => {
        // Flash message will be shown via useEffect
      },
    });
  };

  const handleAppSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    appForm.post('/admin/system-config', {
      preserveScroll: true,
      forceFormData: true,
      onSuccess: () => {
        // Flash message will be shown via useEffect
      },
      onError: () => {
        // Flash message will be shown via useEffect
      },
    });
  };

  const handleLogoChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      appForm.setData('logo', file);

      // Create preview
      const reader = new FileReader();
      reader.onloadend = () => {
        setLogoPreview(reader.result as string);
      };
      reader.readAsDataURL(file);
    }
  };

  const handleLogoDarkChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      appForm.setData('logo_dark', file);
      const reader = new FileReader();
      reader.onloadend = () => setLogoDarkPreview(reader.result as string);
      reader.readAsDataURL(file);
    }
  };

  const handleRemoveLogo = () => {
    setLogoPreview(null);
    appForm.setData('logo', null);
    appForm.setData('config', { ...appForm.data.config, remove_logo: true });
    if (fileInputRef.current) fileInputRef.current.value = '';
  };

  const handleRemoveLogoDark = () => {
    setLogoDarkPreview(null);
    appForm.setData('logo_dark', null);
    appForm.setData('config', { ...appForm.data.config, remove_logo_dark: true });
    if (darkInputRef.current) darkInputRef.current.value = '';
  };

  const handleFaviconChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      appForm.setData('favicon', file);
      
      // Create preview
      const reader = new FileReader();
      reader.onloadend = () => {
        setFaviconPreview(reader.result as string);
      };
      reader.readAsDataURL(file);
    }
  };

  const handleTestEmail = async () => {
    if (!testEmail) return;
    
    setTestEmailSending(true);
    setTestEmailResult(null);

    try {
      const response = await window.axios.post('/admin/system-config/test-mail', {
        email: testEmail,
      });

      setTestEmailResult({
        success: true,
        message: response.data.message,
      });
    } catch (error: unknown) {
      let errorMessage = 'Failed to send test email';
      
      if (error && typeof error === 'object' && 'response' in error) {
        const axiosError = error as { response?: { data?: { message?: string } } };
        errorMessage = axiosError.response?.data?.message || errorMessage;
      }
      
      setTestEmailResult({
        success: false,
        message: errorMessage,
      });
    } finally {
      setTestEmailSending(false);
    }
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={t('admin.system_config.system_configuration')} />

      <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-8">
        <div>
          <h2 className="text-xl font-semibold tracking-tight">{t('admin.system_config.system_configuration')}</h2>
          <p className="text-sm text-muted-foreground">{t('admin.system_config.manage_systemwide_settings_and_integrations')}</p>
        </div>

        {/* Success Alert */}
        {showSuccessAlert && flash?.success && (
          <Alert className="border-green-600 dark:border-green-500 bg-green-50 dark:bg-green-950/30">
            <CheckCircle2 className="h-4 w-4 text-green-600 dark:text-green-500" />
            <AlertDescription className="text-green-900 dark:text-green-100">
              {flash.success}
            </AlertDescription>
          </Alert>
        )}

        {/* Error Alert */}
        {showErrorAlert && flash?.error && (
          <Alert variant="destructive">
            <AlertCircle className="h-4 w-4" />
            <AlertDescription>
              {flash.error}
            </AlertDescription>
          </Alert>
        )}

        <Tabs value={activeTab} onValueChange={handleTabChange} orientation="vertical" className="!flex-row !gap-6 items-start">
          <TabsList className="sticky top-4 !flex !h-auto !w-44 shrink-0 flex-col !items-stretch !justify-start rounded-lg border !bg-muted !p-1.5 gap-0.5">
            <TabsTrigger value="stripe" className="!h-auto !flex-none justify-start gap-2 px-3 py-2 text-sm">
              <CreditCard className="h-4 w-4 shrink-0" />
              <span>Stripe</span>
            </TabsTrigger>
            <TabsTrigger value="razorpay" className="!h-auto !flex-none justify-start gap-2 px-3 py-2 text-sm">
              <CreditCard className="h-4 w-4 shrink-0" />
              <span>Razorpay</span>
            </TabsTrigger>
            <TabsTrigger value="paystack" className="!h-auto !flex-none justify-start gap-2 px-3 py-2 text-sm">
              <CreditCard className="h-4 w-4 shrink-0" />
              <span>Paystack</span>
            </TabsTrigger>
            <TabsTrigger value="paypal" className="!h-auto !flex-none justify-start gap-2 px-3 py-2 text-sm">
              <CreditCard className="h-4 w-4 shrink-0" />
              <span>PayPal</span>
            </TabsTrigger>
            <TabsTrigger value="flutterwave" className="!h-auto !flex-none justify-start gap-2 px-3 py-2 text-sm">
              <CreditCard className="h-4 w-4 shrink-0" />
              <span>Flutterwave</span>
            </TabsTrigger>
            <TabsTrigger value="cryptomus" className="!h-auto !flex-none justify-start gap-2 px-3 py-2 text-sm">
              <Coins className="h-4 w-4 shrink-0" />
              <span>Cryptomus</span>
            </TabsTrigger>
            <TabsTrigger value="mail" className="!h-auto !flex-none justify-start gap-2 px-3 py-2 text-sm">
              <Mail className="h-4 w-4 shrink-0" />
              <span>Mail</span>
            </TabsTrigger>
            <TabsTrigger value="currency" className="!h-auto !flex-none justify-start gap-2 px-3 py-2 text-sm">
              <Coins className="h-4 w-4 shrink-0" />
              <span>{t('admin.system_config.currency')}</span>
            </TabsTrigger>
            <TabsTrigger value="turnstile" className="!h-auto !flex-none justify-start gap-2 px-3 py-2 text-sm">
              <Shield className="h-4 w-4 shrink-0" />
              <span>Turnstile</span>
            </TabsTrigger>
            <TabsTrigger value="application" className="!h-auto !flex-none justify-start gap-2 px-3 py-2 text-sm">
              <Globe className="h-4 w-4 shrink-0" />
              <span>Application</span>
            </TabsTrigger>
          </TabsList>

          <div className="flex-1 min-w-0">

          {/* Stripe Configuration */}
          <TabsContent value="stripe">
            <form onSubmit={handleStripeSubmit}>
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <CreditCard className="h-5 w-5" />
                    Stripe Configuration
                  </CardTitle>
                  <CardDescription>
                    Configure Stripe payment gateway for processing payments
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  <Alert>
                    <AlertCircle className="h-4 w-4" />
                    <AlertDescription>
                      Get your Stripe credentials from{' '}
                      <a
                        href="https://dashboard.stripe.com/apikeys"
                        target="_blank"
                        rel="noopener noreferrer"
                        className="font-medium underline"
                      >
                        Stripe Dashboard
                      </a>
                    </AlertDescription>
                  </Alert>

                  <div className="space-y-2">
                    <Label htmlFor="stripe_key">Publishable Key</Label>
                    <Input
                      id="stripe_key"
                      type="text"
                      value={stripeForm.data.config.key}
                      onChange={(e) => stripeForm.setData('config', {
                        ...stripeForm.data.config,
                        key: e.target.value,
                      })}
                      placeholder="pk_test_..."
                    />
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="stripe_secret">Secret Key</Label>
                    <Input
                      id="stripe_secret"
                      type="password"
                      value={stripeForm.data.config.secret}
                      onChange={(e) => stripeForm.setData('config', {
                        ...stripeForm.data.config,
                        secret: e.target.value,
                      })}
                      placeholder="sk_test_..."
                    />
                    <p className="text-xs text-muted-foreground">
                      Leave as dots to keep existing value
                    </p>
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="stripe_webhook_secret">Webhook Signing Secret</Label>
                    <Input
                      id="stripe_webhook_secret"
                      type="password"
                      value={stripeForm.data.config.webhook_secret}
                      onChange={(e) => stripeForm.setData('config', {
                        ...stripeForm.data.config,
                        webhook_secret: e.target.value,
                      })}
                      placeholder="whsec_..."
                    />
                    <p className="text-xs text-muted-foreground">
                      Without it, payment notifications from Stripe are refused and a top-up is credited only when the buyer returns to this site. Set the webhook URL to:{' '}
                      <code className="text-xs bg-muted px-1 rounded">{window.location.origin}/webhooks/payment/stripe</code>
                    </p>
                  </div>

                  <div className="flex justify-end pt-4">
                    <Button type="submit" disabled={stripeForm.processing}>
                      <Settings className="mr-2 h-4 w-4" />
                      Save Stripe Configuration
                    </Button>
                  </div>
                </CardContent>
              </Card>
            </form>
          </TabsContent>

          {/* Razorpay Configuration */}
          <TabsContent value="razorpay">
            <form onSubmit={handleRazorpaySubmit}>
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <CreditCard className="h-5 w-5" />
                    Razorpay Configuration
                  </CardTitle>
                  <CardDescription>
                    Configure Razorpay payment gateway for processing payments in India
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  <Alert>
                    <AlertCircle className="h-4 w-4" />
                    <AlertDescription>
                      Get your Razorpay credentials from{' '}
                      <a
                        href="https://dashboard.razorpay.com/app/keys"
                        target="_blank"
                        rel="noopener noreferrer"
                        className="font-medium underline"
                      >
                        Razorpay Dashboard
                      </a>
                    </AlertDescription>
                  </Alert>

                  <div className="space-y-2">
                    <Label htmlFor="razorpay_key">API Key (Key ID)</Label>
                    <Input
                      id="razorpay_key"
                      type="text"
                      value={razorpayForm.data.config.key}
                      onChange={(e) => razorpayForm.setData('config', {
                        ...razorpayForm.data.config,
                        key: e.target.value,
                      })}
                      placeholder="rzp_test_..."
                    />
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="razorpay_secret">Secret Key</Label>
                    <Input
                      id="razorpay_secret"
                      type="password"
                      value={razorpayForm.data.config.secret}
                      onChange={(e) => razorpayForm.setData('config', {
                        ...razorpayForm.data.config,
                        secret: e.target.value,
                      })}
                      placeholder="Your Razorpay secret key..."
                    />
                    <p className="text-xs text-muted-foreground">
                      Leave as dots to keep existing value
                    </p>
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="razorpay_webhook_secret">Webhook Secret</Label>
                    <Input
                      id="razorpay_webhook_secret"
                      type="password"
                      value={razorpayForm.data.config.webhook_secret}
                      onChange={(e) => razorpayForm.setData('config', {
                        ...razorpayForm.data.config,
                        webhook_secret: e.target.value,
                      })}
                      placeholder="Your webhook secret..."
                    />
                    <p className="text-xs text-muted-foreground">
                      Without it, payment notifications from Razorpay are refused and a top-up is credited only when the buyer returns to this site. Get it from your Razorpay webhook settings.
                    </p>
                  </div>

                  <div className="flex items-center justify-between rounded-lg border p-4">
                    <div className="space-y-0.5">
                      <Label htmlFor="razorpay_test_mode">Test Mode</Label>
                      <p className="text-sm text-muted-foreground">
                        Enable test mode to use test API keys
                      </p>
                    </div>
                    <Switch
                      id="razorpay_test_mode"
                      checked={razorpayForm.data.config.test_mode}
                      onCheckedChange={(checked) => razorpayForm.setData('config', {
                        ...razorpayForm.data.config,
                        test_mode: checked,
                      })}
                    />
                  </div>

                  <div className="flex justify-end pt-4">
                    <Button type="submit" disabled={razorpayForm.processing}>
                      <Settings className="mr-2 h-4 w-4" />
                      Save Razorpay Configuration
                    </Button>
                  </div>
                </CardContent>
              </Card>
            </form>
          </TabsContent>

          {/* Paystack Configuration */}
          <TabsContent value="paystack">
            <form onSubmit={handlePaystackSubmit}>
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <CreditCard className="h-5 w-5" />
                    Paystack Configuration
                  </CardTitle>
                  <CardDescription>
                    Configure Paystack payment gateway for processing payments in Africa (NGN, GHS, ZAR, KES, USD)
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  <Alert>
                    <AlertCircle className="h-4 w-4" />
                    <AlertDescription>
                      Get your Paystack credentials from{' '}
                      <a
                        href="https://dashboard.paystack.com/#/settings/developers"
                        target="_blank"
                        rel="noopener noreferrer"
                        className="font-medium underline"
                      >
                        Paystack Dashboard
                      </a>
                    </AlertDescription>
                  </Alert>

                  <div className="space-y-2">
                    <Label htmlFor="paystack_public_key">Public Key</Label>
                    <Input
                      id="paystack_public_key"
                      type="text"
                      value={paystackForm.data.config.public_key}
                      onChange={(e) => paystackForm.setData('config', {
                        ...paystackForm.data.config,
                        public_key: e.target.value,
                      })}
                      placeholder="pk_test_..."
                    />
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="paystack_secret_key">Secret Key</Label>
                    <Input
                      id="paystack_secret_key"
                      type="password"
                      value={paystackForm.data.config.secret_key}
                      onChange={(e) => paystackForm.setData('config', {
                        ...paystackForm.data.config,
                        secret_key: e.target.value,
                      })}
                      placeholder="sk_test_..."
                    />
                    <p className="text-xs text-muted-foreground">
                      Leave as dots to keep existing value
                    </p>
                  </div>

                  <div className="flex items-center justify-between rounded-lg border p-4">
                    <div className="space-y-0.5">
                      <Label htmlFor="paystack_test_mode">Test Mode</Label>
                      <p className="text-sm text-muted-foreground">
                        Enable test mode to use test API keys
                      </p>
                    </div>
                    <Switch
                      id="paystack_test_mode"
                      checked={paystackForm.data.config.test_mode}
                      onCheckedChange={(checked) => paystackForm.setData('config', {
                        ...paystackForm.data.config,
                        test_mode: checked,
                      })}
                    />
                  </div>

                  <div className="flex justify-end pt-4">
                    <Button type="submit" disabled={paystackForm.processing}>
                      <Settings className="mr-2 h-4 w-4" />
                      Save Paystack Configuration
                    </Button>
                  </div>
                </CardContent>
              </Card>
            </form>
          </TabsContent>

          {/* PayPal Configuration */}
          <TabsContent value="paypal">
            <form onSubmit={handlePayPalSubmit}>
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <CreditCard className="h-5 w-5" />
                    PayPal Configuration
                  </CardTitle>
                  <CardDescription>
                    Configure PayPal payment gateway — accepted in 200+ countries, 25 currencies
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  <Alert>
                    <AlertCircle className="h-4 w-4" />
                    <AlertDescription>
                      Get your PayPal credentials from{' '}
                      <a
                        href="https://developer.paypal.com/dashboard/applications"
                        target="_blank"
                        rel="noopener noreferrer"
                        className="font-medium underline"
                      >
                        PayPal Developer Dashboard
                      </a>
                      . Create a REST API app to get Client ID and Secret.
                    </AlertDescription>
                  </Alert>

                  <div className="space-y-2">
                    <Label htmlFor="paypal_client_id">Client ID</Label>
                    <Input
                      id="paypal_client_id"
                      type="text"
                      value={paypalForm.data.config.client_id}
                      onChange={(e) => paypalForm.setData('config', {
                        ...paypalForm.data.config,
                        client_id: e.target.value,
                      })}
                      placeholder="AXxx..."
                    />
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="paypal_client_secret">Client Secret</Label>
                    <Input
                      id="paypal_client_secret"
                      type="password"
                      value={paypalForm.data.config.client_secret}
                      onChange={(e) => paypalForm.setData('config', {
                        ...paypalForm.data.config,
                        client_secret: e.target.value,
                      })}
                      placeholder="EBxx..."
                    />
                    <p className="text-xs text-muted-foreground">
                      Leave as dots to keep existing value
                    </p>
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="paypal_webhook_id">Webhook ID</Label>
                    <Input
                      id="paypal_webhook_id"
                      type="password"
                      value={paypalForm.data.config.webhook_id}
                      onChange={(e) => paypalForm.setData('config', {
                        ...paypalForm.data.config,
                        webhook_id: e.target.value,
                      })}
                      placeholder="Your PayPal Webhook ID..."
                    />
                    <p className="text-xs text-muted-foreground">
                      Without it, payment notifications from PayPal are refused and a top-up is credited only when the buyer returns to this site. Set the webhook URL to:{' '}
                      <code className="text-xs bg-muted px-1 rounded">{window.location.origin}/webhooks/payment/paypal</code>
                    </p>
                  </div>

                  <div className="flex items-center justify-between rounded-lg border p-4">
                    <div className="space-y-0.5">
                      <Label htmlFor="paypal_test_mode">Sandbox Mode</Label>
                      <p className="text-sm text-muted-foreground">
                        Use PayPal Sandbox for testing (use sandbox credentials)
                      </p>
                    </div>
                    <Switch
                      id="paypal_test_mode"
                      checked={paypalForm.data.config.test_mode}
                      onCheckedChange={(checked) => paypalForm.setData('config', {
                        ...paypalForm.data.config,
                        test_mode: checked,
                      })}
                    />
                  </div>

                  <div className="flex justify-end pt-4">
                    <Button type="submit" disabled={paypalForm.processing}>
                      <Settings className="mr-2 h-4 w-4" />
                      Save PayPal Configuration
                    </Button>
                  </div>
                </CardContent>
              </Card>
            </form>
          </TabsContent>

          {/* Flutterwave Configuration */}
          <TabsContent value="flutterwave">
            <form onSubmit={handleFlutterwaveSubmit}>
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <CreditCard className="h-5 w-5" />
                    Flutterwave Configuration
                  </CardTitle>
                  <CardDescription>
                    Configure Flutterwave payment gateway for processing payments across Africa, Europe, and globally
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  <Alert>
                    <AlertCircle className="h-4 w-4" />
                    <AlertDescription>
                      Get your Flutterwave credentials from{' '}
                      <a
                        href="https://dashboard.flutterwave.com/settings/apis"
                        target="_blank"
                        rel="noopener noreferrer"
                        className="font-medium underline"
                      >
                        Flutterwave Dashboard
                      </a>
                      . Use V3 API keys. Set webhook URL to:{' '}
                      <code className="text-xs bg-muted px-1 rounded">{window.location.origin}/webhooks/payment/flutterwave</code>
                    </AlertDescription>
                  </Alert>

                  <div className="space-y-2">
                    <Label htmlFor="flutterwave_public_key">Public Key</Label>
                    <Input
                      id="flutterwave_public_key"
                      type="text"
                      value={flutterwaveForm.data.config.public_key}
                      onChange={(e) => flutterwaveForm.setData('config', {
                        ...flutterwaveForm.data.config,
                        public_key: e.target.value,
                      })}
                      placeholder="FLWPUBK_TEST-..."
                    />
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="flutterwave_secret_key">Secret Key</Label>
                    <Input
                      id="flutterwave_secret_key"
                      type="password"
                      value={flutterwaveForm.data.config.secret_key}
                      onChange={(e) => flutterwaveForm.setData('config', {
                        ...flutterwaveForm.data.config,
                        secret_key: e.target.value,
                      })}
                      placeholder="FLWSECK_TEST-..."
                    />
                    <p className="text-xs text-muted-foreground">
                      Leave as dots to keep existing value
                    </p>
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="flutterwave_encryption_key">Encryption Key</Label>
                    <Input
                      id="flutterwave_encryption_key"
                      type="password"
                      value={flutterwaveForm.data.config.encryption_key}
                      onChange={(e) => flutterwaveForm.setData('config', {
                        ...flutterwaveForm.data.config,
                        encryption_key: e.target.value,
                      })}
                      placeholder="FLWSECK_TEST..."
                    />
                    <p className="text-xs text-muted-foreground">
                      Used for direct card charge encryption. Found in your API settings.
                    </p>
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="flutterwave_secret_hash">Secret Hash</Label>
                    <Input
                      id="flutterwave_secret_hash"
                      type="password"
                      value={flutterwaveForm.data.config.secret_hash}
                      onChange={(e) => flutterwaveForm.setData('config', {
                        ...flutterwaveForm.data.config,
                        secret_hash: e.target.value,
                      })}
                      placeholder="Your webhook secret hash..."
                    />
                    <p className="text-xs text-muted-foreground">
                      Set in Flutterwave Dashboard under Webhooks settings. Without it, payment notifications from Flutterwave are refused and a top-up is credited only when the buyer returns to this site.
                    </p>
                  </div>

                  <div className="flex items-center justify-between rounded-lg border p-4">
                    <div className="space-y-0.5">
                      <Label htmlFor="flutterwave_test_mode">Test Mode</Label>
                      <p className="text-sm text-muted-foreground">
                        Enable test mode to use test API keys
                      </p>
                    </div>
                    <Switch
                      id="flutterwave_test_mode"
                      checked={flutterwaveForm.data.config.test_mode}
                      onCheckedChange={(checked) => flutterwaveForm.setData('config', {
                        ...flutterwaveForm.data.config,
                        test_mode: checked,
                      })}
                    />
                  </div>

                  <div className="flex justify-end pt-4">
                    <Button type="submit" disabled={flutterwaveForm.processing}>
                      <Settings className="mr-2 h-4 w-4" />
                      Save Flutterwave Configuration
                    </Button>
                  </div>
                </CardContent>
              </Card>
            </form>
          </TabsContent>

          {/* Cryptomus Configuration */}
          <TabsContent value="cryptomus">
            <form onSubmit={handleCryptomusSubmit}>
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <Coins className="h-5 w-5" />
                    Cryptomus Configuration
                  </CardTitle>
                  <CardDescription>
                    Configure Cryptomus cryptocurrency payment gateway for accepting crypto payments
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  <Alert>
                    <AlertCircle className="h-4 w-4" />
                    <AlertDescription>
                      Get your Cryptomus credentials from{' '}
                      <a
                        href="https://app.cryptomus.com/settings/api"
                        target="_blank"
                        rel="noopener noreferrer"
                        className="font-medium underline"
                      >
                        Cryptomus Dashboard
                      </a>
                      . Set webhook URL to:{' '}
                      <code className="text-xs bg-muted px-1 rounded">{window.location.origin}/webhooks/payment/cryptomus</code>
                    </AlertDescription>
                  </Alert>

                  <div className="space-y-2">
                    <Label htmlFor="cryptomus_merchant_uuid">Merchant UUID</Label>
                    <Input
                      id="cryptomus_merchant_uuid"
                      type="text"
                      value={cryptomusForm.data.config.merchant_uuid}
                      onChange={(e) => cryptomusForm.setData('config', {
                        ...cryptomusForm.data.config,
                        merchant_uuid: e.target.value,
                      })}
                      placeholder="Your Cryptomus Merchant UUID..."
                    />
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="cryptomus_payment_key">Payment API Key</Label>
                    <Input
                      id="cryptomus_payment_key"
                      type="password"
                      value={cryptomusForm.data.config.payment_key}
                      onChange={(e) => cryptomusForm.setData('config', {
                        ...cryptomusForm.data.config,
                        payment_key: e.target.value,
                      })}
                      placeholder="Your Cryptomus Payment API Key..."
                    />
                    <p className="text-xs text-muted-foreground">
                      Leave as dots to keep existing value
                    </p>
                  </div>

                  <div className="flex items-center justify-between rounded-lg border p-4">
                    <div className="space-y-0.5">
                      <Label htmlFor="cryptomus_test_mode">Test Mode</Label>
                      <p className="text-sm text-muted-foreground">
                        Enable test mode to use sandbox credentials
                      </p>
                    </div>
                    <Switch
                      id="cryptomus_test_mode"
                      checked={cryptomusForm.data.config.test_mode}
                      onCheckedChange={(checked) => cryptomusForm.setData('config', {
                        ...cryptomusForm.data.config,
                        test_mode: checked,
                      })}
                    />
                  </div>

                  <div className="flex justify-end pt-4">
                    <Button type="submit" disabled={cryptomusForm.processing}>
                      <Settings className="mr-2 h-4 w-4" />
                      Save Cryptomus Configuration
                    </Button>
                  </div>
                </CardContent>
              </Card>
            </form>
          </TabsContent>

          {/* Mail Configuration */}
          <TabsContent value="mail">
            <form onSubmit={handleMailSubmit}>
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <Mail className="h-5 w-5" />
                    Mail Configuration
                  </CardTitle>
                  <CardDescription>
                    Configure SMTP settings for sending emails
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="grid grid-cols-2 gap-4">
                    <div className="space-y-2">
                      <Label htmlFor="mail_mailer">Mailer</Label>
                      <Select
                        value={mailForm.data.config.mailer}
                        onValueChange={(value) => mailForm.setData('config', {
                          ...mailForm.data.config,
                          mailer: value,
                        })}
                      >
                        <SelectTrigger>
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="log">Log (Testing)</SelectItem>
                          <SelectItem value="smtp">SMTP</SelectItem>
                          <SelectItem value="sendmail">Sendmail</SelectItem>
                          <SelectItem value="mailgun">Mailgun</SelectItem>
                          <SelectItem value="ses">Amazon SES</SelectItem>
                        </SelectContent>
                      </Select>
                      <p className="text-xs text-muted-foreground">
                        Use "Log" for testing without sending real emails
                      </p>
                    </div>

                    <div className="space-y-2">
                      <Label htmlFor="mail_encryption">Encryption</Label>
                      <Select
                        value={mailForm.data.config.encryption}
                        onValueChange={(value) => mailForm.setData('config', {
                          ...mailForm.data.config,
                          encryption: value,
                        })}
                        disabled={mailForm.data.config.mailer === 'log'}
                      >
                        <SelectTrigger>
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="tls">TLS (Recommended)</SelectItem>
                          <SelectItem value="ssl">SSL</SelectItem>
                          <SelectItem value="none">None</SelectItem>
                        </SelectContent>
                      </Select>
                      <p className="text-xs text-muted-foreground">
                        Use TLS for port 587, SSL for port 465
                      </p>
                    </div>
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                    <div className="space-y-2">
                      <Label htmlFor="mail_host">SMTP Host</Label>
                      <Input
                        id="mail_host"
                        type="text"
                        value={mailForm.data.config.host}
                        onChange={(e) => mailForm.setData('config', {
                          ...mailForm.data.config,
                          host: e.target.value,
                        })}
                        placeholder="smtp.gmail.com"
                        disabled={mailForm.data.config.mailer === 'log'}
                      />
                      <p className="text-xs text-muted-foreground">
                        SMTP server hostname (e.g., smtp.gmail.com, smtp.mailtrap.io)
                      </p>
                    </div>

                    <div className="space-y-2">
                      <Label htmlFor="mail_port">SMTP Port</Label>
                      <Input
                        id="mail_port"
                        type="number"
                        value={mailForm.data.config.port}
                        onChange={(e) => mailForm.setData('config', {
                          ...mailForm.data.config,
                          port: parseInt(e.target.value) || 2525,
                        })}
                        placeholder="587"
                        disabled={mailForm.data.config.mailer === 'log'}
                      />
                      <p className="text-xs text-muted-foreground">
                        Common: 587 (TLS), 465 (SSL), 25 (plain)
                      </p>
                    </div>
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="mail_username">Username</Label>
                    <Input
                      id="mail_username"
                      type="text"
                      value={mailForm.data.config.username === 'null' ? '' : mailForm.data.config.username}
                      onChange={(e) => mailForm.setData('config', {
                        ...mailForm.data.config,
                        username: e.target.value || 'null',
                      })}
                      placeholder="your-email@gmail.com"
                      disabled={mailForm.data.config.mailer === 'log'}
                    />
                    <p className="text-xs text-muted-foreground">
                      SMTP authentication username (leave empty for none)
                    </p>
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="mail_password">Password</Label>
                    <Input
                      id="mail_password"
                      type="password"
                      value={mailForm.data.config.password === '••••••••' || mailForm.data.config.password === 'null' ? '' : mailForm.data.config.password}
                      onChange={(e) => mailForm.setData('config', {
                        ...mailForm.data.config,
                        password: e.target.value || '••••••••',
                      })}
                      placeholder="Enter new password"
                      disabled={mailForm.data.config.mailer === 'log'}
                    />
                    <p className="text-xs text-muted-foreground">
                      Leave empty to keep existing password
                    </p>
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                    <div className="space-y-2">
                      <Label htmlFor="mail_from_address">From Address</Label>
                      <Input
                        id="mail_from_address"
                        type="email"
                        value={mailForm.data.config.from_address}
                        onChange={(e) => mailForm.setData('config', {
                          ...mailForm.data.config,
                          from_address: e.target.value,
                        })}
                        placeholder="noreply@example.com"
                      />
                    </div>

                    <div className="space-y-2">
                      <Label htmlFor="mail_from_name">From Name</Label>
                      <Input
                        id="mail_from_name"
                        type="text"
                        value={mailForm.data.config.from_name}
                        onChange={(e) => mailForm.setData('config', {
                          ...mailForm.data.config,
                          from_name: e.target.value,
                        })}
                        placeholder="Teleman AI"
                      />
                    </div>
                  </div>

                  {/* Test Email Section */}
                  <div className="border-t pt-4 mt-4">
                    <Label htmlFor="test_email">Test Email</Label>
                    <div className="flex gap-2 mt-2">
                      <Input
                        id="test_email"
                        type="email"
                        value={testEmail}
                        onChange={(e) => setTestEmail(e.target.value)}
                        placeholder="test@example.com"
                        className="flex-1"
                      />
                      <Button
                        type="button"
                        variant="outline"
                        onClick={handleTestEmail}
                        disabled={!testEmail || testEmailSending}
                      >
                        <Send className="mr-2 h-4 w-4" />
                        {testEmailSending ? 'Sending...' : 'Send Test'}
                      </Button>
                    </div>
                    {testEmailResult && (
                      <Alert className={`mt-2 ${testEmailResult.success ? 'border-green-200 bg-green-50' : ''}`} variant={testEmailResult.success ? 'default' : 'destructive'}>
                        {testEmailResult.success ? (
                          <CheckCircle2 className="h-4 w-4 text-green-600" />
                        ) : (
                          <AlertCircle className="h-4 w-4" />
                        )}
                        <AlertDescription className={testEmailResult.success ? 'text-green-800' : ''}>
                          {testEmailResult.message}
                        </AlertDescription>
                      </Alert>
                    )}
                  </div>

                  <div className="flex justify-end pt-4">
                    <Button type="submit" disabled={mailForm.processing}>
                      <Settings className="mr-2 h-4 w-4" />
                      Save Mail Configuration
                    </Button>
                  </div>
                </CardContent>
              </Card>
            </form>
          </TabsContent>

          {/* Currency Configuration */}
          <TabsContent value="currency">
            <form onSubmit={handleCurrencySubmit}>
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <Coins className="h-5 w-5" />
                    {t('admin.system_config.currency_configuration')}
                  </CardTitle>
                  <CardDescription>
                    {t('admin.system_config.currency_description')}
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                  <div className="space-y-2">
                    <Label htmlFor="base_currency">{t('admin.system_config.base_currency')}</Label>
                    <Select
                      value={currencyForm.data.config.base_currency}
                      onValueChange={(value) =>
                        currencyForm.setData('config', {
                          ...currencyForm.data.config,
                          base_currency: value,
                        })
                      }
                    >
                      <SelectTrigger>
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="USD">USD - US Dollar ($)</SelectItem>
                        <SelectItem value="EUR">EUR - Euro (€)</SelectItem>
                        <SelectItem value="GBP">GBP - British Pound (£)</SelectItem>
                        <SelectItem value="JPY">JPY - Japanese Yen (¥)</SelectItem>
                        <SelectItem value="CAD">CAD - Canadian Dollar (C$)</SelectItem>
                        <SelectItem value="AUD">AUD - Australian Dollar (A$)</SelectItem>
                        <SelectItem value="CHF">CHF - Swiss Franc (CHF)</SelectItem>
                        <SelectItem value="INR">INR - Indian Rupee (₹)</SelectItem>
                        <SelectItem value="BRL">BRL - Brazilian Real (R$)</SelectItem>
                        <SelectItem value="NGN">NGN - Nigerian Naira (₦)</SelectItem>
                        <SelectItem value="GHS">GHS - Ghanaian Cedi (GH₵)</SelectItem>
                        <SelectItem value="ZAR">ZAR - South African Rand (R)</SelectItem>
                        <SelectItem value="KES">KES - Kenyan Shilling (KSh)</SelectItem>
                        <SelectItem value="EGP">EGP - Egyptian Pound (E£)</SelectItem>
                        <SelectItem value="AED">AED - UAE Dirham (د.إ)</SelectItem>
                        <SelectItem value="SAR">SAR - Saudi Riyal (﷼)</SelectItem>
                        <SelectItem value="MXN">MXN - Mexican Peso (MX$)</SelectItem>
                        <SelectItem value="SGD">SGD - Singapore Dollar (S$)</SelectItem>
                        <SelectItem value="CNY">CNY - Chinese Yuan (¥)</SelectItem>
                      </SelectContent>
                    </Select>
                    <p className="text-sm text-muted-foreground">
                      {t('admin.system_config.base_currency_help')}
                    </p>
                  </div>

                  {/* Exchange Rate Mode */}
                  <div className="space-y-3">
                    <Label>{t('admin.system_config.exchange_rate_mode')}</Label>
                    <RadioGroup
                      value={currencyForm.data.config.exchange_rate_mode}
                      onValueChange={(value) =>
                        currencyForm.setData('config', {
                          ...currencyForm.data.config,
                          exchange_rate_mode: value,
                        })
                      }
                      className="space-y-2"
                    >
                      <div className="flex items-start space-x-3 rounded-lg border p-3">
                        <RadioGroupItem value="auto" id="rate-auto" className="mt-0.5" />
                        <div>
                          <Label htmlFor="rate-auto" className="font-medium cursor-pointer">
                            {t('admin.system_config.exchange_rate_auto')}
                          </Label>
                          <p className="text-sm text-muted-foreground">
                            {t('admin.system_config.exchange_rate_auto_description')}
                          </p>
                        </div>
                      </div>
                      <div className="flex items-start space-x-3 rounded-lg border p-3">
                        <RadioGroupItem value="manual" id="rate-manual" className="mt-0.5" />
                        <div>
                          <Label htmlFor="rate-manual" className="font-medium cursor-pointer">
                            {t('admin.system_config.exchange_rate_manual')}
                          </Label>
                          <p className="text-sm text-muted-foreground">
                            {t('admin.system_config.exchange_rate_manual_description')}
                          </p>
                        </div>
                      </div>
                    </RadioGroup>
                  </div>

                  {/* Manual Rates Input */}
                  {currencyForm.data.config.exchange_rate_mode === 'manual' && (() => {
                    const baseCurrency = currencyForm.data.config.base_currency;
                    const currencies = [
                      { code: 'USD', name: 'US Dollar', symbol: '$' },
                      { code: 'EUR', name: 'Euro', symbol: '€' },
                      { code: 'GBP', name: 'British Pound', symbol: '£' },
                      { code: 'JPY', name: 'Japanese Yen', symbol: '¥' },
                      { code: 'CAD', name: 'Canadian Dollar', symbol: 'C$' },
                      { code: 'AUD', name: 'Australian Dollar', symbol: 'A$' },
                      { code: 'CHF', name: 'Swiss Franc', symbol: 'CHF' },
                      { code: 'INR', name: 'Indian Rupee', symbol: '₹' },
                      { code: 'BRL', name: 'Brazilian Real', symbol: 'R$' },
                      { code: 'NGN', name: 'Nigerian Naira', symbol: '₦' },
                      { code: 'GHS', name: 'Ghanaian Cedi', symbol: 'GH₵' },
                      { code: 'ZAR', name: 'South African Rand', symbol: 'R' },
                      { code: 'KES', name: 'Kenyan Shilling', symbol: 'KSh' },
                      { code: 'EGP', name: 'Egyptian Pound', symbol: 'E£' },
                      { code: 'AED', name: 'UAE Dirham', symbol: 'د.إ' },
                      { code: 'SAR', name: 'Saudi Riyal', symbol: '﷼' },
                      { code: 'MXN', name: 'Mexican Peso', symbol: 'MX$' },
                      { code: 'SGD', name: 'Singapore Dollar', symbol: 'S$' },
                      { code: 'CNY', name: 'Chinese Yuan', symbol: '¥' },
                    ].filter(c => c.code !== baseCurrency);

                    const manualRates = currencyForm.data.config.manual_rates?.[baseCurrency] || {};

                    const updateRate = (currency: string, value: string) => {
                      const numVal = parseFloat(value);
                      const updatedRates = {
                        ...currencyForm.data.config.manual_rates,
                        [baseCurrency]: {
                          ...(currencyForm.data.config.manual_rates?.[baseCurrency] || {}),
                          [currency]: isNaN(numVal) ? 0 : numVal,
                        },
                      };
                      currencyForm.setData('config', {
                        ...currencyForm.data.config,
                        manual_rates: updatedRates,
                      });
                    };

                    const handleFetchRates = async () => {
                      setFetchingRates(true);
                      try {
                        const response = await window.axios.get('/admin/system-config/live-rates', {
                          params: { base_currency: baseCurrency },
                        });
                        if (response.data.success) {
                          const apiRates: Record<string, number> = {};
                          currencies.forEach(c => {
                            if (response.data.rates[c.code]) {
                              apiRates[c.code] = parseFloat(response.data.rates[c.code].toFixed(6));
                            }
                          });
                          currencyForm.setData('config', {
                            ...currencyForm.data.config,
                            manual_rates: {
                              ...currencyForm.data.config.manual_rates,
                              [baseCurrency]: apiRates,
                            },
                          });
                        }
                      } catch {
                        // Silently fail — user can enter rates manually
                      } finally {
                        setFetchingRates(false);
                      }
                    };

                    return (
                      <div className="space-y-4 rounded-lg border p-4">
                        <div className="flex items-center justify-between">
                          <div>
                            <h4 className="font-medium">{t('admin.system_config.manual_rates')}</h4>
                            <p className="text-sm text-muted-foreground">
                              {t('admin.system_config.manual_rates_help')}
                            </p>
                          </div>
                          <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={handleFetchRates}
                            disabled={fetchingRates}
                          >
                            <RefreshCw className={`mr-2 h-4 w-4 ${fetchingRates ? 'animate-spin' : ''}`} />
                            {t('admin.system_config.fetch_current_rates')}
                          </Button>
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                          {currencies.map(currency => (
                            <div key={currency.code} className="flex items-center gap-2">
                              <Label className="w-20 shrink-0 text-sm font-medium">
                                {currency.code}
                              </Label>
                              <Input
                                type="number"
                                step="any"
                                min="0"
                                placeholder="0.00"
                                value={manualRates[currency.code] ?? ''}
                                onChange={(e) => updateRate(currency.code, e.target.value)}
                                className="h-9"
                              />
                            </div>
                          ))}
                        </div>
                        <p className="text-xs text-muted-foreground">
                          {t('admin.system_config.rate_for_currency', { base: baseCurrency })}
                        </p>
                      </div>
                    );
                  })()}

                  <Button type="submit" disabled={currencyForm.processing}>
                    {currencyForm.processing ? t('common.saving') : t('common.save_changes')}
                  </Button>
                </CardContent>
              </Card>
            </form>
          </TabsContent>

          {/* Turnstile Configuration */}
          <TabsContent value="turnstile">
            <form onSubmit={handleTurnstileSubmit}>
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <Shield className="h-5 w-5" />
                    Cloudflare Turnstile
                  </CardTitle>
                  <CardDescription>
                    Protect login, registration, and password reset forms from bots
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  <Alert>
                    <AlertCircle className="h-4 w-4" />
                    <AlertDescription>
                      Get your Turnstile credentials from the{' '}
                      <a
                        href="https://dash.cloudflare.com/?to=/:account/turnstile"
                        target="_blank"
                        rel="noopener noreferrer"
                        className="font-medium underline"
                      >
                        Cloudflare Dashboard
                      </a>
                      . Create a widget and copy the Site Key and Secret Key.
                    </AlertDescription>
                  </Alert>

                  <div className="space-y-2">
                    <Label htmlFor="turnstile_site_key">Site Key</Label>
                    <Input
                      id="turnstile_site_key"
                      type="text"
                      value={turnstileForm.data.config.site_key}
                      onChange={(e) => turnstileForm.setData('config', {
                        ...turnstileForm.data.config,
                        site_key: e.target.value,
                      })}
                      placeholder="0x4AAAAAAC..."
                    />
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="turnstile_secret_key">Secret Key</Label>
                    <Input
                      id="turnstile_secret_key"
                      type="password"
                      value={turnstileForm.data.config.secret_key}
                      onChange={(e) => turnstileForm.setData('config', {
                        ...turnstileForm.data.config,
                        secret_key: e.target.value,
                      })}
                      placeholder="0x4AAAAAAC..."
                    />
                    <p className="text-xs text-muted-foreground">
                      Leave as dots to keep existing value
                    </p>
                  </div>

                  <div className="flex items-center justify-between rounded-lg border p-4">
                    <div className="space-y-0.5">
                      <Label htmlFor="turnstile_enabled">Enable Turnstile</Label>
                      <p className="text-sm text-muted-foreground">
                        When enabled, login, register, and forgot password forms will require Turnstile verification
                      </p>
                    </div>
                    <Switch
                      id="turnstile_enabled"
                      checked={turnstileForm.data.config.enabled}
                      onCheckedChange={(checked) => turnstileForm.setData('config', {
                        ...turnstileForm.data.config,
                        enabled: checked,
                      })}
                    />
                  </div>

                  <div className="flex justify-end pt-4">
                    <Button type="submit" disabled={turnstileForm.processing}>
                      <Settings className="mr-2 h-4 w-4" />
                      {turnstileForm.processing ? 'Saving...' : 'Save Turnstile Configuration'}
                    </Button>
                  </div>
                </CardContent>
              </Card>
            </form>
          </TabsContent>

          {/* Application Settings */}
          <TabsContent value="application">
            <form onSubmit={handleAppSubmit}>
              <Card>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <Globe className="h-5 w-5" />
                    Application Settings
                  </CardTitle>
                  <CardDescription>
                    Configure general application settings
                  </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="space-y-2">
                    <Label htmlFor="app_name">Application Name</Label>
                    <Input
                      id="app_name"
                      type="text"
                      value={appForm.data.config.name}
                      onChange={(e) => appForm.setData('config', {
                        ...appForm.data.config,
                        name: e.target.value,
                      })}
                      placeholder="Teleman AI"
                    />
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="app_logo">Light Logo <span className="text-xs font-normal text-muted-foreground">(for light backgrounds)</span></Label>
                    <div className="flex items-start gap-4">
                      {logoPreview && (
                        <div className="shrink-0 space-y-1">
                          <img
                            src={logoPreview.startsWith('blob:') || logoPreview.startsWith('http') || logoPreview.startsWith('data:') ? logoPreview : `/${logoPreview}`}
                            alt="Logo preview"
                            className="h-16 w-16 rounded border object-contain"
                          />
                          <Button type="button" variant="ghost" size="sm" className="h-7 w-16 text-xs text-destructive hover:text-destructive" onClick={handleRemoveLogo}>
                            Remove
                          </Button>
                        </div>
                      )}
                      <div className="flex-1 space-y-2">
                        <Input
                          ref={fileInputRef}
                          id="app_logo"
                          type="file"
                          accept="image/png,image/jpeg,image/jpg,image/svg+xml"
                          onChange={handleLogoChange}
                          className="cursor-pointer"
                        />
                        <p className="text-sm text-muted-foreground">
                          Upload a logo (PNG, JPG, JPEG, or SVG, max 2MB)
                        </p>
                      </div>
                    </div>
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="app_logo_dark">Dark Logo <span className="text-xs font-normal text-muted-foreground">(shown in dark mode)</span></Label>
                    <div className="flex items-start gap-4">
                      {logoDarkPreview && (
                        <div className="shrink-0 space-y-1">
                          <div className="flex h-16 w-16 items-center justify-center rounded border bg-zinc-900 p-2">
                            <img
                              src={logoDarkPreview.startsWith('blob:') || logoDarkPreview.startsWith('http') || logoDarkPreview.startsWith('data:') ? logoDarkPreview : `/${logoDarkPreview}`}
                              alt="Dark logo preview"
                              className="max-h-full max-w-full object-contain"
                            />
                          </div>
                          <Button type="button" variant="ghost" size="sm" className="h-7 w-16 text-xs text-destructive hover:text-destructive" onClick={handleRemoveLogoDark}>
                            Remove
                          </Button>
                        </div>
                      )}
                      <div className="flex-1 space-y-2">
                        <Input
                          ref={darkInputRef}
                          id="app_logo_dark"
                          type="file"
                          accept="image/png,image/jpeg,image/jpg,image/svg+xml"
                          onChange={handleLogoDarkChange}
                          className="cursor-pointer"
                        />
                        <p className="text-sm text-muted-foreground">
                          Optional. Used in dark mode (dashboard &amp; auth). Falls back to the light logo if not set.
                        </p>
                      </div>
                    </div>
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="app_favicon">Favicon</Label>
                    <div className="flex items-start gap-4">
                      {faviconPreview && (
                        <div className="shrink-0 flex items-center justify-center h-16 w-16 rounded border bg-white p-2">
                          <img 
                            src={faviconPreview.startsWith('blob:') || faviconPreview.startsWith('http') || faviconPreview.startsWith('data:') ? faviconPreview : `/${faviconPreview}`} 
                            alt="Favicon preview" 
                            className="max-h-full max-w-full object-contain"
                          />
                        </div>
                      )}
                      <div className="flex-1 space-y-2">
                        <Input
                          ref={faviconInputRef}
                          id="app_favicon"
                          type="file"
                          accept="image/x-icon,image/png,image/jpeg,image/jpg"
                          onChange={handleFaviconChange}
                          className="cursor-pointer"
                        />
                        <p className="text-sm text-muted-foreground">
                          Upload a favicon (ICO, PNG, JPG, max 1MB, recommended 32x32px)
                        </p>
                      </div>
                    </div>
                  </div>

                  {/* Logo display mode & size — per surface */}
                  <div className="space-y-3 rounded-lg border p-4">
                    <div>
                      <Label className="text-base">Logo display &amp; size</Label>
                      <p className="text-sm text-muted-foreground">Choose how the brand mark appears and its exact height on each surface.</p>
                    </div>
                    {([
                      { key: 'landing', label: 'Landing — home header & footer' },
                      { key: 'dashboard', label: 'Dashboard — sidebar' },
                      { key: 'auth', label: 'Auth — login / register' },
                    ] as const).map((s) => (
                      <div key={s.key} className="grid grid-cols-1 gap-3 sm:grid-cols-[1fr_170px_110px] sm:items-end">
                        <div className="text-sm font-medium">{s.label}</div>
                        <div className="space-y-1">
                          <Label className="text-xs text-muted-foreground">Display</Label>
                          <Select
                            value={(appForm.data.config as Record<string, unknown>)[`logo_display_${s.key}`] as string}
                            onValueChange={(v) => appForm.setData('config', { ...appForm.data.config, [`logo_display_${s.key}`]: v })}
                          >
                            <SelectTrigger><SelectValue /></SelectTrigger>
                            <SelectContent>
                              <SelectItem value="logo_text">Logo + Text</SelectItem>
                              <SelectItem value="logo_only">Logo only</SelectItem>
                              <SelectItem value="text_only">Text only</SelectItem>
                            </SelectContent>
                          </Select>
                        </div>
                        <div className="space-y-1">
                          <Label className="text-xs text-muted-foreground">Size (px)</Label>
                          <Input
                            type="number"
                            min={16}
                            max={120}
                            value={(appForm.data.config as Record<string, unknown>)[`logo_size_${s.key}`] as number}
                            onChange={(e) => appForm.setData('config', { ...appForm.data.config, [`logo_size_${s.key}`]: parseInt(e.target.value || '0', 10) })}
                          />
                        </div>
                      </div>
                    ))}
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="app_url">Application URL</Label>
                    <Input
                      id="app_url"
                      type="url"
                      value={appForm.data.config.url}
                      onChange={(e) => appForm.setData('config', {
                        ...appForm.data.config,
                        url: e.target.value,
                      })}
                      placeholder="https://example.com"
                    />
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                    <div className="space-y-2">
                      <Label htmlFor="app_timezone">Timezone</Label>
                      <Input
                        id="app_timezone"
                        type="text"
                        value={appForm.data.config.timezone}
                        onChange={(e) => appForm.setData('config', {
                          ...appForm.data.config,
                          timezone: e.target.value,
                        })}
                        placeholder="UTC"
                      />
                    </div>

                    <div className="space-y-2">
                      <Label htmlFor="app_locale">Locale</Label>
                      <Input
                        id="app_locale"
                        type="text"
                        value={appForm.data.config.locale}
                        onChange={(e) => appForm.setData('config', {
                          ...appForm.data.config,
                          locale: e.target.value,
                        })}
                        placeholder="en"
                      />
                    </div>
                  </div>

                  <div className="grid grid-cols-2 gap-4">
                    <div className="space-y-2">
                      <Label htmlFor="app_environment">Environment</Label>
                      <Select
                        value={appForm.data.config.environment}
                        onValueChange={(value) => appForm.setData('config', {
                          ...appForm.data.config,
                          environment: value,
                        })}
                      >
                        <SelectTrigger>
                          <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="production">Production</SelectItem>
                          <SelectItem value="staging">Staging</SelectItem>
                          <SelectItem value="local">Local</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>

                    <div className="flex items-center space-x-2 pt-8">
                      <Switch
                        id="app_debug"
                        checked={appForm.data.config.debug}
                        onCheckedChange={(checked) => appForm.setData('config', {
                          ...appForm.data.config,
                          debug: checked,
                        })}
                      />
                      <Label htmlFor="app_debug" className="cursor-pointer">
                        Debug Mode
                      </Label>
                    </div>
                  </div>

                  <Alert variant="destructive">
                    <AlertCircle className="h-4 w-4" />
                    <AlertDescription>
                      Debug mode should be disabled in production environments
                    </AlertDescription>
                  </Alert>

                  <div className="flex justify-end pt-4">
                    <Button type="submit" disabled={appForm.processing}>
                      <Settings className="mr-2 h-4 w-4" />
                      Save Application Settings
                    </Button>
                  </div>
                </CardContent>
              </Card>
            </form>
          </TabsContent>
          </div>
        </Tabs>
      </div>
    </AppLayout>
  );
}
