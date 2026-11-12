import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import Heading from '@/components/heading';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { RefreshCw, Search, ExternalLink, FileText } from 'lucide-react';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { useState } from 'react';
import Pagination from '@/components/pagination';
import { useTranslation } from 'react-i18next';

interface Call {
    id: number;
    twilio_call_sid: string | null;
    to_number: string;
    from_number: string;
    status: string;
    call_type: string;
    duration_seconds: number | null;
    price: number | string | null;
    price_unit: string | null;
    error_message: string | null;
    transcript_text: string | null;
    transcript_status: string | null;
    // AI Sentiment Analysis
    sentiment: 'positive' | 'neutral' | 'negative' | null;
    sentiment_confidence: number | null;
    lead_score: number | null;
    lead_quality: 'hot' | 'warm' | 'cold' | 'not_interested' | null;
    ai_summary: string | null;
    key_intents: string[] | null;
    created_at: string;
    user?: { id: number; name: string; email: string };
    campaign?: { id: number; name: string };
}

interface User {
    id: number;
    name: string;
    email: string;
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
    calls: {
        data: Call[];
        links: PaginationLink[];
        meta: PaginationMeta;
    };
    users: User[];
    filters: {
        status?: string;
        type?: string;
        search?: string;
        user_id?: string;
        sentiment?: string;
        lead_quality?: string;
    };
}

const statusColors: Record<string, string> = {
    completed: 'default',
    'in-progress': 'secondary',
    initiated: 'secondary',
    ringing: 'secondary',
    busy: 'destructive',
    failed: 'destructive',
    'no-answer': 'destructive',
    canceled: 'outline',
};

const sentimentColors: Record<string, string> = {
    positive: 'default',
    neutral: 'secondary',
    negative: 'destructive',
};

const leadQualityColors: Record<string, string> = {
    hot: 'default',
    warm: 'secondary',
    cold: 'outline',
    not_interested: 'destructive',
};

export default function CallLogs({ calls, users, filters }: Props) {
    const { t } = useTranslation();
    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || 'all');
    const [type, setType] = useState(filters.type || 'all');
    const [userId, setUserId] = useState(filters.user_id || 'all');
    const [sentiment, setSentiment] = useState(filters.sentiment || 'all');
    const [leadQuality, setLeadQuality] = useState(filters.lead_quality || 'all');
    const [selectedTranscript, setSelectedTranscript] = useState<Call | null>(null);

    const handleFilter = () => {
        const params: Record<string, string> = {};
        if (search) params.search = search;
        if (status !== 'all') params.status = status;
        if (type !== 'all') params.type = type;
        if (userId !== 'all') params.user_id = userId;
        if (sentiment !== 'all') params.sentiment = sentiment;
        if (leadQuality !== 'all') params.lead_quality = leadQuality;

        router.get('/admin/call-logs', params, { preserveState: true });
    };

    const handleReset = () => {
        setSearch('');
        setStatus('all');
        setType('all');
        setUserId('all');
        setSentiment('all');
        setLeadQuality('all');
        router.get('/admin/call-logs');
    };

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: t('nav.dashboard'),
            href: dashboard().url,
        },
        {
            title: t('admin.call_logs.title'),
            href: '/admin/call-logs',
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('admin.call_logs.title')} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4 md:gap-8 md:p-8">
                <div className="flex items-center justify-between">
                    <Heading
                        title={t('admin.call_logs.title')}
                        description={t('admin.call_logs.description')}
                    />
                    <Button variant="outline" size="sm" onClick={() => router.reload()}>
                        <RefreshCw className="h-4 w-4 mr-2" />
                        {t('common.refresh')}
                    </Button>
                </div>

                <Card>
                    <CardContent className="pt-6">
                    <div className="flex gap-4 mb-4">
                        <div className="flex-1">
                            <Input
                                placeholder={t('admin.call_logs.search_placeholder')}
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && handleFilter()}
                            />
                        </div>
                        <Select value={userId} onValueChange={setUserId}>
                            <SelectTrigger className="w-[250px]">
                                <SelectValue placeholder={t('admin.call_logs.filter_by_customer')} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{t('admin.call_logs.all_customers')}</SelectItem>
                                {users.map((user) => (
                                    <SelectItem key={user.id} value={user.id.toString()}>
                                        {user.name} ({user.email})
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select value={status} onValueChange={setStatus}>
                            <SelectTrigger className="w-[200px]">
                                <SelectValue placeholder={t('admin.call_logs.status')} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{t('admin.call_logs.all_statuses')}</SelectItem>
                                <SelectItem value="completed">{t('admin.call_logs.status_completed')}</SelectItem>
                                <SelectItem value="initiated">{t('admin.call_logs.status_initiated')}</SelectItem>
                                <SelectItem value="ringing">{t('admin.call_logs.status_ringing')}</SelectItem>
                                <SelectItem value="in-progress">{t('admin.call_logs.status_in_progress')}</SelectItem>
                                <SelectItem value="busy">{t('admin.call_logs.status_busy')}</SelectItem>
                                <SelectItem value="failed">{t('admin.call_logs.status_failed')}</SelectItem>
                                <SelectItem value="no-answer">{t('admin.call_logs.status_no_answer')}</SelectItem>
                                <SelectItem value="canceled">{t('admin.call_logs.status_canceled')}</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="flex gap-4">
                        <Select value={type} onValueChange={setType}>
                            <SelectTrigger className="w-[200px]">
                                <SelectValue placeholder={t('admin.call_logs.type')} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{t('admin.call_logs.all_types')}</SelectItem>
                                <SelectItem value="campaign">{t('admin.call_logs.type_campaign')}</SelectItem>
                                <SelectItem value="manual">{t('admin.call_logs.type_manual')}</SelectItem>
                                <SelectItem value="inbound">{t('admin.call_logs.type_inbound')}</SelectItem>
                            </SelectContent>
                        </Select>
                        <Select value={sentiment} onValueChange={setSentiment}>
                            <SelectTrigger className="w-[200px]">
                                <SelectValue placeholder={t('admin.call_logs.sentiment')} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{t('admin.call_logs.all_sentiments')}</SelectItem>
                                <SelectItem value="positive">{t('admin.call_logs.sentiment_positive')}</SelectItem>
                                <SelectItem value="neutral">{t('admin.call_logs.sentiment_neutral')}</SelectItem>
                                <SelectItem value="negative">{t('admin.call_logs.sentiment_negative')}</SelectItem>
                            </SelectContent>
                        </Select>
                        <Select value={leadQuality} onValueChange={setLeadQuality}>
                            <SelectTrigger className="w-[200px]">
                                <SelectValue placeholder={t('admin.call_logs.lead_quality')} />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">{t('admin.call_logs.all_lead_qualities')}</SelectItem>
                                <SelectItem value="hot">{t('admin.call_logs.lead_quality_hot')}</SelectItem>
                                <SelectItem value="warm">{t('admin.call_logs.lead_quality_warm')}</SelectItem>
                                <SelectItem value="cold">{t('admin.call_logs.lead_quality_cold')}</SelectItem>
                                <SelectItem value="not_interested">{t('admin.call_logs.lead_quality_not_interested')}</SelectItem>
                            </SelectContent>
                        </Select>
                        <div className="flex-1"></div>
                        <Button onClick={handleFilter}>
                            <Search className="h-4 w-4 mr-2" />
                            {t('common.filter')}
                        </Button>
                        <Button variant="outline" onClick={handleReset}>
                            {t('common.reset')}
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent className="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>{t('admin.call_logs.col_call_sid')}</TableHead>
                                <TableHead>{t('admin.call_logs.col_from_to')}</TableHead>
                                <TableHead>{t('admin.call_logs.col_type')}</TableHead>
                                <TableHead>{t('admin.call_logs.col_campaign_user')}</TableHead>
                                <TableHead>{t('admin.call_logs.col_status')}</TableHead>
                                <TableHead>{t('admin.call_logs.col_duration')}</TableHead>
                                <TableHead className="text-right">{t('admin.call_logs.col_cost')}</TableHead>
                                <TableHead>{t('admin.call_logs.col_ai_analysis')}</TableHead>
                                <TableHead>{t('admin.call_logs.col_transcript')}</TableHead>
                                <TableHead>{t('admin.call_logs.col_error')}</TableHead>
                                <TableHead>{t('common.created_at')}</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {calls.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={10} className="text-center py-12 text-muted-foreground">
                                        {t('admin.call_logs.no_calls')}
                                    </TableCell>
                                </TableRow>
                            ) : (
                                calls.data.map((call) => (
                                    <TableRow key={call.id}>
                                        <TableCell className="font-mono text-sm">
                                            {call.twilio_call_sid ? (
                                                <a
                                                    href={`https://console.twilio.com/us1/monitor/logs/calls/${call.twilio_call_sid}`}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="flex items-center gap-1 text-primary hover:underline"
                                                >
                                                    {call.twilio_call_sid.substring(0, 15)}...
                                                    <ExternalLink className="h-3 w-3" />
                                                </a>
                                            ) : (
                                                <span className="text-muted-foreground">—</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="font-mono text-sm">
                                            <div>{call.from_number}</div>
                                            <div className="text-muted-foreground">→ {call.to_number}</div>
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline">{call.call_type}</Badge>
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {call.campaign ? (
                                                <div>
                                                    <div className="font-medium">{call.campaign.name}</div>
                                                    <div className="text-muted-foreground text-xs">{t('admin.call_logs.campaign_label')}</div>
                                                </div>
                                            ) : call.user ? (
                                                <div>
                                                    <div className="font-medium">{call.user.name}</div>
                                                    <div className="text-muted-foreground text-xs">{t('admin.call_logs.user_label')}</div>
                                                </div>
                                            ) : (
                                                '—'
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant={(statusColors[call.status] || 'outline') as 'default' | 'secondary' | 'destructive' | 'outline'}>
                                                {call.status}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            {call.duration_seconds !== null
                                                ? `${call.duration_seconds}s`
                                                : '—'}
                                        </TableCell>
                                        <TableCell className="text-right font-mono text-sm">
                                            {call.price != null && Number(call.price) > 0
                                                ? `$${Number(call.price).toFixed(4)}`
                                                : '—'}
                                        </TableCell>
                                        <TableCell>
                                            {call.sentiment ? (
                                                <div className="space-y-1">
                                                    <div className="flex items-center gap-1">
                                                        <Badge variant={(sentimentColors[call.sentiment] || 'outline') as 'default' | 'secondary' | 'destructive' | 'outline'} className="text-xs">
                                                            {call.sentiment}
                                                        </Badge>
                                                        <span className="text-xs text-muted-foreground">
                                                            {call.sentiment_confidence}%
                                                        </span>
                                                    </div>
                                                    {call.lead_quality && (
                                                        <Badge variant={(leadQualityColors[call.lead_quality] || 'outline') as 'default' | 'secondary' | 'destructive' | 'outline'} className="text-xs">
                                                            {call.lead_quality} {call.lead_score ? `(${call.lead_score}/10)` : ''}
                                                        </Badge>
                                                    )}
                                                </div>
                                            ) : (
                                                <span className="text-xs text-muted-foreground">—</span>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {call.transcript_text ? (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => setSelectedTranscript(call)}
                                                    className="h-8"
                                                >
                                                    <FileText className="h-3 w-3 mr-1" />
                                                    {t('common.view')}
                                                </Button>
                                            ) : call.transcript_status === 'in-progress' ? (
                                                <Badge variant="secondary" className="text-xs">{t('admin.call_logs.processing')}</Badge>
                                            ) : (
                                                <span className="text-xs text-muted-foreground">—</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="max-w-[200px]">
                                            {call.error_message ? (
                                                <span className="text-xs text-destructive line-clamp-2" title={call.error_message}>
                                                    {call.error_message}
                                                </span>
                                            ) : (
                                                '—'
                                            )}
                                        </TableCell>
                                        <TableCell className="text-sm text-muted-foreground whitespace-nowrap">
                                            {new Date(call.created_at).toLocaleString()}
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            {calls.meta.last_page > 1 && (
                <Pagination links={calls.links} meta={calls.meta} />
            )}
            </div>

            {/* Transcript Modal */}
            <Dialog open={!!selectedTranscript} onOpenChange={() => setSelectedTranscript(null)}>
                <DialogContent className="max-w-3xl max-h-[85vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>{t('admin.call_logs.transcript_modal_title')}</DialogTitle>
                        <DialogDescription>
                            {selectedTranscript && (
                                <div className="text-sm space-y-1">
                                    <div><strong>{t('admin.call_logs.modal_call_sid')}:</strong> {selectedTranscript.twilio_call_sid}</div>
                                    <div><strong>{t('admin.call_logs.modal_from')}:</strong> {selectedTranscript.from_number}</div>
                                    <div><strong>{t('admin.call_logs.modal_to')}:</strong> {selectedTranscript.to_number}</div>
                                    <div><strong>{t('admin.call_logs.modal_date')}:</strong> {new Date(selectedTranscript.created_at).toLocaleString()}</div>
                                    {selectedTranscript.duration_seconds && (
                                        <div><strong>{t('admin.call_logs.modal_duration')}:</strong> {selectedTranscript.duration_seconds}s</div>
                                    )}
                                    {selectedTranscript.user && (
                                        <div><strong>{t('admin.call_logs.modal_user')}:</strong> {selectedTranscript.user.name}</div>
                                    )}
                                </div>
                            )}
                        </DialogDescription>
                    </DialogHeader>

                    {/* AI Analysis Section */}
                    {selectedTranscript?.sentiment && (
                        <div className="space-y-3 p-4 bg-blue-50 dark:bg-blue-950 rounded-lg border border-blue-200 dark:border-blue-800">
                            <h4 className="font-semibold text-sm flex items-center gap-2">
                                <span className="text-blue-600 dark:text-blue-400">🤖 {t('admin.call_logs.ai_analysis')}</span>
                            </h4>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <div className="text-xs text-muted-foreground mb-1">{t('admin.call_logs.sentiment')}</div>
                                    <Badge variant={(sentimentColors[selectedTranscript.sentiment] || 'outline') as 'default' | 'secondary' | 'destructive' | 'outline'}>
                                        {selectedTranscript.sentiment} ({selectedTranscript.sentiment_confidence}%)
                                    </Badge>
                                </div>
                                {selectedTranscript.lead_quality && (
                                    <div>
                                        <div className="text-xs text-muted-foreground mb-1">{t('admin.call_logs.lead_quality')}</div>
                                        <Badge variant={(leadQualityColors[selectedTranscript.lead_quality] || 'outline') as 'default' | 'secondary' | 'destructive' | 'outline'}>
                                            {selectedTranscript.lead_quality} {selectedTranscript.lead_score && `(${selectedTranscript.lead_score}/10)`}
                                        </Badge>
                                    </div>
                                )}
                            </div>

                            {selectedTranscript.ai_summary && (
                                <div>
                                    <div className="text-xs text-muted-foreground mb-1">{t('admin.call_logs.ai_summary')}</div>
                                    <p className="text-sm leading-relaxed">{selectedTranscript.ai_summary}</p>
                                </div>
                            )}

                            {selectedTranscript.key_intents && selectedTranscript.key_intents.length > 0 && (
                                <div>
                                    <div className="text-xs text-muted-foreground mb-1">{t('admin.call_logs.key_intents')}</div>
                                    <div className="flex flex-wrap gap-1">
                                        {selectedTranscript.key_intents.map((intent, idx) => (
                                            <Badge key={idx} variant="secondary" className="text-xs">
                                                {intent.replace(/_/g, ' ')}
                                            </Badge>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Transcript Section */}
                    {selectedTranscript?.transcript_text && (
                        <div className="mt-4">
                            <h4 className="font-semibold text-sm mb-2">{t('admin.call_logs.full_transcript')}</h4>
                            <div className="bg-slate-50 dark:bg-slate-900 rounded-lg p-4 border">
                                <p className="text-sm leading-relaxed whitespace-pre-wrap">
                                    {selectedTranscript.transcript_text}
                                </p>
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
