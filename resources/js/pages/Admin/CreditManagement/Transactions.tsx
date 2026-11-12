import AppLayout from '@/layouts/app-layout';
import { useTranslation } from 'react-i18next';
import { useFormatCurrency } from '@/hooks/use-currency';
import Heading from '@/components/heading';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Head, Link } from '@inertiajs/react';
import { ArrowUpCircle, ArrowDownCircle, TrendingUp, DollarSign } from 'lucide-react';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { PageHelp } from '@/components/page-help';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';

interface User {
    id: number;
    name: string;
    email: string;
}

interface Admin {
    id: number;
    name: string;
}

interface Transaction {
    id: number;
    user: User | null;
    type: 'credit' | 'debit';
    amount: number;
    description: string;
    balance_after: number;
    created_at: string;
    reference_type?: string;
    payment_gateway?: string;
    payment_id?: string;
    status: string;
    actual_cost?: number;
    profit_amount?: number;
    profit_percentage?: number;
    service_type?: string;
    admin?: Admin | null;
}

interface ProfitStats {
    total_revenue: number;
    total_costs: number;
    total_profit: number;
    average_profit_margin: number;
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
    profitStats: ProfitStats;
    filters?: {
        user_id?: number;
        type?: string;
        status?: string;
        reference_type?: string;
        from_date?: string;
        to_date?: string;
    };
}

export default function Transactions({ transactions, profitStats, filters = {} }: Props) {
  const { t } = useTranslation();
    const { formatCurrency } = useFormatCurrency();
    const transactionArray = transactions?.data || [];

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('common.dashboard'), href: dashboard().url },
        { title: t('admin.credit_management.credit_management'), href: '/admin/credit-management' },
        { title: t('admin.credit_management.all_transactions'), href: '/admin/credit-management/transactions' },
    ];

    const helpSections = [
        {
            title: 'Transaction Overview',
            content: 'View all credit transactions across the platform with detailed profit tracking. Monitor revenue from top-ups, actual service costs, and profit margins for each transaction.',
        },
        {
            title: 'Profit Metrics',
            content: 'Track key financial metrics: Total Revenue (from customer top-ups), Total Costs (actual service expenses), Total Profit (revenue minus costs), and Average Margin (profit percentage).',
        },
        {
            title: 'Transaction Types',
            content: 'Credit transactions add funds to user accounts (top-ups). Debit transactions deduct funds when services are used (calls, SMS, etc.). Each debit transaction includes cost and profit data.',
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('admin.credit_management.all_transactions')} />

            <div className="mx-auto max-w-7xl space-y-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title={t('admin.credit_management.all_transactions')}
                        description={t('admin.credit_management.view_all_credit_transactions_and_profit_breakdown_')}
                    />
                    <PageHelp sections={helpSections} />
                </div>

                <div className="grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">{t('admin.credit_management.total_revenue')}</CardTitle>
                            <DollarSign className="h-4 w-4 text-green-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-green-600">
                                {formatCurrency(Number(profitStats.total_revenue))}
                            </div>
                            <p className="text-xs text-muted-foreground">From top-ups</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">{t('admin.credit_management.total_costs')}</CardTitle>
                            <ArrowDownCircle className="h-4 w-4 text-red-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-red-600">
                                {formatCurrency(Number(profitStats.total_costs))}
                            </div>
                            <p className="text-xs text-muted-foreground">Actual service costs</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">{t('admin.credit_management.total_profit')}</CardTitle>
                            <TrendingUp className="h-4 w-4 text-blue-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-blue-600">
                                {formatCurrency(Number(profitStats.total_profit))}
                            </div>
                            <p className="text-xs text-muted-foreground">Net profit</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">{t('admin.credit_management.avg_margin')}</CardTitle>
                            <TrendingUp className="h-4 w-4 text-purple-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-purple-600">
                                {Number(profitStats.average_profit_margin || 0).toFixed(1)}%
                            </div>
                            <p className="text-xs text-muted-foreground">Profit margin</p>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('admin.credit_management.transaction_history')}</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        {transactionArray.length === 0 ? (
                            <p className="text-center text-muted-foreground py-8 px-6">No transactions found.</p>
                        ) : (
                            <div className="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="pl-6">ID</TableHead>
                                            <TableHead>User</TableHead>
                                            <TableHead>Type</TableHead>
                                            <TableHead>Description</TableHead>
                                            <TableHead>Payment Info</TableHead>
                                            <TableHead className="text-right">Amount</TableHead>
                                            <TableHead className="text-right">Cost</TableHead>
                                            <TableHead className="text-right">Profit</TableHead>
                                            <TableHead className="text-right">Margin</TableHead>
                                            <TableHead className="pr-6">Date</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {transactionArray.map((transaction) => (
                                            <TableRow key={transaction.id}>
                                                <TableCell className="font-mono text-sm pl-6">{transaction.id}</TableCell>
                                                <TableCell>
                                                    {transaction.user ? (
                                                        <div className="min-w-[150px]">
                                                            <Link
                                                                href={`/admin/users/${transaction.user.id}`}
                                                                className="font-medium hover:underline"
                                                            >
                                                                {transaction.user.name}
                                                            </Link>
                                                            <p className="text-xs text-muted-foreground truncate">
                                                                {transaction.user.email}
                                                            </p>
                                                        </div>
                                                    ) : (
                                                        <span className="text-muted-foreground">-</span>
                                                    )}
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
                                                <TableCell className="min-w-[200px]">
                                                    <div>
                                                        <div>{transaction.description}</div>
                                                        {transaction.reference_type && (
                                                            <div className="text-xs text-muted-foreground mt-1">
                                                                {transaction.reference_type}
                                                                {transaction.service_type && ` • ${transaction.service_type}`}
                                                            </div>
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    {transaction.payment_gateway && transaction.reference_type === 'TopUp' ? (
                                                        <div className="text-sm min-w-[120px]">
                                                            <Badge variant="outline" className="mb-1">
                                                                {transaction.payment_gateway === 'razorpay' ? 'Razorpay' : 'Stripe'}
                                                            </Badge>
                                                            {transaction.payment_id && (
                                                                <div className="font-mono text-xs text-muted-foreground truncate" title={transaction.payment_id}>
                                                                    {transaction.payment_id.substring(0, 20)}...
                                                                </div>
                                                            )}
                                                        </div>
                                                    ) : (
                                                        <span className="text-xs text-muted-foreground">-</span>
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right font-medium">
                                                    <span className={transaction.type === 'credit' ? 'text-green-600' : 'text-red-600'}>
                                                        {transaction.type === 'credit' ? '+' : '-'}
                                                        {formatCurrency(Number(transaction.amount))}
                                                    </span>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    {transaction.actual_cost ? (
                                                        <span className="text-orange-600 font-medium">
                                                            {formatCurrency(Number(transaction.actual_cost))}
                                                        </span>
                                                    ) : (
                                                        <span className="text-muted-foreground">-</span>
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    {transaction.profit_amount ? (
                                                        <span className="text-blue-600 font-medium">
                                                            {formatCurrency(Number(transaction.profit_amount))}
                                                        </span>
                                                    ) : (
                                                        <span className="text-muted-foreground">-</span>
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    {transaction.profit_percentage ? (
                                                        <Badge variant="outline" className="font-medium">
                                                            {Number(transaction.profit_percentage).toFixed(1)}%
                                                        </Badge>
                                                    ) : (
                                                        <span className="text-muted-foreground">-</span>
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-sm text-muted-foreground whitespace-nowrap pr-6">
                                                    {new Date(transaction.created_at).toLocaleString()}
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
