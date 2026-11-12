import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Head, Link } from '@inertiajs/react';
import { CreditCard, Wallet, TrendingUp } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { PageHelp } from '@/components/page-help';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { useTranslation } from 'react-i18next';
import { useFormatCurrency } from '@/hooks/use-currency';

interface Props {
    creditBalance: number | string | null;
}

export default function CreditIndex({ creditBalance = 0 }: Props) {
    const { t } = useTranslation();
    const { formatCurrency } = useFormatCurrency();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard().url },
        { title: 'My Credits', href: '/credit' },
    ];

    // Convert creditBalance to a number safely
    const balance = typeof creditBalance === 'number'
        ? creditBalance
        : parseFloat(creditBalance as string) || 0;

    const helpSections = [
        {
            title: t('credit.overview_title'),
            content: t('credit.overview_description'),
        },
        {
            title: t('credit.topping_up_title'),
            content: t('credit.topping_up_description'),
        },
        {
            title: t('credit.usage_title'),
            content: t('credit.usage_description'),
        },
        {
            title: t('credit.low_balance_title'),
            content: t('credit.low_balance_description'),
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('credit.title')} />

            <div className="mx-auto max-w-7xl space-y-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title={t('credit.title')}
                        description={t('credit.description')}
                    />
                    <PageHelp title="Credits Help" sections={helpSections} />
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center">
                                <Wallet className="mr-2 h-5 w-5" />
                                {t('credit.current_balance')}
                            </CardTitle>
                            <CardDescription>{t('credit.available_credits')}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="text-4xl font-bold">{formatCurrency(balance)}</div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{t('credit.top_up_credits')}</CardTitle>
                            <CardDescription>{t('credit.add_more')}</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Button asChild className="w-full">
                                <Link href="/credit/top-up">
                                    <CreditCard className="mr-2 h-4 w-4" />
                                    {t('credit.top_up_now')}
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center">
                            <TrendingUp className="mr-2 h-5 w-5" />
                            {t('credit.account_statement')}
                        </CardTitle>
                        <CardDescription>{t('credit.complete_history')}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Button variant="outline" asChild>
                            <Link href="/credit/history">{t('credit.view_history')}</Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
