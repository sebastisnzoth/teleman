import { Head, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { useEffect, useState } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Loader2 } from 'lucide-react';

interface Props {
    orderId: string;
    amount: number;
    currency: string;
    razorpayKey: string;
    userEmail: string;
    userName: string;
}

declare global {
    interface Window {
        Razorpay: any;
    }
}

export default function RazorpayCheckout({ orderId, amount, currency, razorpayKey, userEmail, userName }: Props) {
  const { t } = useTranslation();
  const appName = (usePage<any>().props.appName ?? usePage<any>().props.name) as string || 'App';
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        // Load Razorpay script
        const script = document.createElement('script');
        script.src = 'https://checkout.razorpay.com/v1/checkout.js';
        script.async = true;
        script.onload = () => {
            setLoading(false);
            initializeRazorpay();
        };
        script.onerror = () => {
            setError('Failed to load Razorpay. Please try again.');
            setLoading(false);
        };
        document.body.appendChild(script);

        return () => {
            document.body.removeChild(script);
        };
    }, []);

    const initializeRazorpay = () => {
        const options = {
            key: razorpayKey,
            amount: Math.round(amount * 100), // Amount in paise
            currency: currency,
            name: appName,
            description: 'Credit Top-up',
            order_id: orderId,
            prefill: {
                name: userName,
                email: userEmail,
            },
            theme: {
                color: '#7c3aed',
            },
            handler: function (response: any) {
                // Payment successful
                window.location.href = `/payment/success?session_id=${response.razorpay_order_id}&payment_id=${response.razorpay_payment_id}`;
            },
            modal: {
                ondismiss: function () {
                    // Payment cancelled
                    window.location.href = '/payment/cancel';
                },
            },
        };

        const razorpay = new window.Razorpay(options);
        razorpay.on('payment.failed', function (response: any) {
            setError('Payment failed: ' + response.error.description);
            setTimeout(() => {
                window.location.href = '/payment/cancel';
            }, 3000);
        });

        razorpay.open();
    };

    return (
        <AppLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-foreground">
                    Payment Processing
                </h2>
            }
        >
            <Head title={t('credit.processing_payment')} />

            <div className="flex min-h-[calc(100vh-200px)] items-center justify-center p-4">
                <Card className="w-full max-w-md">
                    <CardHeader className="text-center">
                        <CardTitle>{t('credit.processing_payment')}</CardTitle>
                        <CardDescription>
                            {loading ? 'Loading secure payment gateway...' : 'Redirecting to Razorpay...'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col items-center gap-4">
                        {loading && (
                            <Loader2 className="h-12 w-12 animate-spin text-primary" />
                        )}
                        {error && (
                            <div className="text-center text-sm text-destructive">
                                {error}
                            </div>
                        )}
                        <p className="text-center text-sm text-muted-foreground">
                            Amount: {currency} {Number(amount).toFixed(2)}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            If the payment window doesn't open automatically, please refresh the page.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
