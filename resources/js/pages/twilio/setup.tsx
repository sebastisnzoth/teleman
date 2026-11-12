import AlertError from '@/components/alert-error';
import Heading from '@/components/heading';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Check, Loader2, X } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

interface TwilioSetupProps {
    credential: {
        account_sid: string;
        phone_number: string | null;
        is_active: boolean;
        verified_at: string;
    } | null;
    has_credentials: boolean;
}

export default function TwilioSetup({ credential, has_credentials }: TwilioSetupProps) {
  const { t } = useTranslation();
  const breadcrumbs: BreadcrumbItem[] = [
    { title: t('common.dashboard'), href: dashboard().url },
    { title: t('twilio.twilio_setup'), href: '/settings/twilio' },
];
    const [verifying, setVerifying] = useState(false);
    const [verified, setVerified] = useState(false);
    const [verificationError, setVerificationError] = useState<string | null>(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        account_sid: credential?.account_sid || '',
        auth_token: '',
        phone_number: credential?.phone_number || '',
    });

    const handleVerify = async () => {
        if (!data.account_sid || !data.auth_token) {
            return;
        }

        setVerifying(true);
        setVerificationError(null);
        setVerified(false);

        try {
            // Use axios which automatically handles CSRF token from Inertia
            const response = await window.axios.post('/twilio/verify', {
                account_sid: data.account_sid,
                auth_token: data.auth_token,
            });

            if (response.data.valid) {
                setVerified(true);
                if (response.data.phone_number) {
                    setData('phone_number', response.data.phone_number);
                }
            } else {
                setVerificationError('Invalid credentials');
            }
        } catch (error: unknown) {
            const err = error as { response?: { data?: { message?: string, errors?: { account_sid?: string[] } } } };   
            setVerificationError(
                err.response?.data?.errors?.account_sid?.[0] ||
                err.response?.data?.message ||
                'Failed to verify credentials. Please try again.'
            );
        } finally {
            setVerifying(false);
        }
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/twilio/store', {
            onSuccess: () => {
                reset('auth_token');
                setVerified(false);
            },
        });
    };

    const handleDelete = () => {
        if (confirm('Are you sure you want to remove your Twilio credentials?')) {
            router.delete('/twilio/destroy');
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('twilio.twilio_setup')} />

            <div className="mx-auto max-w-3xl space-y-6">
                <Heading title={t('twilio.twilio_configuration')} />

                <Card>
                    <CardHeader>
                        <CardTitle>{t('twilio.connect_your_twilio_account')}</CardTitle>
                        <CardDescription>
                            Enter your Twilio Account SID and Auth Token to enable voice calling features.
                            You can find these credentials in your{' '}
                            <a
                                href="https://console.twilio.com/"
                                target="_blank"
                                rel="noopener noreferrer"
                                className="text-foreground underline hover:no-underline"
                            >
                                Twilio Console
                            </a>
                            .
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-6">
                            <div className="space-y-2">
                                <Label htmlFor="account_sid">Account SID</Label>
                                <Input
                                    id="account_sid"
                                    type="text"
                                    value={data.account_sid}
                                    onChange={(e) => {
                                        setData('account_sid', e.target.value);
                                        setVerified(false);
                                    }}
                                    placeholder="AC..."
                                    className="font-mono text-sm"
                                    disabled={processing}
                                />
                                {errors.account_sid && (
                                    <p className="text-sm text-destructive">{errors.account_sid}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="auth_token">Auth Token</Label>
                                <Input
                                    id="auth_token"
                                    type="password"
                                    value={data.auth_token}
                                    onChange={(e) => {
                                        setData('auth_token', e.target.value);
                                        setVerified(false);
                                    }}
                                    placeholder="Enter your Auth Token"
                                    className="font-mono text-sm"
                                    disabled={processing}
                                />
                                {errors.auth_token && (
                                    <p className="text-sm text-destructive">{errors.auth_token}</p>
                                )}
                            </div>

                            {verificationError && (
                                <AlertError errors={[verificationError]} />
                            )}

                            {verified && (
                                <div className="flex items-center gap-2 rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200">
                                    <Check className="h-4 w-4" />
                                    <span>Credentials verified successfully!</span>
                                </div>
                            )}

                            <div className="flex items-center gap-3">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={handleVerify}
                                    disabled={!data.account_sid || !data.auth_token || verifying || processing}
                                >
                                    {verifying ? (
                                        <>
                                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                            Verifying...
                                        </>
                                    ) : verified ? (
                                        <>
                                            <Check className="mr-2 h-4 w-4" />
                                            Verified
                                        </>
                                    ) : (
                                        'Verify Credentials'
                                    )}
                                </Button>

                                <Button
                                    type="submit"
                                    disabled={!verified || processing}
                                >
                                    {processing ? (
                                        <>
                                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                            Saving...
                                        </>
                                    ) : (
                                        'Save Credentials'
                                    )}
                                </Button>

                                {has_credentials && (
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        onClick={handleDelete}
                                        disabled={processing}
                                    >
                                        <X className="mr-2 h-4 w-4" />
                                        Remove
                                    </Button>
                                )}
                            </div>
                        </form>
                    </CardContent>
                </Card>

                {credential && (
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('twilio.current_configuration')}</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            <div className="grid grid-cols-3 gap-2 text-sm">
                                <div className="text-muted-foreground">Account SID:</div>
                                <div className="col-span-2 font-mono">{credential.account_sid}</div>
                            </div>
                            {credential.phone_number && (
                                <div className="grid grid-cols-3 gap-2 text-sm">
                                    <div className="text-muted-foreground">Phone Number:</div>
                                    <div className="col-span-2 font-mono">{credential.phone_number}</div>
                                </div>
                            )}
                            <div className="grid grid-cols-3 gap-2 text-sm">
                                <div className="text-muted-foreground">Status:</div>
                                <div className="col-span-2">
                                    {credential.is_active ? (
                                        <span className="inline-flex items-center gap-1 text-green-600 dark:text-green-400">
                                            <Check className="h-3 w-3" />
                                            Active
                                        </span>
                                    ) : (
                                        <span className="text-muted-foreground">Inactive</span>
                                    )}
                                </div>
                            </div>
                            <div className="grid grid-cols-3 gap-2 text-sm">
                                <div className="text-muted-foreground">Verified:</div>
                                <div className="col-span-2">
                                    {new Date(credential.verified_at).toLocaleString()}
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>{t('twilio.how_to_get_your_twilio_credentials')}</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3 text-sm text-muted-foreground">
                        <ol className="list-inside list-decimal space-y-2">
                            <li>
                                Visit the{' '}
                                <a
                                    href="https://console.twilio.com/"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-foreground underline hover:no-underline"
                                >
                                    Twilio Console
                                </a>
                            </li>
                            <li>Sign in to your account or create a new one</li>
                            <li>On the dashboard, you'll find your <strong>Account SID</strong></li>
                            <li>Click "Show" next to <strong>Auth Token</strong> to reveal it</li>
                            <li>Copy both values and paste them above</li>
                            <li>Click "Verify Credentials" to test the connection</li>
                            <li>Once verified, click "Save Credentials" to complete setup</li>
                        </ol>
                        <p className="mt-4 rounded-md border border-border bg-muted p-3">
                            <strong>Note:</strong> Your Auth Token is encrypted and stored securely. We never share your credentials with third parties.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
