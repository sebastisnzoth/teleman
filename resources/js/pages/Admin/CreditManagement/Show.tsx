import AppLayout from '@/layouts/app-layout';
import { useTranslation } from 'react-i18next';
import Heading from '@/components/heading';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, CreditCard, Activity, Plus, Minus, DollarSign } from 'lucide-react';
import { useState, FormEventHandler } from 'react';
import { type BreadcrumbItem } from '@/types';
import { dashboard } from '@/routes';

interface Transaction {
    id: number;
    type: string;
    amount: number;
    description: string;
    created_at: string;
}

interface User {
    id: number;
    name: string;
    email: string;
    credit_balance: number;
}

interface PaginatedTransactions {
    data: Transaction[];
    current_page: number;
    last_page: number;
}

interface Props {
    user: User;
    transactions: PaginatedTransactions;
    spendingSummary?: {
        total_purchased: number;
        total_spent: number;
    };
}

export default function Show({ user, transactions, spendingSummary }: Props) {
  const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    
    const { data, setData, post, processing, errors, reset } = useForm({
        type: 'credit' as 'credit' | 'debit',
        amount: '',
        reason: '',
    });

    const transactionsData = transactions?.data || [];
    const totalPurchased = spendingSummary?.total_purchased || 0;
    const totalSpent = spendingSummary?.total_spent || 0;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('common.dashboard'), href: dashboard().url },
        { title: t('admin.credit_management.credit_management'), href: '/admin/credit-management' },
        { title: user.name, href: '' },
    ];

    const handleSubmit: FormEventHandler = (e) => {
        e.preventDefault();
        post(`/admin/credit-management/users/${user.id}/adjust`, {
            onSuccess: () => {
                reset();
                setOpen(false);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Credit Details - ${user.name}`} />

            <div className="mx-auto max-w-7xl space-y-6">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Link href="/admin/credit-management">
                            <Button variant="ghost" size="icon">
                                <ArrowLeft className="h-4 w-4" />
                            </Button>
                        </Link>
                        <Heading
                            title={t('admin.credit_management.credit_details')}
                            description={`Managing credits for ${user.name}`}
                        />
                    </div>
                    
                    <Dialog open={open} onOpenChange={setOpen}>
                        <DialogTrigger asChild>
                            <Button>
                                <DollarSign className="mr-2 h-4 w-4" />
                                Adjust Credits
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <form onSubmit={handleSubmit}>
                                <DialogHeader>
                                    <DialogTitle>Manual Credit Adjustment</DialogTitle>
                                    <DialogDescription>
                                        Add or deduct credits from {user.name}'s account
                                    </DialogDescription>
                                </DialogHeader>
                                
                                <div className="grid gap-4 py-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="type">Type</Label>
                                        <Select
                                            value={data.type}
                                            onValueChange={(value) => setData('type', value as 'credit' | 'debit')}
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="credit">
                                                    <div className="flex items-center gap-2">
                                                        <Plus className="h-4 w-4 text-green-600" />
                                                        Add Credits
                                                    </div>
                                                </SelectItem>
                                                <SelectItem value="debit">
                                                    <div className="flex items-center gap-2">
                                                        <Minus className="h-4 w-4 text-red-600" />
                                                        Deduct Credits
                                                    </div>
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        {errors.type && (
                                            <p className="text-sm text-red-600">{errors.type}</p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="amount">Amount (in dollars)</Label>
                                        <Input
                                            id="amount"
                                            type="number"
                                            step="0.01"
                                            min="0.01"
                                            max="10000"
                                            placeholder="0.00"
                                            value={data.amount}
                                            onChange={(e) => setData('amount', e.target.value)}
                                        />
                                        {errors.amount && (
                                            <p className="text-sm text-red-600">{errors.amount}</p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="reason">Reason</Label>
                                        <Textarea
                                            id="reason"
                                            placeholder="Enter reason for adjustment..."
                                            value={data.reason}
                                            onChange={(e) => setData('reason', e.target.value)}
                                            rows={3}
                                        />
                                        {errors.reason && (
                                            <p className="text-sm text-red-600">{errors.reason}</p>
                                        )}
                                    </div>
                                </div>

                                <DialogFooter>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setOpen(false)}
                                    >
                                        Cancel
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        {processing ? 'Processing...' : 'Confirm'}
                                    </Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">{t('admin.credit_management.current_credits')}</CardTitle>
                            <CreditCard className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {Number(user.credit_balance || 0).toFixed(2)} USD
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">{t('admin.credit_management.total_purchased')}</CardTitle>
                            <Activity className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-green-600">
                                {Number(totalPurchased).toFixed(2)} USD
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">{t('admin.credit_management.total_spent')}</CardTitle>
                            <Activity className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-red-600">
                                {Number(totalSpent).toFixed(2)} USD
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('admin.credit_management.user_information')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <p className="text-sm text-muted-foreground">Name</p>
                                <p className="font-medium">{user.name}</p>
                            </div>
                            <div>
                                <p className="text-sm text-muted-foreground">Email</p>
                                <p className="font-medium">{user.email}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('admin.credit_management.recent_transactions')}</CardTitle>
                        <CardDescription>
                            Showing {transactionsData.length} most recent transactions
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {transactionsData.length === 0 ? (
                            <div className="flex items-center justify-center py-12 text-center">
                                <div>
                                    <CreditCard className="mx-auto h-12 w-12 text-muted-foreground" />
                                    <p className="mt-4 text-lg font-medium">No transactions found</p>
                                    <p className="text-sm text-muted-foreground">
                                        Transactions will appear here once the user makes purchases or uses credits
                                    </p>
                                </div>
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Description</TableHead>
                                        <TableHead>Type</TableHead>
                                        <TableHead>Amount</TableHead>
                                        <TableHead>Date</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {transactionsData.map((transaction) => (
                                        <TableRow key={transaction.id}>
                                            <TableCell className="font-medium">
                                                {transaction.description}
                                            </TableCell>
                                            <TableCell>
                                                <Badge 
                                                    variant={transaction.type === 'credit' ? 'default' : 'destructive'}
                                                >
                                                    {transaction.type === 'credit' ? 'Credit' : 'Debit'}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                <span className={'font-bold ' + (transaction.type === 'credit' ? 'text-green-600' : 'text-red-600')}>
                                                    {transaction.type === 'credit' ? '+' : '-'}
                                                    {Number(transaction.amount).toFixed(2)} USD
                                                </span>
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {new Date(transaction.created_at).toLocaleString()}
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
