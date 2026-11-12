import { Head, Link, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AppLayout from '@/layouts/app-layout';
import Heading from '@/components/heading';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { RefreshCw, Search, Shield, ShieldCheck, Eye, FileText, Phone, Building, Clock, CheckCircle, XCircle } from 'lucide-react';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { useState } from 'react';
import Pagination from '@/components/pagination';

interface User {
    id: number;
    name: string;
    email: string;
    created_at: string;
}

interface KycSubmission {
    id: number;
    kyc_tier: 'basic' | 'business';
    status: 'pending' | 'approved' | 'rejected' | 'expired';
    user: User;
    phone_number?: string;
    phone_verified_at?: string;
    business_name?: string;
    submitted_at?: string;
    created_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginationMeta {
    current_page: number;
    from: number | null;
    last_page: number;
    path: string;
    per_page: number;
    to: number | null;
    total: number;
}

interface Props {
    pending: {
        data: KycSubmission[];
        links: PaginationLink[];
        meta: PaginationMeta;
    };
    filters: {
        tier?: string;
        search?: string;
    };
    stats: {
        total_pending: number;
        basic_pending: number;
        business_pending: number;
    };
}

const tierColors = {
    basic: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
    business: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
};

const tierIcons = {
    basic: Phone,
    business: Building,
};

const statusColors = {
    pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
    approved: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    rejected: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    expired: 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400',
};

const statusIcons = {
    pending: Clock,
    approved: CheckCircle,
    rejected: XCircle,
    expired: Clock,
};

export default function KycIndex({ pending, filters, stats }: Props) {
    const { t } = useTranslation();
  const breadcrumbs: BreadcrumbItem[] = [
    {
        title: t('common.dashboard'),
        href: dashboard().url,
    },
    {
        title: t('admin.kyc.review'),
        href: '/admin/kyc',
    },
];
    const [search, setSearch] = useState(filters.search || '');
    const [tier, setTier] = useState(filters.tier || 'all');

    const handleFilter = () => {
        router.get('/admin/kyc', {
            search: search || undefined,
            tier: tier !== 'all' ? tier : undefined,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleReset = () => {
        setSearch('');
        setTier('all');
        router.get('/admin/kyc', {}, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('admin.kyc.page_title')} />

            <div className="space-y-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <Heading
                        title={t('admin.kyc.title')}
                        description={t('admin.kyc.description')}
                    />
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => router.reload()}
                    >
                        <RefreshCw className="mr-2 h-4 w-4" />
                        {t('common.refresh')}
                    </Button>
                </div>

                {/* Stats Cards */}
                <div className="grid gap-6 md:grid-cols-3">
                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">{t('admin.kyc.total_pending')}</p>
                                    <p className="text-2xl font-bold">{stats.total_pending}</p>
                                </div>
                                <Shield className="h-8 w-8 text-muted-foreground" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">{t('admin.kyc.basic_tier')}</p>
                                    <p className="text-2xl font-bold">{stats.basic_pending}</p>
                                </div>
                                <Phone className="h-8 w-8 text-blue-600 dark:text-blue-400" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-muted-foreground">{t('admin.kyc.business_tier')}</p>
                                    <p className="text-2xl font-bold">{stats.business_pending}</p>
                                </div>
                                <Building className="h-8 w-8 text-purple-600 dark:text-purple-400" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters */}
                <Card>
                    <CardContent className="p-6">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-end">
                            <div className="flex-1">
                                <label className="mb-2 block text-sm font-medium">{t('common.search')}</label>
                                <div className="relative">
                                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        placeholder={t('admin.kyc.search_placeholder')}
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        onKeyDown={(e) => e.key === 'Enter' && handleFilter()}
                                        className="pl-10"
                                    />
                                </div>
                            </div>

                            <div className="w-full md:w-48">
                                <label className="mb-2 block text-sm font-medium">{t('admin.kyc.tier')}</label>
                                <Select value={tier} onValueChange={setTier}>
                                    <SelectTrigger>
                                        <SelectValue placeholder={t('admin.kyc.all_tiers')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">{t('admin.kyc.all_tiers')}</SelectItem>
                                        <SelectItem value="basic">{t('admin.kyc.basic')}</SelectItem>
                                        <SelectItem value="business">{t('admin.kyc.business')}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="flex gap-2">
                                <Button onClick={handleFilter}>
                                    <Search className="mr-2 h-4 w-4" />
                                    {t('common.filter')}
                                </Button>
                                <Button variant="outline" onClick={handleReset}>
                                    {t('common.reset')}
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Table */}
                <Card>
                    <CardContent className="p-6">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('admin.kyc.user')}</TableHead>
                                    <TableHead>{t('admin.kyc.tier')}</TableHead>
                                    <TableHead>{t('common.status')}</TableHead>
                                    <TableHead>{t('common.phone')}</TableHead>
                                    <TableHead>{t('admin.kyc.business_name')}</TableHead>
                                    <TableHead>{t('admin.kyc.submitted')}</TableHead>
                                    <TableHead className="text-right">{t('common.actions')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {pending.data.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={7} className="text-center text-muted-foreground">
                                            <div className="flex flex-col items-center justify-center py-8">
                                                <ShieldCheck className="mb-2 h-12 w-12 text-muted-foreground/50" />
                                                <p className="text-lg font-medium">{t('admin.kyc.no_pending')}</p>
                                                <p className="text-sm">{t('admin.kyc.all_reviewed')}</p>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    pending.data.map((kyc) => {
                                        const TierIcon = tierIcons[kyc.kyc_tier];
                                        const StatusIcon = statusIcons[kyc.status];

                                        return (
                                            <TableRow key={kyc.id}>
                                                <TableCell>
                                                    <div>
                                                        <div className="font-medium">{kyc.user.name}</div>
                                                        <div className="text-sm text-muted-foreground">{kyc.user.email}</div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge className={tierColors[kyc.kyc_tier]} variant="secondary">
                                                        <TierIcon className="mr-1 h-3 w-3" />
                                                        {kyc.kyc_tier.charAt(0).toUpperCase() + kyc.kyc_tier.slice(1)}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge className={statusColors[kyc.status]} variant="secondary">
                                                        <StatusIcon className="mr-1 h-3 w-3" />
                                                        {kyc.status.charAt(0).toUpperCase() + kyc.status.slice(1)}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    {kyc.phone_number || <span className="text-muted-foreground">—</span>}
                                                </TableCell>
                                                <TableCell>
                                                    {kyc.business_name || <span className="text-muted-foreground">—</span>}
                                                </TableCell>
                                                <TableCell>
                                                    {kyc.submitted_at
                                                        ? new Date(kyc.submitted_at).toLocaleDateString('en-US', {
                                                            year: 'numeric',
                                                            month: 'short',
                                                            day: 'numeric',
                                                        })
                                                        : <span className="text-muted-foreground">{t('admin.kyc.not_submitted')}</span>
                                                    }
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <Button asChild variant="ghost" size="sm">
                                                        <Link href={`/admin/kyc/${kyc.id}`}>
                                                            <Eye className="mr-2 h-4 w-4" />
                                                            {t('admin.kyc.review')}
                                                        </Link>
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })
                                )}
                            </TableBody>
                        </Table>

                        {/* Pagination */}
                        {pending.data.length > 0 && (
                            <div className="border-t pt-4 mt-4">
                                <Pagination meta={pending.meta} links={pending.links} />
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
