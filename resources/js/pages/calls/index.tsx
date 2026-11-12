import AppLayout from '@/layouts/app-layout';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { router, Link, Head } from '@inertiajs/react';
import { Phone, PhoneCall, PhoneMissed, PhoneOff, Clock, Search, Filter, Eye, Mic, Download } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useFormatCurrency } from '@/hooks/use-currency';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { PageHelp } from '@/components/page-help';

interface Call {
  id: number;
  call_type: string;
  direction: string;
  to_number: string;
  from_number: string;
  status: string;
  duration: number | null;
  price: number | string | null;
  price_unit: string | null;
  recording_url: string | null;
  recording_duration: number | null;
  started_at: string | null;
  ended_at: string | null;
  created_at: string;
  campaign?: {
    id: number;
    name: string;
  };
  campaignContact?: {
    id: number;
    first_name: string | null;
    last_name: string | null;
    phone_number: string;
  };
}

interface Props {
  calls?: {
    data: Call[];
    links: unknown[];
    meta: {
      current_page: number;
      from: number | null;
      last_page: number;
      per_page: number;
      to: number | null;
      total: number;
    };
  };
  stats?: {
    total: number;
    completed: number;
    failed: number;
    in_progress: number;
    inbound: number;
    outbound: number;
  };
  filters?: {
    status?: string;
    call_type?: string;
    direction?: string;
    from_date?: string;
    to_date?: string;
    search?: string;
  };
  error?: string;
}

export default function CallsIndex({
  calls = { data: [], links: [], meta: { current_page: 1, from: null, last_page: 1, per_page: 20, to: null, total: 0 } },
  stats = { total: 0, completed: 0, failed: 0, in_progress: 0, inbound: 0, outbound: 0 },
  filters = {},
  error
}: Props) {
  const { t } = useTranslation();
  const { formatCurrency } = useFormatCurrency();
  const breadcrumbs: BreadcrumbItem[] = [
  {
    title: t('common.dashboard'),
    href: dashboard().url,
  },
  {
    title: t('nav.call_history'),
    href: '/calls',
  },
];
  const [searchTerm, setSearchTerm] = useState(filters.search || '');
  const [showFilters, setShowFilters] = useState(false);

  const handleSearch = (e: FormEvent) => {
    e.preventDefault();
    router.get('/calls', { ...filters, search: searchTerm }, { preserveState: true });
  };

  const handleFilterChange = (key: string, value: string) => {
    router.get('/calls', { ...filters, [key]: value }, { preserveState: true });
  };

  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'completed':
        return <PhoneCall className="h-4 w-4 text-green-600" />;
      case 'failed':
      case 'busy':
      case 'no-answer':
        return <PhoneMissed className="h-4 w-4 text-red-600" />;
      case 'in-progress':
      case 'ringing':
        return <Phone className="h-4 w-4 text-blue-600 animate-pulse" />;
      default:
        return <PhoneOff className="h-4 w-4 text-slate-400" />;
    }
  };

  const getStatusBadge = (status: string) => {
    const variants: Record<string, 'default' | 'secondary' | 'destructive' | 'outline'> = {
      completed: 'default',
      failed: 'destructive',
      busy: 'destructive',
      'no-answer': 'destructive',
      'in-progress': 'secondary',
      ringing: 'secondary',
    };

    return (
      <Badge variant={variants[status] || 'outline'} className="capitalize">
        {status.replace(/-/g, ' ')}
      </Badge>
    );
  };

  const formatDuration = (seconds: number | null) => {
    if (!seconds) return '0:00';
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
  };

  const formatContactName = (call: Call) => {
    if (call.campaignContact) {
      const name = [call.campaignContact.first_name, call.campaignContact.last_name]
        .filter(Boolean)
        .join(' ');
      return name || call.to_number;
    }
    return call.to_number;
  };

  const helpSections = [
    {
      title: t('calls.help_overview'),
      content: t('calls.help_overview_description'),
    },
    {
      title: t('calls.help_status'),
      content: t('calls.help_status_description'),
    },
    {
      title: t('calls.help_stats'),
      content: t('calls.help_stats_description'),
    },
    {
      title: t('calls.help_search'),
      content: t('calls.help_search_description'),
    },
    {
      title: t('calls.help_cost'),
      content: t('calls.help_cost_description'),
    },
    {
      title: t('calls.help_view'),
      content: t('calls.help_view_description'),
    },
  ];

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={t('calls.title')} />
      <div className="flex h-full flex-1 flex-col gap-4 p-4 md:gap-8 md:p-8">
        <div className="flex items-center justify-between">
          <Heading
            title={t('calls.title')}
            description={t('calls.description')}
          />
          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              onClick={() => {
                const params = new URLSearchParams();
                if (filters.status && filters.status !== 'all') params.append('status', filters.status);
                if (filters.call_type && filters.call_type !== 'all') params.append('call_type', filters.call_type);
                if (filters.direction && filters.direction !== 'all') params.append('direction', filters.direction);
                if (filters.from_date) params.append('from_date', filters.from_date);
                if (filters.to_date) params.append('to_date', filters.to_date);
                if (filters.search) params.append('search', filters.search);
                window.location.href = `/calls/export?${params.toString()}`;
              }}
            >
              <Download className="h-4 w-4 mr-2" />
              {t('calls.export')}
            </Button>
            <PageHelp title={t('calls.title')} sections={helpSections} />
          </div>
        </div>
        {/* Permission Error */}
        {error && (
          <div className="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300">
            {error}
          </div>
        )}

        {/* Statistics Cards */}
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-6">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">{t('calls.total')}</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{(stats?.total ?? 0).toLocaleString()}</div>
            </CardContent>
          </Card>
          
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">{t('calls.completed')}</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-green-600">{(stats?.completed ?? 0).toLocaleString()}</div>
              <p className="text-xs text-muted-foreground">
                {(stats?.total ?? 0) > 0 ? Math.round(((stats?.completed ?? 0) / (stats?.total ?? 1)) * 100) : 0}% {t('calls.success_rate')}
              </p>
            </CardContent>
          </Card>
          
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">{t('calls.failed')}</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-red-600">{(stats?.failed ?? 0).toLocaleString()}</div>
            </CardContent>
          </Card>
          
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">{t('calls.in_progress')}</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-blue-600">{(stats?.in_progress ?? 0).toLocaleString()}</div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">{t('calls.inbound')}</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-purple-600">{(stats?.inbound ?? 0).toLocaleString()}</div>
              <p className="text-xs text-muted-foreground">
                {(stats?.total ?? 0) > 0 ? Math.round(((stats?.inbound ?? 0) / (stats?.total ?? 1)) * 100) : 0}%
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">{t('calls.outbound')}</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-orange-600">{(stats?.outbound ?? 0).toLocaleString()}</div>
              <p className="text-xs text-muted-foreground">
                {(stats?.total ?? 0) > 0 ? Math.round(((stats?.outbound ?? 0) / (stats?.total ?? 1)) * 100) : 0}%
              </p>
            </CardContent>
          </Card>
        </div>

      {/* Filters and Search */}
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <div>
              <CardTitle>{t('common.filter')}</CardTitle>
              <CardDescription>{t('calls.search_and_filter')}</CardDescription>
            </div>
            <Button
              variant="outline"
              size="sm"
              onClick={() => setShowFilters(!showFilters)}
            >
              <Filter className="h-4 w-4 mr-2" />
              {showFilters ? t('common.hide') : t('common.show')} {t('common.filters')}
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleSearch} className="mb-4">
            <div className="flex gap-2">
              <div className="flex-1">
                <Input
                  type="text"
                  placeholder={t('calls.search')}
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className="w-full"
                />
              </div>
              <Button type="submit">
                <Search className="h-4 w-4 mr-2" />
                {t('common.search')}
              </Button>
            </div>
          </form>

          {showFilters && (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
              <div>
                <label className="text-sm font-medium mb-2 block">{t('common.status')}</label>
                <select
                  className="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm"
                  value={filters.status || 'all'}
                  onChange={(e) => handleFilterChange('status', e.target.value)}
                >
                  <option value="all">{t('common.all')} {t('common.status')}</option>
                  <option value="completed">{t('common.completed')}</option>
                  <option value="failed">{t('common.failed')}</option>
                  <option value="busy">{t('calls.busy')}</option>
                  <option value="no-answer">{t('calls.no_answer')}</option>
                  <option value="in-progress">{t('calls.in_progress')}</option>
                </select>
              </div>

              <div>
                <label className="text-sm font-medium mb-2 block">{t('common.type')}</label>
                <select
                  className="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm"
                  value={filters.call_type || 'all'}
                  onChange={(e) => handleFilterChange('call_type', e.target.value)}
                >
                  <option value="all">{t('common.all')} {t('common.types')}</option>
                  <option value="manual">{t('calls.manual')}</option>
                  <option value="campaign">{t('common.campaign')}</option>
                </select>
              </div>

              <div>
                <label className="text-sm font-medium mb-2 block">{t('calls.direction')}</label>
                <select
                  className="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm"
                  value={filters.direction || 'all'}
                  onChange={(e) => handleFilterChange('direction', e.target.value)}
                >
                  <option value="all">{t('common.all')} {t('calls.directions')}</option>
                  <option value="inbound">{t('calls.inbound')}</option>
                  <option value="outbound">{t('calls.outbound')}</option>
                </select>
              </div>

              <div>
                <label className="text-sm font-medium mb-2 block">{t('calls.from_date')}</label>
                <Input
                  type="date"
                  value={filters.from_date || ''}
                  onChange={(e) => handleFilterChange('from_date', e.target.value)}
                />
              </div>

              <div>
                <label className="text-sm font-medium mb-2 block">{t('calls.to_date')}</label>
                <Input
                  type="date"
                  value={filters.to_date || ''}
                  onChange={(e) => handleFilterChange('to_date', e.target.value)}
                />
              </div>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Calls Table */}
      <Card>
        <CardHeader>
          <div className="flex items-center justify-between">
            <div>
              <CardTitle>{t('calls.title')}</CardTitle>
            </div>
          </div>
        </CardHeader>
        <CardContent>
          {(calls?.data?.length ?? 0) > 0 ? (
            <>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t('common.status')}</TableHead>
                    <TableHead>{t('calls.direction')}</TableHead>
                    <TableHead>{t('calls.contact_number')}</TableHead>
                    <TableHead>{t('common.type')}</TableHead>
                    <TableHead>{t('calls.campaign')}</TableHead>
                    <TableHead>{t('calls.duration')}</TableHead>
                    <TableHead className="text-right">{t('calls.cost')}</TableHead>
                    <TableHead>{t('calls.recording')}</TableHead>
                    <TableHead>{t('calls.date_time')}</TableHead>
                    <TableHead className="text-right">{t('common.actions')}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {(calls?.data ?? []).map((call) => (
                    <TableRow key={call.id}>
                      <TableCell>
                        <div className="flex items-center gap-2">
                          {getStatusIcon(call.status)}
                          {getStatusBadge(call.status)}
                        </div>
                      </TableCell>
                      <TableCell>
                        <Badge 
                          variant={call.direction === 'inbound' ? 'default' : 'secondary'}
                          className="capitalize"
                        >
                          {call.direction === 'inbound' ? (
                            <>
                              <svg className="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M14 5l7 7m0 0l-7 7m7-7H10" />
                              </svg>
                              {t('calls.inbound')}
                            </>
                          ) : (
                            <>
                              <svg className="h-3 w-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 8l4-4m0 0l-4-4m4 4H7" />
                              </svg>
                              {t('calls.outbound')}
                            </>
                          )}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        <div>
                          <div className="font-medium">{formatContactName(call)}</div>
                          <div className="text-sm text-slate-500">
                            From: {call.from_number}
                          </div>
                        </div>
                      </TableCell>
                      <TableCell>
                        <Badge variant="outline" className="capitalize">
                          {call.call_type}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        {call.campaign ? (
                          <Link
                            href={`/campaigns/${call.campaign.id}`}
                            className="text-blue-600 hover:underline"
                          >
                            {call.campaign.name}
                          </Link>
                        ) : (
                          <span className="text-slate-400">-</span>
                        )}
                      </TableCell>
                      <TableCell>
                        <div className="flex items-center gap-1">
                          <Clock className="h-3.5 w-3.5 text-slate-400" />
                          {formatDuration(call.duration)}
                        </div>
                      </TableCell>
                      <TableCell className="text-right font-mono text-sm">
                        {call.price != null && Number(call.price) > 0
                          ? formatCurrency(Number(call.price), undefined, 4)
                          : '—'}
                      </TableCell>
                      <TableCell>
                        {call.recording_url ? (
                          <div className="flex items-center gap-2">
                            <Mic className="h-4 w-4 text-green-600" />
                            <audio controls className="h-8" style={{ width: '180px' }}>
                              <source src={call.recording_url} type="audio/mpeg" />
                              Your browser does not support audio.
                            </audio>
                          </div>
                        ) : (
                          <span className="text-slate-400 text-sm">-</span>
                        )}
                      </TableCell>
                      <TableCell>
                        <div className="text-sm">
                          {new Date(call.created_at).toLocaleString()}
                        </div>
                      </TableCell>
                      <TableCell className="text-right">
                        <Link href={`/calls/${call.id}`}>
                          <Button variant="ghost" size="sm">
                            <Eye className="h-4 w-4" />
                          </Button>
                        </Link>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>

              {/* Pagination */}
              {(calls?.meta?.last_page ?? 1) > 1 && (
                <div className="mt-4 flex items-center justify-between">
                  <div className="text-sm text-slate-600">
                    {t('common.showing')} {calls?.meta?.from ?? 0} to {calls?.meta?.to ?? 0} {t('common.of')} {calls?.meta?.total ?? 0} calls
                  </div>
                  <div className="flex gap-2">
                    {(calls?.meta?.current_page ?? 1) > 1 && (
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() =>
                          router.get('/calls', {
                            ...filters,
                            page: (calls?.meta?.current_page ?? 1) - 1,
                          })
                        }
                      >
                        {t('common.previous')}
                      </Button>
                    )}
                    {(calls?.meta?.current_page ?? 1) < (calls?.meta?.last_page ?? 1) && (
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() =>
                          router.get('/calls', {
                            ...filters,
                            page: (calls?.meta?.current_page ?? 1) + 1,
                          })
                        }
                      >
                        {t('common.next')}
                      </Button>
                    )}
                  </div>
                </div>
              )}
            </>
          ) : (
            <div className="text-center py-12">
              <PhoneOff className="h-12 w-12 text-slate-300 mx-auto mb-4" />
              <h3 className="text-lg font-medium text-slate-900 mb-2">{t('calls.no_calls')}</h3>
              <p className="text-slate-600 mb-4">
                {filters.search || filters.status || filters.call_type
                  ? t('calls.try_adjusting_filters')
                  : t('calls.start_making_calls')}
              </p>
              <Link href="/softphone">
                <Button>
                  <Phone className="h-4 w-4 mr-2" />
                  {t('calls.make_a_call')}
                </Button>
              </Link>
            </div>
          )}
        </CardContent>
      </Card>
      </div>
    </AppLayout>
  );
}
