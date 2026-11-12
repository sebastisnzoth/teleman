import AppLayout from '@/layouts/app-layout';
import { useTranslation } from 'react-i18next';
import { useFormatCurrency } from '@/hooks/use-currency';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
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
import { Head, Link } from '@inertiajs/react';
import { TrendingDown, TrendingUp, Users, Wallet } from 'lucide-react';
import { PageHelp } from '@/components/page-help';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';

interface User {
    id: number;
    name: string;
    email: string;
    credit_balance: number;
}

interface Stats {
    total_users: number;
    total_credit_balance: number;
    low_balance_users: number;
    recent_transactions: number;
}

interface PaginatedUsers {
    data: User[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Props {
    users: PaginatedUsers;
    stats: Stats;
}

export default function CreditManagementIndex({ 
    users = { data: [], current_page: 1, last_page: 1, per_page: 25, total: 0 }, 
    stats = {
        total_users: 0,
        total_credit_balance: 0,
        low_balance_users: 0,
        recent_transactions: 0,
    }
}: Props) {
  const { t } = useTranslation();
    const { formatCurrency } = useFormatCurrency();
    // Ensure users.data is always an array
    const usersArray = Array.isArray(users?.data) ? users.data : [];
    
    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('common.dashboard'), href: dashboard().url },
        { title: t('admin.credit_management.credit_management'), href: '/admin/credit-management' },
    ];

    const helpSections = [
        {
            title: 'Credit Management Overview',
            content: 'Monitor all user credit balances from a centralized dashboard. Track total credits in the system, identify low-balance users, and manage credit allocations.',
        },
        {
            title: 'Credit Balance',
            content: 'Each user has a credit balance used to pay for calls. Credits are deducted in real-time based on call duration and destination.',
        },
        {
            title: 'Low Balance Users',
            content: 'Users with balances below threshold are highlighted. Encourage them to top up to prevent campaign interruptions.',
        },
        {
            title: 'Manual Adjustments',
            content: 'Admins can manually adjust user credits for refunds, bonuses, or corrections. All adjustments are logged for audit purposes.',
        },
        {
            title: 'Transaction History',
            content: 'View detailed transaction history for each user to track credit purchases, usage, and adjustments over time.',
        },
        {
            title: 'Credit Grants',
            content: 'Grant promotional credits to users for trials, bonuses, or compensation. Specify amount and reason for the grant.',
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('admin.credit_management.credit_management')} />

            <div className="mx-auto max-w-7xl space-y-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title={t('admin.credit_management.credit_management')}
                        description={t('admin.credit_management.monitor_and_manage_user_credit_balances')}
                    />
                    <PageHelp title={t('admin.credit_management.credit_management_help')} sections={helpSections} />
                </div>

                {/* Stats Cards */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">{t('admin.credit_management.total_credits')}</CardTitle>
                            <Wallet className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {formatCurrency(Number(stats?.total_credit_balance || 0))}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">{t('admin.credit_management.total_users')}</CardTitle>
                            <Users className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {stats?.total_users || 0}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">{t('admin.credit_management.low_balance_users')}</CardTitle>
                            <TrendingDown className="h-4 w-4 text-red-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-red-600">
                                {stats?.low_balance_users || 0}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">{t('admin.credit_management.recent_transactions_7d')}</CardTitle>
                            <TrendingUp className="h-4 w-4 text-green-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-green-600">
                                {stats?.recent_transactions || 0}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Users Table */}
                <Card>
                    <CardHeader>
                        <CardTitle>{t('admin.credit_management.user_credit_balances')}</CardTitle>
                        <CardDescription>
                            View and manage credit balances for all users
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {usersArray.length === 0 ? (
                            <div className="flex items-center justify-center py-12 text-center">
                                <div>
                                    <Wallet className="mx-auto h-12 w-12 text-muted-foreground" />
                                    <p className="mt-4 text-lg font-medium">No users found</p>
                                </div>
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>User</TableHead>
                                        <TableHead>Email</TableHead>
                                        <TableHead>Credit Balance</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {usersArray.map((user) => (
                                        <TableRow key={user.id}>
                                            <TableCell className="font-medium">
                                                {user.name}
                                            </TableCell>
                                            <TableCell>{user.email}</TableCell>
                                            <TableCell>
                                                <span className="text-lg font-semibold">
                                                    {formatCurrency(Number(user?.credit_balance || 0))}
                                                </span>
                                            </TableCell>
                                            <TableCell>
                                                {Number(user?.credit_balance || 0) > 10 ? (
                                                    <Badge variant="default">Active</Badge>
                                                ) : Number(user?.credit_balance || 0) > 0 ? (
                                                    <Badge variant="secondary">Low Balance</Badge>
                                                ) : (
                                                    <Badge variant="destructive">No Credits</Badge>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Button variant="outline" size="sm" asChild>
                                                    <Link href={`/admin/credit-management/users/${user.id}`}>
                                                        Manage
                                                    </Link>
                                                </Button>
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
