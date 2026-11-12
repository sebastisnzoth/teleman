import AppLayout from '@/layouts/app-layout';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Head } from '@inertiajs/react';
import { CreditCard, Wallet, AlertCircle, Check } from 'lucide-react';
import { useState, useMemo, useEffect, useCallback } from 'react';
import { PageHelp } from '@/components/page-help';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { useTranslation } from 'react-i18next';
import { useFormatCurrency } from '@/hooks/use-currency';
import { getCurrencySymbol } from '@/lib/currency';

interface PaymentGateway {
    id: string;
    name: string;
    icon: string;
    supported_currencies: string[];
}

interface Props {
    currentBalance: number;
    configError?: string;
    availableGateways: PaymentGateway[];
}

export default function TopUp({ currentBalance = 0, configError, availableGateways = [] }: Props) {
    const { t } = useTranslation();
    const { formatCurrency, baseCurrency } = useFormatCurrency();

    const [amount, setAmount] = useState('10.00');
    const [selectedGateway, setSelectedGateway] = useState(availableGateways[0]?.id || 'stripe');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(configError || null);
    const [conversionPreview, setConversionPreview] = useState<{
        converted_amount: number;
        rate: number;
    } | null>(null);
    const [loadingRate, setLoadingRate] = useState(false);

    // Get the selected gateway's supported currencies
    const selectedGatewayData = useMemo(
        () => availableGateways.find((g) => g.id === selectedGateway),
        [availableGateways, selectedGateway]
    );

    const supportedCurrencies = useMemo(
        () => selectedGatewayData?.supported_currencies || ['USD'],
        [selectedGatewayData]
    );

    // Default currency: USD if supported, otherwise first supported currency
    const [selectedCurrency, setSelectedCurrency] = useState(() => {
        const gw = availableGateways.find((g) => g.id === (availableGateways[0]?.id || 'stripe'));
        const currencies = gw?.supported_currencies || ['USD'];
        return currencies.includes('USD') ? 'USD' : currencies[0];
    });

    // When gateway changes, reset currency if current currency is not supported
    const handleGatewayChange = (gatewayId: string) => {
        setSelectedGateway(gatewayId);
        const gw = availableGateways.find((g) => g.id === gatewayId);
        const currencies = gw?.supported_currencies || ['USD'];
        if (!currencies.includes(selectedCurrency)) {
            setSelectedCurrency(currencies.includes('USD') ? 'USD' : currencies[0]);
        }
    };

    // Fetch conversion preview when currency differs from base currency
    const fetchConversionPreview = useCallback(async (amt: string, currency: string) => {
        const parsedAmount = parseFloat(amt);
        if (!parsedAmount || parsedAmount <= 0 || currency.toUpperCase() === baseCurrency.toUpperCase()) {
            setConversionPreview(null);
            return;
        }

        setLoadingRate(true);
        try {
            const response = await fetch(`/credit/exchange-rate?amount=${parsedAmount}&from=${currency}`, {
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
            });
            const data = await response.json();
            if (data.success) {
                setConversionPreview({
                    converted_amount: data.converted_amount,
                    rate: data.rate,
                });
            }
        } catch {
            setConversionPreview(null);
        } finally {
            setLoadingRate(false);
        }
    }, [baseCurrency]);

    // Debounced conversion preview
    useEffect(() => {
        const timer = setTimeout(() => {
            fetchConversionPreview(amount, selectedCurrency);
        }, 500);
        return () => clearTimeout(timer);
    }, [amount, selectedCurrency, fetchConversionPreview]);

    const symbol = getCurrencySymbol(selectedCurrency);
    const predefinedAmounts = [10, 25, 50, 100, 250, 500];

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard().url },
        { title: 'Top Up Credits', href: '/credit/top-up' },
    ];

    const helpSections = [
        {
            title: 'Top Up Credits Overview',
            content: 'Add credits to your account to use services like making calls, sending SMS messages, and purchasing phone numbers. Your credits are stored securely and can be used at any time.',
        },
        {
            title: 'Current Balance',
            content: 'Your current balance is displayed at the top. This shows how many credits you currently have available. Top up when your balance runs low to avoid service interruptions.',
        },
        {
            title: 'Preset Amounts',
            content: 'Select from preset amounts for quick top-ups. These common amounts make it easy to add credits without entering a custom value.',
        },
        {
            title: 'Custom Amount',
            content: 'Enter any custom amount starting from 1.00. The custom amount field allows you to top up the exact amount you need for your specific use case.',
        },
        {
            title: 'Payment Process',
            content: 'When you click "Proceed to Payment", you\'ll be redirected to a secure payment processor. Complete the payment there, and credits will be added to your account immediately upon successful payment.',
        },
        {
            title: 'Payment Security',
            content: 'All payments are processed securely through your chosen payment provider. We never store your payment information. You can view all your transactions in the Credit History page.',
        },
    ];

    const handleTopUp = async () => {
        setLoading(true);
        setError(null);

        try {
            const response = await fetch('/payment/checkout', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    amount: parseFloat(amount),
                    currency: selectedCurrency,
                    gateway: selectedGateway,
                }),
            });

            const data = await response.json();

            if (data.success && data.checkout_url) {
                window.location.href = data.checkout_url;
            } else {
                setError(data.message || 'Failed to create checkout session. Please try again.');
                setLoading(false);
            }
        } catch {
            setError('An error occurred. Please try again later.');
            setLoading(false);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('credit.topup_title')} />

            <div className="mx-auto max-w-7xl space-y-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title={t('credit.topup_title')}
                        description={t('credit.topup_description')}
                    />
                    <PageHelp title={t('credit.topup_title')} sections={helpSections} />
                </div>

                {error && (
                    <Alert variant="destructive">
                        <AlertCircle className="h-4 w-4" />
                        <AlertTitle>Payment Error</AlertTitle>
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                )}

                <div className="max-w-3xl space-y-6">

                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>{t('credit.current_balance')}</CardTitle>
                                <CardDescription>{t('credit.available_credits')}</CardDescription>
                            </div>
                            <Wallet className="h-8 w-8 text-muted-foreground" />
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="text-3xl font-bold">
                            {formatCurrency(Number(currentBalance))}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('credit.select_amount')}</CardTitle>
                        <CardDescription>{t('credit.choose_amount')}</CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        {/* Payment Gateway Selection */}
                        {availableGateways.length > 1 && (
                            <div className="space-y-3">
                                <Label>{t('credit.payment_method')}</Label>
                                <RadioGroup value={selectedGateway} onValueChange={handleGatewayChange}>
                                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                        {availableGateways.map((gateway) => (
                                            <Label
                                                key={gateway.id}
                                                htmlFor={gateway.id}
                                                className={`flex items-center justify-between rounded-lg border-2 p-4 cursor-pointer transition-all ${
                                                    selectedGateway === gateway.id
                                                        ? 'border-primary bg-primary/5'
                                                        : 'border-muted hover:border-primary/50'
                                                }`}
                                            >
                                                <div className="flex items-center gap-3">
                                                    <RadioGroupItem value={gateway.id} id={gateway.id} />
                                                    <div>
                                                        <p className="font-medium">{gateway.name}</p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {t('credit.secure_payment')}
                                                        </p>
                                                    </div>
                                                </div>
                                                {selectedGateway === gateway.id && (
                                                    <Check className="h-5 w-5 text-primary" />
                                                )}
                                            </Label>
                                        ))}
                                    </div>
                                </RadioGroup>
                            </div>
                        )}

                        {/* Currency Selection */}
                        {supportedCurrencies.length > 1 && (
                            <div className="space-y-2">
                                <Label>{t('credit.currency')}</Label>
                                <Select value={selectedCurrency} onValueChange={setSelectedCurrency}>
                                    <SelectTrigger className="w-full sm:w-48">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {supportedCurrencies.map((currency) => (
                                            <SelectItem key={currency} value={currency}>
                                                {getCurrencySymbol(currency)} {currency}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        )}

                        <div className="grid grid-cols-3 gap-3">
                            {predefinedAmounts.map((presetAmount) => (
                                <Button
                                    key={presetAmount}
                                    variant={parseFloat(amount) === presetAmount ? 'default' : 'outline'}
                                    onClick={() => setAmount(presetAmount.toFixed(2))}
                                >
                                    {symbol}{presetAmount}
                                </Button>
                            ))}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="custom-amount">{t('credit.custom_amount')}</Label>
                            <div className="relative">
                                <span className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground">
                                    {symbol}
                                </span>
                                <Input
                                    id="custom-amount"
                                    type="number"
                                    step="0.01"
                                    min="1"
                                    value={amount}
                                    onChange={(e) => setAmount(e.target.value)}
                                    className="pl-7"
                                />
                            </div>
                        </div>

                        <div className="border-t pt-4">
                            <div className="flex items-center justify-between text-lg font-medium mb-4">
                                <span>{t('credit.amount_to_add')}</span>
                                <span className="text-2xl">{symbol}{parseFloat(amount || '0').toFixed(2)} {selectedCurrency}</span>
                            </div>

                            {/* Conversion preview when paying in non-base currency */}
                            {selectedCurrency.toUpperCase() !== baseCurrency.toUpperCase() && (
                                <div className="mb-4 rounded-lg bg-muted/50 p-3 text-sm">
                                    {loadingRate ? (
                                        <span className="text-muted-foreground">{t('credit.fetching_rate')}</span>
                                    ) : conversionPreview ? (
                                        <div className="space-y-1">
                                            <div className="flex items-center justify-between">
                                                <span className="text-muted-foreground">{t('credit.you_will_receive')}</span>
                                                <span className="font-semibold text-green-600">
                                                    {formatCurrency(conversionPreview.converted_amount)} {t('credit.credits_label')}
                                                </span>
                                            </div>
                                            <div className="flex items-center justify-between text-xs text-muted-foreground">
                                                <span>{t('credit.exchange_rate_label')}</span>
                                                <span>1 {selectedCurrency} = {conversionPreview.rate.toFixed(4)} {baseCurrency}</span>
                                            </div>
                                        </div>
                                    ) : null}
                                </div>
                            )}

                            <Button
                                size="lg"
                                className="w-full"
                                onClick={handleTopUp}
                                disabled={loading || !amount || parseFloat(amount) < 1}
                            >
                                <CreditCard className="mr-2 h-5 w-5" />
                                {t('credit.proceed_payment')}
                            </Button>
                        </div>

                        <p className="text-sm text-muted-foreground text-center">
                            {t('credit.payment_redirect')}
                        </p>
                    </CardContent>
                </Card>
                </div>
            </div>
        </AppLayout>
    );
}
