import AppLayout from '@/layouts/app-layout';
import Heading from '@/components/heading';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Head } from '@inertiajs/react';
import { ArrowDownCircle, ArrowUpCircle } from 'lucide-react';
import { PageHelp } from '@/components/page-help';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTranslation } from 'react-i18next';
import { useFormatCurrency } from '@/hooks/use-currency';

interface Transaction {
    id: number;
    type: 'credit' | 'debit';
    amount: number;
    description: string;
    balance_after: number;
    created_at: string;
    reference_type?: string;
    payment_gateway?: string;
    payment_id?: string;
    status: string;
}

interface PaginatedData {
    data: Transaction[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Props {
    transactions: PaginatedData;
    currentBalance: number;
}

export default function History({ transactions, currentBalance = 0 }: Props) {
    const { t } = useTranslation();
    const { formatCurrency } = useFormatCurrency();

    const transactionArray = transactions?.data || [];

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard().url },
        { title: 'Account Statement', href: '/credit/history' },
    ];

    const helpSections = [
        {
            title: 'Account Statement Overview',
            content: 'Your account statement displays all credit transactions in chronological order - both money added (top-ups) and money spent (calls, SMS, etc.). Monitor your complete financial activity and balance changes.',
        },
        {
            title: 'Current Balance',
            content: 'Your current balance is displayed at the top of the page. This represents the total amount of credits available in your account for making calls, sending SMS, and purchasing phone numbers.',
        },
        {
            title: 'Transaction Types',
            content: 'Credit transactions (green) add funds to your account from top-ups or refunds. Debit transactions (red) deduct funds for services like calls, SMS, and phone number purchases.',
        },
        {
            title: 'Transaction Details',
            content: 'Each transaction shows the date/time, type (credit/debit), description of the activity, amount charged or added, and your balance after the transaction was processed.',
        },
        {
            title: 'Balance After',
            content: 'The "Balance After" column shows your account balance immediately after each transaction. This helps you track how your balance changes over time and verify all transactions.',
        },
        {
            title: 'Top Up Credits',
            content: 'If your balance is low, visit the Top Up page to add more credits to your account. Regular monitoring of your credit history helps prevent service interruptions.',
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('credit.history_title')} />

            <div className="mx-auto max-w-7xl space-y-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title={t('credit.history_title')}
                        description={t('credit.history_description')}
                    />
                    <PageHelp title="Account Statement Help" sections={helpSections} />
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('credit.current_balance')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="text-3xl font-bold">
                            {formatCurrency(Number(currentBalance))}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('credit.statement_details')}</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        {transactionArray.length === 0 ? (
                            <p className="text-muted-foreground text-center py-8 px-6">
                                {t('credit.no_transactions')}
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="pl-6">{t('common.date')}</TableHead>
                                            <TableHead>{t('credit.transaction_type')}</TableHead>
                                            <TableHead>{t('common.description')}</TableHead>
                                            <TableHead>{t('credit.payment_info')}</TableHead>
                                            <TableHead className="text-right">{t('common.amount')}</TableHead>
                                            <TableHead className="text-right pr-6">{t('credit.balance_after')}</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {transactionArray.map((transaction) => (
                                            <TableRow key={transaction.id}>
                                                <TableCell className="pl-6">
                                                    {new Date(transaction.created_at).toLocaleString()}
                                                </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={transaction.type === 'credit' ? 'default' : 'destructive'}
                                                    className="gap-1"
                                                >
                                                    {transaction.type === 'credit' ? (
                                                        <ArrowUpCircle className="h-3 w-3" />
                                                    ) : (
                                                        <ArrowDownCircle className="h-3 w-3" />
                                                    )}
                                                    {transaction.type}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                <div>
                                                    <div>{transaction.description}</div>
                                                    {transaction.reference_type && (
                                                        <div className="text-xs text-muted-foreground mt-1">
                                                            {transaction.reference_type}
                                                        </div>
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                {transaction.payment_gateway && transaction.reference_type === 'TopUp' ? (
                                                    <div className="text-sm">
                                                        <Badge variant="outline" className="mb-1">
                                                            {transaction.payment_gateway === 'razorpay' ? 'Razorpay' : transaction.payment_gateway === 'paystack' ? 'Paystack' : transaction.payment_gateway === 'paypal' ? 'PayPal' : 'Stripe'}
                                                        </Badge>
                                                        {transaction.payment_id && (
                                                            <div className="font-mono text-xs text-muted-foreground truncate max-w-[150px]" title={transaction.payment_id}>
                                                                {transaction.payment_id}
                                                            </div>
                                                        )}
                                                    </div>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground">-</span>
                                                )}
                                            </TableCell>
                                            <TableCell className={`text-right font-medium ${
                                                transaction.type === 'credit' ? 'text-green-600' : 'text-red-600'
                                            }`}>
                                                {transaction.type === 'credit' ? '+' : '-'}
                                                {formatCurrency(Number(transaction.amount))}
                                            </TableCell>
                                            <TableCell className="text-right font-medium pr-6">
                                                {formatCurrency(Number(transaction.balance_after))}
                                            </TableCell>
                                        </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
