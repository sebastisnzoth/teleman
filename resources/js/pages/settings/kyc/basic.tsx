import { BreadcrumbItem, UserKycVerification } from '@/types';
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
import { Phone, Info, ChevronLeft } from 'lucide-react';
import { Link } from '@inertiajs/react';
import InputError from '@/components/input-error';

interface Props {
    kyc: UserKycVerification | null;
}

export default function KycBasic({ kyc }: Props) {
  const { t } = useTranslation();
  const breadcrumbs: BreadcrumbItem[] = [
    {
        title: t('settings.title'), href: '/settings/profile',
    },
    {
        title: t('settings.kyc_verification'), href: '/settings/kyc',
    },
    {
        title: t('settings.basic_verification'), href: '/settings/kyc/basic',
    },
];
    const { data, setData, post, processing, errors } = useForm({
        phone_number: kyc?.phone_number || '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/settings/kyc/basic');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('settings.basic_verification_kyc')} />

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
                                title={t('settings.phone_verification')}
                                description={t('settings.verify_your_phone_number_to_complete_kyc_verificat')}
                            />
                        </div>
                    </div>

                    {/* Info Alert */}
                <Alert className="border-blue-200 bg-blue-50 dark:border-blue-900/50 dark:bg-blue-900/20">
                    <Info className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                    <AlertTitle className="text-blue-900 dark:text-blue-100">Verification Required</AlertTitle>
                    <AlertDescription className="text-blue-800 dark:text-blue-200">
                        <p className="text-sm">
                            Verify your phone number to complete KYC verification and unlock access to all platform features.
                        </p>
                    </AlertDescription>
                </Alert>                    {/* Verification Form */}
                    <Card>
                        <CardHeader className="pb-4">
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30">
                                    <Phone className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                </div>
                                <div>
                                    <CardTitle className="text-lg">{t('settings.phone_number_verification')}</CardTitle>
                                    <CardDescription className="mt-1">
                                        Enter your phone number to receive a verification code
                                    </CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <form onSubmit={handleSubmit} className="space-y-6">
                                <div className="space-y-2">
                                    <Label htmlFor="phone_number">
                                        Phone Number <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        id="phone_number"
                                        type="tel"
                                        value={data.phone_number}
                                        onChange={(e) => setData('phone_number', e.target.value)}
                                        placeholder="+1234567890"
                                        className="font-mono"
                                        disabled={processing}
                                        required
                                    />
                                    <InputError message={errors.phone_number} />
                                    <p className="text-xs text-muted-foreground">
                                        Enter your phone number in international format (E.164), starting with +
                                    </p>
                                </div>

                                <div className="rounded-lg border border-muted bg-muted/50 p-4">
                                    <h4 className="mb-2 font-medium text-sm">Important Notes:</h4>
                                    <ul className="space-y-1 text-xs text-muted-foreground">
                                        <li>• You will receive a 6-digit verification code via SMS</li>
                                        <li>• The code expires in 10 minutes</li>
                                        <li>• You have 5 attempts to enter the correct code</li>
                                        <li>• Standard SMS rates may apply</li>
                                    </ul>
                                </div>

                                <div className="flex gap-3">
                                    <Button type="submit" disabled={processing} className="flex-1">
                                        {processing ? 'Sending Code...' : 'Send Verification Code'}
                                    </Button>
                                    <Button type="button" variant="outline" asChild>
                                        <Link href="/settings/kyc">Cancel</Link>
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>

                    {/* Privacy Notice */}
                    <Card>
                        <CardContent className="pt-6">
                            <div className="space-y-3 text-sm text-muted-foreground">
                                <h4 className="font-medium text-foreground">Privacy & Security</h4>
                                <p>
                                    Your phone number is securely stored and encrypted. We use it solely for identity verification
                                    and will never share it with third parties without your consent.
                                </p>
                                <p>
                                    By proceeding, you agree to receive SMS verification messages from our service.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
