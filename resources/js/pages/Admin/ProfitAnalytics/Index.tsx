import AppLayout from '@/layouts/app-layout';
import { useTranslation } from 'react-i18next';
import { useFormatCurrency } from '@/hooks/use-currency';
import Heading from '@/components/heading';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Head, router } from '@inertiajs/react';
import { PageHelp } from '@/components/page-help';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { 
    TrendingUp, 
    DollarSign, 
    Phone, 
    MessageSquare,
    Activity,
    BarChart3,
    Download,
    Calendar,
    Users,
    Globe
} from 'lucide-react';
import { useState } from 'react';
import { providerLabel, useTelephony } from '@/lib/telephony';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

interface Summary {
    total_revenue: number;
    total_cost: number;
    total_profit: number;
    avg_margin: number;
    total_transactions: number;
}

interface ServiceBreakdown {
    service_type: string;
    service_label: string;
    revenue: number;
    cost: number;
    profit: number;
    margin: number;
    count: number;
}

interface Trend {
    period: string | number;
    label: string;
    revenue: number;
    cost: number;
    profit: number;
}

interface TopCustomer {
    user_id: number;
    revenue: number;
    cost: number;
    profit: number;
    transactions: number;
    user: {
        id: number;
        name: string;
        email: string;
    };
}

interface CountryProfit {
    country: string;
    revenue: number;
    cost: number;
    profit: number;
    margin: number;
    count: number;
}

interface AccuracyMetrics {
    avg_accuracy: number;
    total_overcharged: number;
    total_undercharged: number;
    refunds_issued: number;
}

interface Transaction {
    id: number;
    user: {
        id: number;
        name: string;
        email: string;
    };
    service_type: string;
    amount: number;
    actual_cost: number;
    profit_amount: number;
    profit_percentage: number;
    created_at: string;
}

interface Filters {
    period: string;
    start_date: string;
    end_date: string;
}

interface Props {
    summary: Summary;
    profitByService: ServiceBreakdown[];
    trends: Trend[];
    topCustomers: TopCustomer[];
    profitByCountry: CountryProfit[];
    accuracyMetrics: AccuracyMetrics;
    recentTransactions: Transaction[];
    filters: Filters;
}

export default function ProfitAnalyticsIndex({ 
    summary,
    profitByService,
    trends,
    topCustomers,
    profitByCountry,
    accuracyMetrics,
    recentTransactions,
    filters
}: Props) {
  const { t } = useTranslation();
    const { formatCurrency } = useFormatCurrency();
    // Whose bill the cost side of this dashboard is. Named once a provider
    // is locked, since an admin chose it; neutral before that.
    const { provider, locked } = useTelephony();
    const providerName = locked ? providerLabel(provider) : 'your telephony provider';
    const [selectedPeriod, setSelectedPeriod] = useState(filters.period);

    const n = (v: unknown) => Number(v) || 0;
    
    const handlePeriodChange = (period: string) => {
        setSelectedPeriod(period);
        router.get('/admin/profit-analytics', { period }, { 
            preserveState: true,
            preserveScroll: true 
        });
    };

    const handleExport = () => {
        window.location.href = `/admin/profit-analytics/export?period=${selectedPeriod}`;
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('common.dashboard'), href: dashboard().url },
        { title: t('admin.profit.profit_analytics'), href: '/admin/profit-analytics' },
    ];

    const helpSections = [
        {
            title: 'Profit Analytics Overview',
            content: 'The profit analytics dashboard provides comprehensive insights into your revenue, costs, and profit margins. Track performance metrics for today and the current month, and analyze detailed breakdowns by period to understand your business profitability.',
        },
        {
            title: 'Revenue Tracking',
            content: 'Revenue represents the total amount charged to customers for calls, SMS, and phone number services. View separate revenue figures for today and the current month to track daily and monthly income trends.',
        },
        {
            title: 'Cost Analysis',
            content: `Costs are the base expenses charged by ${providerName} for services (calls, SMS, phone numbers). Understanding your costs helps you set appropriate pricing rules and maintain healthy profit margins.`,
        },
        {
            title: 'Profit Calculation',
            content: 'Profit is calculated as Revenue minus Cost. The profit amount shows your net earnings after accounting for service costs. A positive profit indicates successful monetization of your services.',
        },
        {
            title: 'Profit Margin Percentage',
            content: 'The profit margin percentage is calculated as (Profit / Revenue) × 100. This metric helps you understand what percentage of your revenue is actual profit. Higher margins indicate more efficient pricing strategies.',
        },
        {
            title: 'Period Breakdown Table',
            content: 'The breakdown table shows historical profit data grouped by period (daily or monthly). Review revenue, cost, profit, margin percentage, call count, and SMS count for each period to identify trends and optimize your pricing.',
        },
        {
            title: 'Service Volume Metrics',
            content: 'Track the number of calls and SMS messages for each period. These volume metrics help correlate activity levels with revenue and profit, allowing you to forecast future performance based on usage patterns.',
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('admin.profit.profit_analytics')} />

            <div className="mx-auto max-w-7xl space-y-6">
                {/* Header with Filters */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <Heading
                            title={t('admin.profit.profit_analytics')}
                            description={t('admin.profit.comprehensive_revenue_cost_and_profit_analysis')}
                        />
                    </div>
                    <div className="flex items-center gap-2">
                        <Select value={selectedPeriod} onValueChange={handlePeriodChange}>
                            <SelectTrigger className="w-[150px]">
                                <Calendar className="mr-2 h-4 w-4" />
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="today">Today</SelectItem>
                                <SelectItem value="week">Last Week</SelectItem>
                                <SelectItem value="month">Last Month</SelectItem>
                                <SelectItem value="year">Last Year</SelectItem>
                            </SelectContent>
                        </Select>
                        <Button onClick={handleExport} variant="outline" size="sm">
                            <Download className="mr-2 h-4 w-4" />
                            Export CSV
                        </Button>
                        <PageHelp title={t('admin.profit.profit_analytics_help')} sections={helpSections} />
                    </div>
                </div>

                {/* Summary Stats */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-5">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">{t('admin.profit.total_revenue')}</CardTitle>
                            <DollarSign className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-blue-600">
                                {formatCurrency(n(summary.total_revenue))}
                            </div>
                            <p className="text-xs text-muted-foreground mt-1">
                                From {summary.total_transactions} transactions
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">{t('admin.profit.total_cost')}</CardTitle>
                            <Activity className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-red-600">
                                {formatCurrency(n(summary.total_cost))}
                            </div>
                            <p className="text-xs text-muted-foreground mt-1">
                                {providerName} expenses
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">{t('admin.profit.net_profit')}</CardTitle>
                            <TrendingUp className="h-4 w-4 text-green-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-green-600">
                                {formatCurrency(n(summary.total_profit))}
                            </div>
                            <p className="text-xs text-muted-foreground mt-1">
                                Revenue minus cost
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">{t('admin.profit.profit_margin')}</CardTitle>
                            <BarChart3 className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {n(summary.avg_margin).toFixed(1)}%
                            </div>
                            <p className="text-xs text-muted-foreground mt-1">
                                Average margin
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">{t('admin.profit.accuracy')}</CardTitle>
                            <Activity className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-purple-600">
                                {n(accuracyMetrics.avg_accuracy).toFixed(1)}%
                            </div>
                            <p className="text-xs text-muted-foreground mt-1">
                                Cost estimation
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Service Breakdown */}
                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('admin.profit.profit_by_service_type')}</CardTitle>
                            <CardDescription>
                                Revenue breakdown by service
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {profitByService.length === 0 ? (
                                <div className="flex items-center justify-center py-8 text-center">
                                    <p className="text-sm text-muted-foreground">No service data available</p>
                                </div>
                            ) : (
                                <div className="space-y-4">
                                    {profitByService.map((service, index) => (
                                        <div key={index} className="space-y-2">
                                            <div className="flex items-center justify-between">
                                                <div className="flex items-center gap-2">
                                                    {service.service_type === 'call' && <Phone className="h-4 w-4 text-blue-500" />}
                                                    {service.service_type === 'sms' && <MessageSquare className="h-4 w-4 text-green-500" />}
                                                    {service.service_type === 'phone_number' && <DollarSign className="h-4 w-4 text-purple-500" />}
                                                    <span className="font-medium">{service.service_label}</span>
                                                </div>
                                                <span className="text-sm font-semibold text-green-600">
                                                    {formatCurrency(n(service.profit))}
                                                </span>
                                            </div>
                                            <div className="grid grid-cols-3 gap-2 text-xs text-muted-foreground">
                                                <div>
                                                    <span className="text-blue-600">{formatCurrency(n(service.revenue))}</span> revenue
                                                </div>
                                                <div>
                                                    <span className="text-red-600">{formatCurrency(n(service.cost))}</span> cost
                                                </div>
                                                <div>
                                                    <Badge variant="outline" className="text-xs">
                                                        {n(service.margin).toFixed(1)}%
                                                    </Badge>
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{t('admin.profit.top_profitable_customers')}</CardTitle>
                            <CardDescription>
                                Customers generating most profit
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {topCustomers.length === 0 ? (
                                <div className="flex items-center justify-center py-8 text-center">
                                    <p className="text-sm text-muted-foreground">No customer data available</p>
                                </div>
                            ) : (
                                <div className="space-y-4">
                                    {topCustomers.slice(0, 5).map((customer, index) => (
                                        <div key={index} className="flex items-start justify-between">
                                            <div className="space-y-1">
                                                <div className="flex items-center gap-2">
                                                    <Users className="h-4 w-4 text-muted-foreground" />
                                                    <p className="text-sm font-medium">{customer.user.name}</p>
                                                </div>
                                                <p className="text-xs text-muted-foreground">
                                                    {customer.transactions} transactions
                                                </p>
                                            </div>
                                            <div className="text-right">
                                                <p className="text-sm font-semibold text-green-600">
                                                    {formatCurrency(n(customer.profit))}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {formatCurrency(n(customer.revenue))} revenue
                                                </p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Trends Chart */}
                <Card>
                    <CardHeader>
                        <CardTitle>{t('admin.profit.profit_trends')}</CardTitle>
                        <CardDescription>
                            Revenue, cost, and profit over time
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {trends.length === 0 ? (
                            <div className="flex items-center justify-center py-12 text-center">
                                <div>
                                    <BarChart3 className="mx-auto h-12 w-12 text-muted-foreground" />
                                    <p className="mt-4 text-lg font-medium">
                                        No trend data available
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        Start making transactions to see trends
                                    </p>
                                </div>
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Period</TableHead>
                                        <TableHead className="text-right">Revenue</TableHead>
                                        <TableHead className="text-right">Cost</TableHead>
                                        <TableHead className="text-right">Profit</TableHead>
                                        <TableHead className="text-right">Margin</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {trends.map((trend, index) => {
                                        const margin = n(trend.revenue) > 0 ? (n(trend.profit) / n(trend.revenue) * 100).toFixed(1) : '0.0';
                                        return (
                                            <TableRow key={index}>
                                                <TableCell className="font-medium">
                                                    {trend.label}
                                                </TableCell>
                                                <TableCell className="text-right text-blue-600">
                                                    {formatCurrency(n(trend.revenue))}
                                                </TableCell>
                                                <TableCell className="text-right text-red-600">
                                                    {formatCurrency(n(trend.cost))}
                                                </TableCell>
                                                <TableCell className="text-right font-semibold text-green-600">
                                                    {formatCurrency(n(trend.profit))}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <Badge variant="outline">
                                                        {margin}%
                                                    </Badge>
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                {/* Country Breakdown */}
                <Card>
                    <CardHeader>
                        <CardTitle>{t('admin.profit.profit_by_country')}</CardTitle>
                        <CardDescription>
                            Geographic profit distribution
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {profitByCountry.length === 0 ? (
                            <div className="flex items-center justify-center py-8 text-center">
                                <p className="text-sm text-muted-foreground">No country data available</p>
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Country</TableHead>
                                        <TableHead className="text-right">Transactions</TableHead>
                                        <TableHead className="text-right">Revenue</TableHead>
                                        <TableHead className="text-right">Cost</TableHead>
                                        <TableHead className="text-right">Profit</TableHead>
                                        <TableHead className="text-right">Margin</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {profitByCountry.map((country, index) => (
                                        <TableRow key={index}>
                                            <TableCell className="font-medium">
                                                <div className="flex items-center gap-2">
                                                    <Globe className="h-4 w-4 text-muted-foreground" />
                                                    {country.country}
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {country.count}
                                            </TableCell>
                                            <TableCell className="text-right text-blue-600">
                                                {formatCurrency(n(country.revenue))}
                                            </TableCell>
                                            <TableCell className="text-right text-red-600">
                                                {formatCurrency(n(country.cost))}
                                            </TableCell>
                                            <TableCell className="text-right font-semibold text-green-600">
                                                {formatCurrency(n(country.profit))}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Badge variant="outline">
                                                    {n(country.margin).toFixed(1)}%
                                                </Badge>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                {/* Recent High-Value Transactions */}
                <Card>
                    <CardHeader>
                        <CardTitle>{t('admin.profit.recent_highvalue_transactions')}</CardTitle>
                        <CardDescription>
                            Top transactions by profit amount
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {recentTransactions.length === 0 ? (
                            <div className="flex items-center justify-center py-8 text-center">
                                <p className="text-sm text-muted-foreground">No recent transactions</p>
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>User</TableHead>
                                        <TableHead>Service</TableHead>
                                        <TableHead className="text-right">Amount</TableHead>
                                        <TableHead className="text-right">Cost</TableHead>
                                        <TableHead className="text-right">Profit</TableHead>
                                        <TableHead className="text-right">Margin</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {recentTransactions.slice(0, 10).map((transaction) => (
                                        <TableRow key={transaction.id}>
                                            <TableCell className="font-medium">
                                                {transaction.user.name}
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="outline">
                                                    {transaction.service_type?.replace('_', ' ')}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right text-blue-600">
                                                {formatCurrency(n(transaction.amount))}
                                            </TableCell>
                                            <TableCell className="text-right text-red-600">
                                                {formatCurrency(n(transaction.actual_cost))}
                                            </TableCell>
                                            <TableCell className="text-right font-semibold text-green-600">
                                                {formatCurrency(n(transaction.profit_amount))}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Badge variant="outline">
                                                    {n(transaction.profit_percentage).toFixed(1)}%
                                                </Badge>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
