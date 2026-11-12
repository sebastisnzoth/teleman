import { Head, useForm, usePage, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { FormEvent, useState, useEffect } from 'react';
import { PageHelp } from '@/components/page-help';
import { Badge } from '@/components/ui/badge';
import { ExternalLink, Loader2, AlertCircle, CheckCircle, Trash2, Globe, Search, Eye, EyeOff, ArrowRightLeft, Phone, PhoneOff, PauseCircle, Archive } from 'lucide-react';
import { type BreadcrumbItem } from '@/types';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Checkbox } from '@/components/ui/checkbox';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from "@/components/ui/alert-dialog";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";

interface TwilioGlobalConfig {
  id: number;
  account_sid: string;
  api_key_sid: string | null;
  twiml_app_sid: string | null;
  webhook_url: string | null;
  is_active: boolean;
  verified_at: string | null;
}

interface Country {
  iso_code: string;
  name: string;
  continent: string;
  enabled: boolean;
}

interface MigrationSummary {
  id: number;
  numbers_matched: number;
  numbers_deactivated: number;
  numbers_new: number;
  campaigns_paused: number;
  ai_agents_reset: number;
  calls_marked_legacy: number;
  sms_numbers_deactivated: number;
}

interface LatestMigration extends MigrationSummary {
  old_account_sid: string;
  new_account_sid: string;
  created_at: string;
}

interface Props {
  config: TwilioGlobalConfig | null;
  canManageTwilio: boolean;
  latestMigration?: LatestMigration | null;
}

interface PageProps extends Record<string, unknown> {
  flash?: {
    success?: string;
    error?: string;
    warning?: string;
    info?: string;
    migration?: MigrationSummary;
  };
}

export default function TwilioSettings({ config, canManageTwilio, latestMigration }: Props) {
  const { t } = useTranslation();
  const breadcrumbs: BreadcrumbItem[] = [
  {
    title: t('settings.title'), href: '/settings',
  },
  {
    title: t('settings.twilio_settings'), href: '/settings/twilio',
  },
];
  const { flash } = usePage<PageProps>().props;
  const [isRemoving, setIsRemoving] = useState(false);
  const [isSyncing, setIsSyncing] = useState(false);
  const [showMigrationDialog, setShowMigrationDialog] = useState(false);
  const [migrationData, setMigrationData] = useState<MigrationSummary | null>(null);

  // Detect migration in flash data
  useEffect(() => {
    if (flash?.migration) {
      setMigrationData(flash.migration);
      setShowMigrationDialog(true);
    }
  }, [flash?.migration]);
  const [countries, setCountries] = useState<Country[]>([]);
  const [loadingCountries, setLoadingCountries] = useState(false);
  const [savingGeo, setSavingGeo] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [geoError, setGeoError] = useState<string | null>(null);
  const [showAccountSid, setShowAccountSid] = useState(false);
  const [showApiKeySid, setShowApiKeySid] = useState(false);
  const [showTwimlAppSid, setShowTwimlAppSid] = useState(false);
  
  const { data, setData, post, processing, errors } = useForm({
    account_sid: '',
    auth_token: '',
  });

  // Mask sensitive data
  const maskString = (str: string | null, visibleChars: number = 4): string => {
    if (!str) return 'Not set';
    if (str.length <= visibleChars) return '••••••••';
    return '••••••••' + str.slice(-visibleChars);
  };

  // Load geo permissions when config exists
  useEffect(() => {
    if (config) {
      loadGeoPermissions();
    }
  }, [config]);

  const loadGeoPermissions = async () => {
    setLoadingCountries(true);
    setGeoError(null);
    try {
      const response = await window.axios.get('/settings/twilio/geo-permissions');
      setCountries(response.data.countries);
    } catch (error: any) {
      console.error('Failed to load geo permissions:', error);
      const errorMessage = error.response?.data?.error || 'Failed to load geo permissions';
      setGeoError(errorMessage);
      // If it's a 404 (no config), hide the error after a moment since the section shouldn't show anyway
      if (error.response?.status === 404) {
        setTimeout(() => setGeoError(null), 3000);
      }
    } finally {
      setLoadingCountries(false);
    }
  };

  const toggleCountry = (isoCode: string) => {
    setCountries(prev => prev.map(country => 
      country.iso_code === isoCode 
        ? { ...country, enabled: !country.enabled }
        : country
    ));
  };

  const toggleAllCountries = (enabled: boolean) => {
    setCountries(prev => prev.map(country => ({ ...country, enabled })));
  };

  const saveGeoPermissions = async () => {
    setSavingGeo(true);
    setGeoError(null);
    try {
      const enabledCountries = countries.filter(c => c.enabled).map(c => c.iso_code);
      
      const response = await window.axios.post('/settings/twilio/geo-permissions', {
        enabled_countries: enabledCountries,
      });
      
      // Show success via Inertia page props
      router.reload({ 
        only: ['flash'],
        onSuccess: () => {
          // Success message will be shown via flash
        }
      });
    } catch (error: any) {
      console.error('Failed to save geo permissions:', error);
      setGeoError(error.response?.data?.error || 'Failed to save geo permissions');
    } finally {
      setSavingGeo(false);
    }
  };

  const enableAllCountries = async () => {
    setSavingGeo(true);
    setGeoError(null);
    try {
      await window.axios.post('/settings/twilio/geo-permissions/enable-all');
      await loadGeoPermissions();
      router.reload({ 
        only: ['flash'],
        onSuccess: () => {
          // Success message will be shown via flash
        }
      });
    } catch (error: any) {
      console.error('Failed to enable all countries:', error);
      setGeoError(error.response?.data?.error || 'Failed to enable all countries');
    } finally {
      setSavingGeo(false);
    }
  };

  const filteredCountries = countries.filter(country =>
    country.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
    country.iso_code.toLowerCase().includes(searchQuery.toLowerCase())
  );

  const enabledCount = countries.filter(c => c.enabled).length;

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault();
    post('/settings/twilio/configure', {
      preserveScroll: true,
      onSuccess: () => {
        setData('auth_token', ''); // Clear password field on success
      },
    });
  };

  const handleRemove = () => {
    setIsRemoving(true);
    router.delete('/settings/twilio/remove', {
      preserveScroll: true,
      onFinish: () => {
        setIsRemoving(false);
      },
    });
  };
  
  const handleSync = () => {
    setIsSyncing(true);
    router.post('/settings/twilio/sync', {}, {
      preserveScroll: true,
      onFinish: () => {
        setIsSyncing(false);
      },
    });
  };

  const helpSections = [
    {
      title: 'Global Twilio Configuration',
      content: 'This configures Twilio for all users in the system. You only need Account SID and Auth Token from your Twilio Console. Everything else is configured automatically.',
    },
    {
      title: 'Automatic Setup',
      content: 'The system automatically creates API Keys, TwiML Applications, and configures all phone numbers. You don\'t need to manually update anything in the Twilio Console.',
    },
    {
      title: 'Sync Configuration',
      content: 'Use the "Sync Configuration" button to update TwiML App URLs and phone number configurations. This is useful if your domain changes or you want to ensure everything is up-to-date.',
    },
    {
      title: 'Inbound & Outbound Calls',
      content: 'The system automatically handles both inbound calls (external phones calling your Twilio numbers) and outbound calls (browser softphone calling external numbers).',
    },
  ];

  if (!canManageTwilio) {
    return (
      <AppLayout breadcrumbs={breadcrumbs}>
        <Head title={t('settings.twilio_settings')} />
        <div className="max-w-2xl mx-auto space-y-6">
          <Card>
            <CardContent className="p-6">
              <p className="text-muted-foreground">Only administrators can manage Twilio settings.</p>
            </CardContent>
          </Card>
        </div>
      </AppLayout>
    );
  }

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={t('settings.twilio_settings')} />

      <div className="max-w-4xl mx-auto space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold">Telephony Settings</h1>
            <p className="text-muted-foreground">Configure global Telephony integration for WebRTC calling</p>
          </div>
          <PageHelp title={t('settings.twilio_settings_help')} sections={helpSections} />
        </div>

        {/* Flash Messages */}
        {flash?.success && (
          <Alert className="border-green-200 bg-green-50 text-green-900">
            <CheckCircle className="h-4 w-4 text-green-600" />
            <AlertTitle>Success</AlertTitle>
            <AlertDescription>{flash.success}</AlertDescription>
          </Alert>
        )}

        {flash?.error && (
          <Alert variant="destructive">
            <AlertCircle className="h-4 w-4" />
            <AlertTitle>Error</AlertTitle>
            <AlertDescription>{flash.error}</AlertDescription>
          </Alert>
        )}

        {/* Recent Account Migration Alert */}
        {latestMigration && (latestMigration.numbers_deactivated > 0 || latestMigration.campaigns_paused > 0) && (
          <Alert className="border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
            <ArrowRightLeft className="h-4 w-4 text-amber-600 dark:text-amber-400" />
            <AlertTitle>{t('twilio.recent_account_migration')}</AlertTitle>
            <AlertDescription>
              {t('twilio.migration_alert_description', {
                deactivated: latestMigration.numbers_deactivated,
                paused: latestMigration.campaigns_paused,
              })}{' '}
              {latestMigration.campaigns_paused > 0 && (
                <a href="/campaigns" className="underline font-medium">
                  {t('twilio.review_affected_campaigns')}
                </a>
              )}
            </AlertDescription>
          </Alert>
        )}

        {/* Current Status */}
        {config && (
          <Card>
            <CardHeader>
              <div className="flex items-center justify-between">
                <CardTitle className="flex items-center gap-2">
                  Current Configuration
                  {config.is_active && <Badge variant="default">Active</Badge>}
                </CardTitle>
                <div className="flex gap-2">
                  <Button variant="outline" size="sm" onClick={handleSync} disabled={isSyncing}>
                    {isSyncing && <Loader2 className="h-4 w-4 mr-2 animate-spin" />}
                    Sync Configuration
                  </Button>
                  <AlertDialog>
                    <AlertDialogTrigger asChild>
                      <Button variant="destructive" size="sm">
                        <Trash2 className="h-4 w-4 mr-2" />
                        Remove
                      </Button>
                    </AlertDialogTrigger>
                    <AlertDialogContent>
                      <AlertDialogHeader>
                        <AlertDialogTitle>Remove Twilio Configuration?</AlertDialogTitle>
                        <AlertDialogDescription>
                          This will remove the current Twilio configuration. You can add a new configuration after removal.
                          This action cannot be undone.
                        </AlertDialogDescription>
                      </AlertDialogHeader>
                      <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={handleRemove} disabled={isRemoving} className="bg-red-600 text-white hover:bg-red-700">
                          {isRemoving && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                          Remove
                        </AlertDialogAction>
                      </AlertDialogFooter>
                    </AlertDialogContent>
                  </AlertDialog>
                </div>
              </div>
            </CardHeader>
            <CardContent className="space-y-2">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <div className="flex items-center justify-between mb-1">
                    <p className="text-sm text-muted-foreground">Account SID</p>
                    <Button
                      variant="ghost"
                      size="sm"
                      className="h-6 w-6 p-0"
                      onClick={() => setShowAccountSid(!showAccountSid)}
                    >
                      {showAccountSid ? <EyeOff className="h-3 w-3" /> : <Eye className="h-3 w-3" />}
                    </Button>
                  </div>
                  <p className="font-mono text-sm">
                    {showAccountSid ? config.account_sid : maskString(config.account_sid)}
                  </p>
                </div>
                <div>
                  <div className="flex items-center justify-between mb-1">
                    <p className="text-sm text-muted-foreground">API Key SID</p>
                    <Button
                      variant="ghost"
                      size="sm"
                      className="h-6 w-6 p-0"
                      onClick={() => setShowApiKeySid(!showApiKeySid)}
                    >
                      {showApiKeySid ? <EyeOff className="h-3 w-3" /> : <Eye className="h-3 w-3" />}
                    </Button>
                  </div>
                  <p className="font-mono text-sm">
                    {showApiKeySid ? (config.api_key_sid || 'Not set') : maskString(config.api_key_sid)}
                  </p>
                </div>
                <div>
                  <div className="flex items-center justify-between mb-1">
                    <p className="text-sm text-muted-foreground">TwiML App SID</p>
                    <Button
                      variant="ghost"
                      size="sm"
                      className="h-6 w-6 p-0"
                      onClick={() => setShowTwimlAppSid(!showTwimlAppSid)}
                    >
                      {showTwimlAppSid ? <EyeOff className="h-3 w-3" /> : <Eye className="h-3 w-3" />}
                    </Button>
                  </div>
                  <p className="font-mono text-sm">
                    {showTwimlAppSid ? (config.twiml_app_sid || 'Not set') : maskString(config.twiml_app_sid)}
                  </p>
                </div>
                <div>
                  <p className="text-sm text-muted-foreground">Webhook URL</p>
                  <p className="font-mono text-sm truncate">{config.webhook_url || 'Not set'}</p>
                </div>
              </div>
              {config.verified_at && (
                <p className="text-sm text-muted-foreground mt-4">
                  Verified: {new Date(config.verified_at).toLocaleString()}
                </p>
              )}
              <Alert className="mt-4">
                <AlertCircle className="h-4 w-4" />
                <AlertTitle>Automatic Configuration</AlertTitle>
                <AlertDescription>
                  The system automatically configures TwiML Apps and phone numbers. Click "Sync Configuration" to update all URLs if your domain changes or to ensure everything is up-to-date.
                </AlertDescription>
              </Alert>
            </CardContent>
          </Card>
        )}

        {/* Configuration Form */}
        <Card>
          <CardHeader>
            <CardTitle>{config ? 'Update' : 'Setup'} Twilio Configuration</CardTitle>
            <CardDescription>
              Enter your Twilio credentials. Get them from <a href="https://console.twilio.com" target="_blank" rel="noopener" className="text-primary hover:underline inline-flex items-center gap-1">Twilio Console <ExternalLink className="h-3 w-3" /></a>
            </CardDescription>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleSubmit} className="space-y-4">
              <div className="space-y-2">
                <Label htmlFor="account_sid">Account SID *</Label>
                <Input
                  id="account_sid"
                  type="password"
                  value={data.account_sid}
                  onChange={(e) => setData('account_sid', e.target.value)}
                  placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                  required
                />
                {errors.account_sid && (
                  <p className="text-sm text-destructive">{errors.account_sid}</p>
                )}
              </div>

                            <div className="space-y-2">
                <Label htmlFor="auth_token">Auth Token *</Label>
                <Input
                  id="auth_token"
                  type="password"
                  value={data.auth_token}
                  onChange={(e) => setData('auth_token', e.target.value)}
                  placeholder="Your Twilio Auth Token"
                  required
                />
                {errors.auth_token && (
                  <p className="text-sm text-destructive">{errors.auth_token}</p>
                )}
                <p className="text-xs text-muted-foreground">
                  Found in Twilio Console Dashboard. Everything else will be auto-configured.
                </p>
              </div>

              <Button type="submit" disabled={processing} className="w-full">
                {processing && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                {config ? 'Update' : 'Configure'} Twilio
              </Button>
            </form>
          </CardContent>
        </Card>

        {/* Geo Permissions Card */}
        {config && (
          <Card>
            <CardHeader>
              <div className="flex items-center justify-between">
                <div>
                  <CardTitle className="flex items-center gap-2">
                    <Globe className="h-5 w-5" />
                    Geo Permissions
                  </CardTitle>
                  <CardDescription>
                    Control which countries can make/receive calls
                  </CardDescription>
                </div>
                <Badge variant="secondary">
                  {enabledCount} of {countries.length} Countries Enabled
                </Badge>
              </div>
            </CardHeader>
            <CardContent className="space-y-4">
              {geoError && (
                <Alert variant="destructive">
                  <AlertCircle className="h-4 w-4" />
                  <AlertTitle>Error</AlertTitle>
                  <AlertDescription>{geoError}</AlertDescription>
                </Alert>
              )}
              
              {loadingCountries ? (
                <div className="flex items-center justify-center py-8">
                  <Loader2 className="h-6 w-6 animate-spin" />
                  <span className="ml-2">Loading countries...</span>
                </div>
              ) : (
                <>
                  <div className="flex gap-2">
                    <Button 
                      variant="outline" 
                      size="sm"
                      onClick={() => toggleAllCountries(true)}
                      disabled={savingGeo}
                    >
                      Select All
                    </Button>
                    <Button 
                      variant="outline" 
                      size="sm"
                      onClick={() => toggleAllCountries(false)}
                      disabled={savingGeo}
                    >
                      Deselect All
                    </Button>
                    <Button 
                      variant="outline" 
                      size="sm"
                      onClick={enableAllCountries}
                      disabled={savingGeo}
                    >
                      {savingGeo && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                      Enable All (No Restrictions)
                    </Button>
                  </div>

                  <div className="relative">
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                      placeholder="Search countries..."
                      value={searchQuery}
                      onChange={(e) => setSearchQuery(e.target.value)}
                      className="pl-9"
                    />
                  </div>

                  <div className="border rounded-lg max-h-96 overflow-y-auto">
                    <div className="divide-y">
                      {filteredCountries.map((country) => (
                        <div
                          key={country.iso_code}
                          className="flex items-center justify-between p-3 hover:bg-muted/50"
                        >
                          <div className="flex items-center space-x-3">
                            <Checkbox
                              checked={country.enabled}
                              onCheckedChange={() => toggleCountry(country.iso_code)}
                            />
                            <div>
                              <div className="font-medium">{country.name}</div>
                              <div className="text-sm text-muted-foreground">
                                {country.iso_code} • {country.continent}
                              </div>
                            </div>
                          </div>
                          {country.enabled && (
                            <Badge variant="default" className="ml-2">Enabled</Badge>
                          )}
                        </div>
                      ))}
                    </div>
                  </div>

                  <div className="flex justify-between items-center pt-2">
                    <p className="text-sm text-muted-foreground">
                      {enabledCount} of {countries.length} countries enabled
                    </p>
                    <Button 
                      onClick={saveGeoPermissions}
                      disabled={savingGeo}
                    >
                      {savingGeo && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                      Save Geo Permissions
                    </Button>
                  </div>
                </>
              )}
            </CardContent>
          </Card>
        )}
      </div>

      {/* Account Migration Summary Dialog */}
      <Dialog open={showMigrationDialog} onOpenChange={setShowMigrationDialog}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <ArrowRightLeft className="h-5 w-5" />
              {t('twilio.migration_summary')}
            </DialogTitle>
            <DialogDescription>
              {t('twilio.migration_detected')}
            </DialogDescription>
          </DialogHeader>
          {migrationData && (
            <div className="space-y-3 py-2">
              {migrationData.numbers_matched > 0 && (
                <div className="flex items-center justify-between p-2 rounded-md bg-green-50 dark:bg-green-950 text-green-900 dark:text-green-200">
                  <div className="flex items-center gap-2">
                    <Phone className="h-4 w-4 text-green-600" />
                    <span className="text-sm">{t('twilio.numbers_matched')}</span>
                  </div>
                  <Badge variant="secondary" className="bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">{migrationData.numbers_matched}</Badge>
                </div>
              )}
              {migrationData.numbers_deactivated > 0 && (
                <div className="flex items-center justify-between p-2 rounded-md bg-amber-50 dark:bg-amber-950 text-amber-900 dark:text-amber-200">
                  <div className="flex items-center gap-2">
                    <PhoneOff className="h-4 w-4 text-amber-600" />
                    <span className="text-sm">{t('twilio.numbers_deactivated')}</span>
                  </div>
                  <Badge variant="secondary" className="bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200">{migrationData.numbers_deactivated}</Badge>
                </div>
              )}
              {migrationData.numbers_new > 0 && (
                <div className="flex items-center justify-between p-2 rounded-md bg-blue-50 dark:bg-blue-950 text-blue-900 dark:text-blue-200">
                  <div className="flex items-center gap-2">
                    <Phone className="h-4 w-4 text-blue-600" />
                    <span className="text-sm">{t('twilio.numbers_new')}</span>
                  </div>
                  <Badge variant="secondary" className="bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">{migrationData.numbers_new}</Badge>
                </div>
              )}
              {migrationData.campaigns_paused > 0 && (
                <div className="flex items-center justify-between p-2 rounded-md bg-red-50 dark:bg-red-950 text-red-900 dark:text-red-200">
                  <div className="flex items-center gap-2">
                    <PauseCircle className="h-4 w-4 text-red-600" />
                    <span className="text-sm">{t('twilio.campaigns_paused')}</span>
                  </div>
                  <Badge variant="secondary" className="bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">{migrationData.campaigns_paused}</Badge>
                </div>
              )}
              {migrationData.calls_marked_legacy > 0 && (
                <div className="flex items-center justify-between p-2 rounded-md bg-muted">
                  <div className="flex items-center gap-2">
                    <Archive className="h-4 w-4 text-muted-foreground" />
                    <span className="text-sm">{t('twilio.calls_marked_legacy')}</span>
                  </div>
                  <Badge variant="secondary">{migrationData.calls_marked_legacy}</Badge>
                </div>
              )}
            </div>
          )}
          <DialogFooter className="flex-col gap-2 sm:flex-row">
            {migrationData && migrationData.campaigns_paused > 0 && (
              <Button variant="outline" asChild>
                <a href="/campaigns">{t('twilio.review_affected_campaigns')}</a>
              </Button>
            )}
            <Button onClick={() => setShowMigrationDialog(false)}>
              {t('common.close')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </AppLayout>
  );
}

