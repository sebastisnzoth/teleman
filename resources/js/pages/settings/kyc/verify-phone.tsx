import { BreadcrumbItem } from '@/types';
import { useTranslation } from 'react-i18next';
import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import HeadingSmall from '@/components/heading-small';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Shield, Clock, ChevronLeft, RefreshCw } from 'lucide-react';
import { Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { useEffect, useState } from 'react';
import { router } from '@inertiajs/react';

interface Props {
    phone_number: string;
    attempts_remaining: number;
    code_expires_at: string;
}

export default function VerifyPhone({ phone_number, attempts_remaining, code_expires_at }: Props) {
  const { t } = useTranslation();
  const breadcrumbs: BreadcrumbItem[] = [
    {
        title: t('settings.title'), href: '/settings/profile',
    },
    {
        title: t('settings.kyc_verification'), href: '/settings/kyc',
    },
    {
        title: t('settings.verify_phone'), href: '/settings/kyc/verify-phone',
    },
];
    const { data, setData, post, processing, errors } = useForm({
        code: '',
    });

    const [timeRemaining, setTimeRemaining] = useState<number>(0);
    const [resendCooldown, setResendCooldown] = useState<number>(0);

    useEffect(() => {
        const expiryTime = new Date(code_expires_at).getTime();
        const updateTimer = () => {
            const now = new Date().getTime();
            const diff = Math.floor((expiryTime - now) / 1000);
            setTimeRemaining(Math.max(0, diff));
        };

        updateTimer();
        const interval = setInterval(updateTimer, 1000);
        return () => clearInterval(interval);
    }, [code_expires_at]);

    useEffect(() => {
        if (resendCooldown > 0) {
            const timer = setTimeout(() => setResendCooldown(resendCooldown - 1), 1000);
            return () => clearTimeout(timer);
        }
    }, [resendCooldown]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/settings/kyc/verify-phone');
    };

    const handleResend = () => {
        setResendCooldown(60);
        router.post('/settings/kyc/resend-code');
    };

    const formatTime = (seconds: number) => {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    };

    const maskPhoneNumber = (phone: string) => {
        if (phone.length <= 4) return phone;
        return phone.slice(0, -4).replace(/./g, '•') + phone.slice(-4);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('settings.verify_phone_number_kyc')} />

            <SettingsLayout>
                <div className="space-y-6 p-6">
                    {/* Header */}
                    <div className="flex items-start justify-between">
                        <div>
                            <Button asChild variant="ghost" size="sm" className="mb-3 -ml-2">
                                <Link href="/settings/kyc">
                                    <ChevronLeft className="mr-1 h-4 w-4" />
                                    Back to KYC Status
                                </Link>
                            </Button>
                            <HeadingSmall
                                title={t('settings.verify_phone_number')}
                                description={`Enter the code sent to ${maskPhoneNumber(phone_number)}`}
                            />
                        </div>
                    </div>

                    {/* Timer Alert */}
                    {timeRemaining > 0 ? (
                        <Alert className="border-blue-200 bg-blue-50 dark:border-blue-900/50 dark:bg-blue-900/20">
                            <Clock className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                            <AlertTitle className="text-blue-900 dark:text-blue-100">Code Expires In</AlertTitle>
                            <AlertDescription className="text-blue-800 dark:text-blue-200">
                                <div className="mt-1 text-2xl font-bold tabular-nums">
                                    {formatTime(timeRemaining)}
                                </div>
                            </AlertDescription>
                        </Alert>
                    ) : (
                        <Alert variant="destructive" className="border-red-200 dark:border-red-900/50">
                            <Clock className="h-4 w-4" />
                            <AlertTitle>Code Expired</AlertTitle>
                            <AlertDescription>
                                Your verification code has expired. Please request a new code.
                            </AlertDescription>
                        </Alert>
                    )}

                    {/* Attempts Warning */}
                    {attempts_remaining <= 2 && attempts_remaining > 0 && (
                        <Alert variant="destructive" className="border-orange-200 bg-orange-50 dark:border-orange-900/50 dark:bg-orange-900/20">
                            <AlertDescription className="text-orange-900 dark:text-orange-100">
                                ⚠️ Only {attempts_remaining} {attempts_remaining === 1 ? 'attempt' : 'attempts'} remaining
                            </AlertDescription>
                        </Alert>
                    )}

                    {/* Verification Form */}
                    <Card>
                        <CardHeader className="pb-4">
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-green-100 dark:bg-green-900/30">
                                    <Shield className="h-5 w-5 text-green-600 dark:text-green-400" />
                                </div>
                                <div>
                                    <CardTitle className="text-lg">{t('settings.enter_verification_code')}</CardTitle>
                                    <CardDescription className="mt-1">
                                        We sent a 6-digit code to {maskPhoneNumber(phone_number)}
                                    </CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <form onSubmit={handleSubmit} className="space-y-6">
                                <div className="space-y-2">
                                    <Label htmlFor="code">
                                        Verification Code <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        id="code"
                                        type="text"
                                        inputMode="numeric"
                                        pattern="[0-9]*"
                                        maxLength={6}
                                        value={data.code}
                                        onChange={(e) => {
                                            const value = e.target.value.replace(/\D/g, '');
                                            setData('code', value);
                                        }}
                                        placeholder="000000"
                                        className="text-center text-2xl font-bold tracking-widest"
                                        disabled={processing || timeRemaining === 0}
                                        autoFocus
                                        required
                                    />
                                    <InputError message={errors.code} />
                                </div>

                                <div className="flex gap-3">
                                    <Button 
                                        type="submit" 
                                        disabled={processing || timeRemaining === 0 || data.code.length !== 6} 
                                        className="flex-1"
                                    >
                                        {processing ? 'Verifying...' : 'Verify Code'}
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>

                    {/* Resend Code */}
                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <div className="space-y-1">
                                    <p className="text-sm font-medium">Didn't receive the code?</p>
                                    <p className="text-xs text-muted-foreground">
                                        Check your spam folder or request a new code
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={handleResend}
                                    disabled={resendCooldown > 0}
                                    size="sm"
                                >
                                    {resendCooldown > 0 ? (
                                        <>Wait {resendCooldown}s</>
                                    ) : (
                                        <>
                                            <RefreshCw className="mr-2 h-4 w-4" />
                                            Resend Code
                                        </>
                                    )}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Help Text */}
                    <Card>
                        <CardContent className="pt-6">
                            <div className="space-y-3 text-sm text-muted-foreground">
                                <h4 className="font-medium text-foreground">Troubleshooting</h4>
                                <ul className="space-y-2">
                                    <li>• Make sure you entered your phone number correctly</li>
                                    <li>• Check if SMS is being blocked by your carrier</li>
                                    <li>• Wait a few moments for the message to arrive</li>
                                    <li>• Try resending the code if it doesn't arrive</li>
                                </ul>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
