import { Head, useForm, usePage, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import { FormEvent, useState } from 'react';
import { AlertCircle, AlertTriangle, CheckCircle, ExternalLink, Eye, EyeOff, Loader2, Phone, ShieldQuestion } from 'lucide-react';
import { type BreadcrumbItem } from '@/types';

type ProviderName = 'twilio' | 'plivo';

interface TwilioBranch {
  settingsUrl: string;
}

interface PlivoBranch {
  authId: string | null;
  hasAuthToken: boolean;
  verifiedAt: string | null;
  /** The Plivo Application id this installation provisioned, if any. */
  applicationId: string | null;
  /**
   * True when the Application was provisioned for a different address
   * than this installation currently uses (e.g. a temporary domain that
   * has since changed) — computed by the server from a local snapshot,
   * never by calling Plivo. False whenever nothing has been provisioned
   * yet, not just when it's still current.
   */
  urlsOutOfDate: boolean;
}

interface Props {
  provider: ProviderName | null;
  providerLabel: string | null;
  locked: boolean;
  twilio: TwilioBranch | null;
  plivo: PlivoBranch | null;
  /** The exact word the admin must type to arm the reset. Null when nothing is locked. */
  resetConfirmationWord: string | null;
  /** Where to go to pick a provider. Only sent when nothing is locked. */
  chooseUrl: string | null;
}

interface PageProps extends Record<string, unknown> {
  flash?: {
    success?: string;
    error?: string;
  };
}

/**
 * Which provider this installation is locked to, and — for Plivo — the
 * credentials for it. Renders one of three branches purely from what the
 * server sent (provider/locked/twilio/plivo); nothing here ever submits a
 * provider choice — that lives in the installer and, after a reset, on
 * Admin/Telephony/Choose.
 *
 * The danger zone at the bottom is the one place a buyer can change that
 * choice. It is shown only while a provider is actually locked, needs the
 * provider's name typed before its button does anything, and then asks
 * again in a dialog — two deliberate steps, because the reset cannot be
 * undone. Its copy has to carry one fact the admin cannot see anywhere
 * else: nothing is cancelled at the provider, so their numbers keep
 * billing until they cancel them in that account themselves.
 */
export default function TelephonySettings({
  provider,
  providerLabel,
  locked,
  twilio,
  plivo,
  resetConfirmationWord,
  chooseUrl,
}: Props) {
  const { t } = useTranslation();
  const breadcrumbs: BreadcrumbItem[] = [
    { title: t('common.dashboard'), href: '/dashboard' },
    { title: t('admin.telephony.telephony'), href: '/admin/telephony' },
  ];
  const { flash } = usePage<PageProps>().props;
  const [verifying, setVerifying] = useState(false);
  const [showAuthId, setShowAuthId] = useState(false);
  const [resetConfirmation, setResetConfirmation] = useState('');
  const [resetDialogOpen, setResetDialogOpen] = useState(false);
  const [resetting, setResetting] = useState(false);
  const [resetForce, setResetForce] = useState(false);

  // The typed word must match before the button does anything. The server
  // checks this again — this only stops an admin reaching the confirmation
  // dialog by accident.
  const resetArmed =
    resetConfirmationWord !== null &&
    resetConfirmation.trim().toLowerCase() === resetConfirmationWord.toLowerCase();

  const handleReset = () => {
    setResetting(true);
    router.post(
      '/admin/telephony/reset',
      { confirmation: resetConfirmation.trim(), force: resetForce },
      {
        onFinish: () => {
          setResetting(false);
          setResetDialogOpen(false);
          setResetConfirmation('');
          setResetForce(false);
        },
      },
    );
  };

  const { data, setData, post, processing, errors } = useForm({
    auth_id: '',
    auth_token: '',
  });

  const maskString = (str: string | null, visibleChars: number = 4): string => {
    if (!str) return t('admin.telephony.not_set');
    if (str.length <= visibleChars) return '••••••••';
    return '••••••••' + str.slice(-visibleChars);
  };

  const handleSave = (e: FormEvent) => {
    e.preventDefault();
    post('/admin/telephony/credentials', {
      preserveScroll: true,
      onSuccess: () => {
        // Never pre-filled from props (the server never sends the token
        // back) — clearing it here too means it doesn't linger in the DOM
        // after a successful save.
        setData('auth_token', '');
      },
    });
  };

  const handleVerify = () => {
    setVerifying(true);
    router.post('/admin/telephony/verify', {}, {
      preserveScroll: true,
      onFinish: () => setVerifying(false),
    });
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={t('admin.telephony.telephony_settings')} />

      <div className="max-w-4xl mx-auto space-y-6">
        <div>
          <h1 className="text-3xl font-bold">{t('admin.telephony.telephony_settings')}</h1>
          <p className="text-muted-foreground">{t('admin.telephony.page_description')}</p>
        </div>

        {flash?.success && (
          <Alert className="border-green-200 bg-green-50 text-green-900">
            <CheckCircle className="h-4 w-4 text-green-600" />
            <AlertTitle>{t('common.success')}</AlertTitle>
            <AlertDescription>{flash.success}</AlertDescription>
          </Alert>
        )}

        {flash?.error && (
          <Alert variant="destructive">
            <AlertCircle className="h-4 w-4" />
            <AlertTitle>{t('common.error')}</AlertTitle>
            <AlertDescription>{flash.error}</AlertDescription>
          </Alert>
        )}

        {!locked && (
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <ShieldQuestion className="h-5 w-5" />
                {t('admin.telephony.no_provider_locked_title')}
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <p className="text-muted-foreground">{t('admin.telephony.no_provider_locked_description')}</p>
              {chooseUrl && (
                <Button asChild>
                  <a href={chooseUrl}>{t('admin.telephony.choose_provider_button')}</a>
                </Button>
              )}
            </CardContent>
          </Card>
        )}

        {provider === 'twilio' && twilio && (
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <Phone className="h-5 w-5" />
                {t('admin.telephony.locked_to', { provider: providerLabel ?? 'Twilio' })}
                <Badge variant="default">{t('admin.telephony.locked_badge')}</Badge>
              </CardTitle>
              <CardDescription>{t('admin.telephony.twilio_description')}</CardDescription>
            </CardHeader>
            <CardContent>
              <Button asChild>
                <a href={twilio.settingsUrl}>
                  {t('admin.telephony.go_to_twilio_settings')}
                  <ExternalLink className="h-4 w-4 ml-2" />
                </a>
              </Button>
            </CardContent>
          </Card>
        )}

        {provider === 'plivo' && plivo && (
          <>
            <Card>
              <CardHeader>
                <div className="flex items-center justify-between">
                  <CardTitle className="flex items-center gap-2">
                    {t('admin.telephony.locked_to', { provider: providerLabel ?? 'Plivo' })}
                    <Badge variant="default">{t('admin.telephony.locked_badge')}</Badge>
                    {plivo.verifiedAt ? (
                      <Badge variant="secondary" className="bg-green-100 text-green-800">
                        {t('admin.telephony.verified_badge')}
                      </Badge>
                    ) : (
                      <Badge variant="outline">{t('admin.telephony.not_verified_badge')}</Badge>
                    )}
                  </CardTitle>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={handleVerify}
                    disabled={verifying || !plivo.hasAuthToken}
                  >
                    {verifying && <Loader2 className="h-4 w-4 mr-2 animate-spin" />}
                    {t('common.verify')}
                  </Button>
                </div>
              </CardHeader>
              <CardContent className="space-y-2">
                {plivo.urlsOutOfDate && (
                  <Alert className="border-amber-200 bg-amber-50 text-amber-900">
                    <AlertTriangle className="h-4 w-4 text-amber-600" />
                    <AlertTitle>{t('admin.telephony.urls_out_of_date_title')}</AlertTitle>
                    <AlertDescription>{t('admin.telephony.urls_out_of_date_description')}</AlertDescription>
                  </Alert>
                )}
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <div className="flex items-center justify-between mb-1">
                      <p className="text-sm text-muted-foreground">{t('admin.telephony.auth_id_label')}</p>
                      <Button
                        variant="ghost"
                        size="sm"
                        className="h-6 w-6 p-0"
                        onClick={() => setShowAuthId(!showAuthId)}
                      >
                        {showAuthId ? <EyeOff className="h-3 w-3" /> : <Eye className="h-3 w-3" />}
                      </Button>
                    </div>
                    <p className="font-mono text-sm">
                      {showAuthId ? (plivo.authId ?? t('admin.telephony.not_set')) : maskString(plivo.authId)}
                    </p>
                  </div>
                  <div>
                    <p className="text-sm text-muted-foreground mb-1">{t('admin.telephony.auth_token_label')}</p>
                    <p className="font-mono text-sm">
                      {plivo.hasAuthToken ? t('admin.telephony.auth_token_stored') : t('admin.telephony.not_set')}
                    </p>
                  </div>
                </div>
                {plivo.applicationId && (
                  <p className="text-xs text-muted-foreground pt-1">
                    {t('admin.telephony.application_id_label')}: <span className="font-mono">{plivo.applicationId}</span>
                  </p>
                )}
                {!plivo.hasAuthToken && (
                  <p className="text-xs text-muted-foreground pt-2">
                    {t('admin.telephony.save_before_verifying_hint')}
                  </p>
                )}
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>
                  {plivo.hasAuthToken
                    ? t('admin.telephony.update_plivo_credentials_title')
                    : t('admin.telephony.add_plivo_credentials_title')}
                </CardTitle>
                <CardDescription>
                  {t('admin.telephony.enter_credentials_description')}{' '}
                  <a
                    href="https://console.plivo.com"
                    target="_blank"
                    rel="noopener"
                    className="text-primary hover:underline inline-flex items-center gap-1"
                  >
                    {t('admin.telephony.plivo_console_link')} <ExternalLink className="h-3 w-3" />
                  </a>
                </CardDescription>
              </CardHeader>
              <CardContent>
                <form onSubmit={handleSave} className="space-y-4">
                  <div className="space-y-2">
                    <Label htmlFor="auth_id">{t('admin.telephony.auth_id_field')}</Label>
                    <Input
                      id="auth_id"
                      type="password"
                      value={data.auth_id}
                      onChange={(e) => setData('auth_id', e.target.value)}
                      placeholder="MAxxxxxxxxxxxxxxxxxxxx"
                      required
                    />
                    {errors.auth_id && <p className="text-sm text-destructive">{errors.auth_id}</p>}
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="auth_token">{t('admin.telephony.auth_token_field')}</Label>
                    <Input
                      id="auth_token"
                      type="password"
                      value={data.auth_token}
                      onChange={(e) => setData('auth_token', e.target.value)}
                      placeholder="Your Plivo Auth Token"
                      required
                    />
                    {errors.auth_token && <p className="text-sm text-destructive">{errors.auth_token}</p>}
                    <p className="text-xs text-muted-foreground">{t('admin.telephony.auth_token_hint')}</p>
                  </div>

                  <Button type="submit" disabled={processing} className="w-full">
                    {processing && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                    {t('admin.telephony.save_plivo_credentials')}
                  </Button>
                </form>
              </CardContent>
            </Card>
          </>
        )}

        {locked && resetConfirmationWord && (
          <Card className="border-destructive">
            <CardHeader>
              <CardTitle className="flex items-center gap-2 text-destructive">
                <AlertTriangle className="h-5 w-5" />
                {t('admin.telephony.danger_zone_title')}
              </CardTitle>
              <CardDescription>
                {t('admin.telephony.reset_description', { provider: providerLabel ?? resetConfirmationWord })}
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid gap-3 sm:grid-cols-2">
                <div className="rounded-md border p-3">
                  <p className="text-sm font-medium mb-1">{t('admin.telephony.reset_keeps_title')}</p>
                  <p className="text-sm text-muted-foreground">{t('admin.telephony.reset_keeps')}</p>
                </div>
                <div className="rounded-md border p-3">
                  <p className="text-sm font-medium mb-1">{t('admin.telephony.reset_deletes_title')}</p>
                  <p className="text-sm text-muted-foreground">{t('admin.telephony.reset_deletes')}</p>
                </div>
              </div>

              <Alert variant="destructive">
                <AlertCircle className="h-4 w-4" />
                <AlertTitle>{t('admin.telephony.reset_nothing_released_title')}</AlertTitle>
                <AlertDescription>
                  {t('admin.telephony.reset_nothing_released', { provider: providerLabel ?? resetConfirmationWord })}
                </AlertDescription>
              </Alert>

              <p className="text-sm text-muted-foreground">{t('admin.telephony.reset_queued_jobs_warning')}</p>

              <div className="space-y-2 pt-2 border-t">
                <Label htmlFor="reset_confirmation">
                  {t('admin.telephony.reset_confirm_label', { word: resetConfirmationWord })}
                </Label>
                <Input
                  id="reset_confirmation"
                  value={resetConfirmation}
                  onChange={(e) => setResetConfirmation(e.target.value)}
                  autoComplete="off"
                />
                <label className="flex items-start gap-2 pt-1 text-sm">
                  <input
                    type="checkbox"
                    className="mt-1"
                    checked={resetForce}
                    onChange={(e) => setResetForce(e.target.checked)}
                  />
                  <span>
                    <span className="font-medium">{t('admin.telephony.reset_force_label')}</span>
                    <span className="block text-muted-foreground">
                      {t('admin.telephony.reset_force_description')}
                    </span>
                  </span>
                </label>
                <Button
                  variant="destructive"
                  disabled={!resetArmed || resetting}
                  onClick={() => setResetDialogOpen(true)}
                >
                  {t('admin.telephony.reset_button')}
                </Button>
              </div>
            </CardContent>
          </Card>
        )}

        <AlertDialog open={resetDialogOpen} onOpenChange={setResetDialogOpen}>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>{t('admin.telephony.reset_dialog_title')}</AlertDialogTitle>
              <AlertDialogDescription>
                {t('admin.telephony.reset_dialog_description', {
                  provider: providerLabel ?? resetConfirmationWord ?? '',
                })}
              </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel disabled={resetting}>{t('common.cancel')}</AlertDialogCancel>
              <AlertDialogAction
                onClick={handleReset}
                disabled={resetting}
                className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
              >
                {resetting && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                {t('admin.telephony.reset_dialog_confirm')}
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>
      </div>
    </AppLayout>
  );
}
