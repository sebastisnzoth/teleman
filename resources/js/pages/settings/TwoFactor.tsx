import AppLayout from '@/layouts/app-layout';
import { useTranslation } from 'react-i18next';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, router, useForm } from '@inertiajs/react';
import { Shield, Smartphone, Key, CheckCircle } from 'lucide-react';
import { useState } from 'react';
import { PageHelp } from '@/components/page-help';

interface Props {
    twoFactorEnabled: boolean;
    qrCode?: string;
    recoveryCodes?: string[];
}

export default function TwoFactor({ twoFactorEnabled = false, qrCode, recoveryCodes }: Props) {
  const { t } = useTranslation();
    const [showRecoveryCodes, setShowRecoveryCodes] = useState(false);
    const { data, setData, post, processing } = useForm({
        code: '',
    });

    const handleEnable = () => {
        post('/user/two-factor-authentication');
    };

    const handleDisable = () => {
        if (confirm('Are you sure you want to disable two-factor authentication?')) {
            router.delete('/user/two-factor-authentication', {});
        }
    };

    const handleConfirm = (e: React.FormEvent) => {
        e.preventDefault();
        post('/user/confirmed-two-factor-authentication');
    };

    const helpSections = [
        {
            title: 'What is Two-Factor Authentication?',
            content: '2FA adds an extra layer of security by requiring both your password and a code from your phone to log in. This protects your account even if someone knows your password.',
        },
        {
            title: 'Setting Up 2FA',
            content: 'Click "Enable Two-Factor Authentication" to get started. Scan the QR code with an authenticator app like Google Authenticator or Authy, then enter the 6-digit code to confirm.',
        },
        {
            title: 'Recovery Codes',
            content: 'Save your recovery codes in a secure place. If you lose access to your authenticator app, these codes can be used to log in and disable 2FA.',
        },
        {
            title: 'Using 2FA',
            content: 'After enabling 2FA, you\'ll need to enter a code from your authenticator app each time you log in. The code changes every 30 seconds.',
        },
    ];

    return (
        <AppLayout>
            <Head title={t('settings.twofactor_authentication')} />

            <div className="space-y-6 max-w-3xl mx-auto">
                <div className="flex items-center justify-between">
                    <Heading
                        title={t('settings.twofactor_authentication')}
                        description={t('settings.add_an_additional_layer_of_security_to_your_accoun')}
                    />
                    <PageHelp title={t('settings.twofactor_authentication_help')} sections={helpSections} />
                </div>

                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle className="flex items-center gap-2">
                                    <Shield className="h-5 w-5" />
                                    Two-Factor Status
                                </CardTitle>
                                <CardDescription>
                                    {twoFactorEnabled 
                                        ? 'Your account is protected with two-factor authentication' 
                                        : 'Two-factor authentication is currently disabled'}
                                </CardDescription>
                            </div>
                            {twoFactorEnabled ? (
                                <CheckCircle className="h-8 w-8 text-green-500" />
                            ) : (
                                <Shield className="h-8 w-8 text-muted-foreground" />
                            )}
                        </div>
                    </CardHeader>
                    <CardContent>
                        {!twoFactorEnabled ? (
                            <Button onClick={handleEnable} disabled={processing}>
                                <Smartphone className="mr-2 h-4 w-4" />
                                Enable Two-Factor Authentication
                            </Button>
                        ) : (
                            <div className="space-y-4">
                                <Button 
                                    variant="destructive" 
                                    onClick={handleDisable}
                                    disabled={processing}
                                >
                                    Disable Two-Factor Authentication
                                </Button>
                                
                                <Button 
                                    variant="outline" 
                                    onClick={() => setShowRecoveryCodes(!showRecoveryCodes)}
                                >
                                    <Key className="mr-2 h-4 w-4" />
                                    {showRecoveryCodes ? 'Hide' : 'Show'} Recovery Codes
                                </Button>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {qrCode && !twoFactorEnabled && (
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('settings.setup_instructions')}</CardTitle>
                            <CardDescription>
                                Scan the QR code with your authenticator app
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-6">
                            <div className="flex justify-center p-4 bg-white rounded-lg">
                                {/* Render QR code SVG safely by parsing and re-rendering only SVG elements */}
                                <div ref={(el) => {
                                    if (el && qrCode) {
                                        el.innerHTML = '';
                                        const parser = new DOMParser();
                                        const doc = parser.parseFromString(qrCode, 'image/svg+xml');
                                        const svg = doc.querySelector('svg');
                                        if (svg) {
                                            el.appendChild(document.importNode(svg, true));
                                        }
                                    }
                                }} />
                            </div>

                            <form onSubmit={handleConfirm} className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="code">Verification Code</Label>
                                    <Input
                                        id="code"
                                        type="text"
                                        placeholder="Enter the 6-digit code"
                                        value={data.code}
                                        onChange={(e) => setData('code', e.target.value)}
                                        maxLength={6}
                                    />
                                </div>
                                <Button type="submit" disabled={processing}>
                                    Confirm Two-Factor Authentication
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                )}

                {showRecoveryCodes && recoveryCodes && recoveryCodes.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('settings.recovery_codes')}</CardTitle>
                            <CardDescription>
                                Store these codes in a safe place. Each code can only be used once.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-2 gap-2 p-4 bg-muted rounded-lg font-mono text-sm">
                                {recoveryCodes.map((code, index) => (
                                    <div key={index} className="p-2 bg-background rounded">
                                        {code}
                                    </div>
                                ))}
                            </div>
                            <p className="text-sm text-muted-foreground mt-4">
                                Make sure to save these codes securely. You'll need them if you lose access to your authenticator app.
                            </p>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
