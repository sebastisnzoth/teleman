import { Head, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AppLayout from '@/layouts/app-layout';
import Heading from '@/components/heading';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { RefreshCw, Search, Play, Pause, FileText } from 'lucide-react';
import { type BreadcrumbItem } from '@/types';
import { useState, useRef } from 'react';
import Pagination from '@/components/pagination';

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
    recording_url: string | null;
    recording_duration: number | null;
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
    campaign?: { id: number; name: string };
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
    filters: {
        status?: string;
        type?: string;
        search?: string;
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

function AudioPlayer({ url, duration }: { url: string; duration: number | null }) {
    const audioRef = useRef<HTMLAudioElement>(null);
    const [isPlaying, setIsPlaying] = useState(false);
    const [currentTime, setCurrentTime] = useState(0);

    const togglePlay = () => {
        if (!audioRef.current) return;

        if (isPlaying) {
            audioRef.current.pause();
        } else {
            audioRef.current.play();
        }
        setIsPlaying(!isPlaying);
    };

    const handleTimeUpdate = () => {
        if (audioRef.current) {
            setCurrentTime(audioRef.current.currentTime);
        }
    };

    const handleEnded = () => {
        setIsPlaying(false);
        setCurrentTime(0);
    };

    const formatTime = (seconds: number) => {
        const mins = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    };

    return (
        <div className="flex items-center gap-2">
            <Button
                variant="outline"
                size="sm"
                onClick={togglePlay}
                className="h-8 w-8 p-0"
            >
                {isPlaying ? <Pause className="h-3 w-3" /> : <Play className="h-3 w-3" />}
            </Button>
            <audio
                ref={audioRef}
                src={url}
                onTimeUpdate={handleTimeUpdate}
                onEnded={handleEnded}
            />
            <span className="text-xs text-muted-foreground">
                {formatTime(currentTime)}
                {duration && ` / ${formatTime(duration)}`}
            </span>
        </div>
    );
}

export default function CallLogs({ calls, filters }: Props) {
  const { t } = useTranslation();
    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || 'all');
    const [type, setType] = useState(filters.type || 'all');
    const [sentiment, setSentiment] = useState(filters.sentiment || 'all');
    const [leadQuality, setLeadQuality] = useState(filters.lead_quality || 'all');
    const [selectedTranscript, setSelectedTranscript] = useState<Call | null>(null);

    const handleFilter = () => {
        const params: Record<string, string> = {};
        if (search) params.search = search;
        if (status !== 'all') params.status = status;
        if (type !== 'all') params.type = type;
        if (sentiment !== 'all') params.sentiment = sentiment;
        if (leadQuality !== 'all') params.lead_quality = leadQuality;

        router.get('/my-calls', params, { preserveState: true });
    };

    const handleReset = () => {
        setSearch('');
        setStatus('all');
        setType('all');
        setSentiment('all');
        setLeadQuality('all');
        router.get('/my-calls');
    };

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: t('customer.my_call_history'), href: '/my-calls',
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('customer.my_call_history')} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4 md:gap-8 md:p-8">
                <div className="flex items-center justify-between">
                    <Heading
                        title={t('customer.my_call_history')}
                        description={t('customer.view_all_your_calls_and_listen_to_recordings')}
                    />
                    <Button variant="outline" size="sm" onClick={() => router.reload()}>
                        <RefreshCw className="h-4 w-4 mr-2" />
                        Refresh
                    </Button>
                </div>

                <Card>
                    <CardContent className="pt-6">
                    <div className="flex gap-4">
                        <div className="flex-1">
                            <Input
                                placeholder="Search by phone number..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && handleFilter()}
                            />
                        </div>
                        <Select value={status} onValueChange={setStatus}>
                            <SelectTrigger className="w-[200px]">
                                <SelectValue placeholder="Status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Statuses</SelectItem>
                                <SelectItem value="completed">Completed</SelectItem>
                                <SelectItem value="initiated">Initiated</SelectItem>
                                <SelectItem value="ringing">Ringing</SelectItem>
                                <SelectItem value="in-progress">In Progress</SelectItem>
                                <SelectItem value="busy">Busy</SelectItem>
                                <SelectItem value="failed">Failed</SelectItem>
                                <SelectItem value="no-answer">No Answer</SelectItem>
                                <SelectItem value="canceled">Canceled</SelectItem>
                            </SelectContent>
                        </Select>
                        <Select value={type} onValueChange={setType}>
                            <SelectTrigger className="w-[200px]">
                                <SelectValue placeholder="Type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Types</SelectItem>
                                <SelectItem value="campaign">Campaign</SelectItem>
                                <SelectItem value="manual">Manual</SelectItem>
                            </SelectContent>
                        </Select>
                        <Select value={sentiment} onValueChange={setSentiment}>
                            <SelectTrigger className="w-[200px]">
                                <SelectValue placeholder="Sentiment" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Sentiments</SelectItem>
                                <SelectItem value="positive">Positive</SelectItem>
                                <SelectItem value="neutral">Neutral</SelectItem>
                                <SelectItem value="negative">Negative</SelectItem>
                            </SelectContent>
                        </Select>
                        <Select value={leadQuality} onValueChange={setLeadQuality}>
                            <SelectTrigger className="w-[200px]">
                                <SelectValue placeholder="Lead Quality" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All Lead Qualities</SelectItem>
                                <SelectItem value="hot">Hot</SelectItem>
                                <SelectItem value="warm">Warm</SelectItem>
                                <SelectItem value="cold">Cold</SelectItem>
                                <SelectItem value="not_interested">Not Interested</SelectItem>
                            </SelectContent>
                        </Select>
                        <Button onClick={handleFilter}>
                            <Search className="h-4 w-4 mr-2" />
                            Filter
                        </Button>
                        <Button variant="outline" onClick={handleReset}>
                            Reset
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardContent className="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>From / To</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead>Campaign</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Duration</TableHead>
                                <TableHead className="text-right">Cost</TableHead>
                                <TableHead>AI Analysis</TableHead>
                                <TableHead>Recording</TableHead>
                                <TableHead>Transcript</TableHead>
                                <TableHead>Created</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {calls.data.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={10} className="text-center py-12 text-muted-foreground">
                                        No calls found
                                    </TableCell>
                                </TableRow>
                            ) : (
                                calls.data.map((call) => (
                                    <TableRow key={call.id}>
                                        <TableCell className="font-mono text-sm">
                                            <div className="font-medium">{call.from_number}</div>
                                            <div className="text-muted-foreground">→ {call.to_number}</div>
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline">{call.call_type}</Badge>
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {call.campaign ? (
                                                <div className="font-medium">{call.campaign.name}</div>
                                            ) : (
                                                <span className="text-muted-foreground">—</span>
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
                                            {call.recording_url ? (
                                                <AudioPlayer 
                                                    url={call.recording_url} 
                                                    duration={call.recording_duration} 
                                                />
                                            ) : (
                                                <span className="text-xs text-muted-foreground">No recording</span>
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
                                                    View
                                                </Button>
                                            ) : call.transcript_status === 'in-progress' ? (
                                                <Badge variant="secondary" className="text-xs">Processing...</Badge>
                                            ) : call.recording_url ? (
                                                <span className="text-xs text-muted-foreground">Not available</span>
                                            ) : (
                                                <span className="text-xs text-muted-foreground">—</span>
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
                        <DialogTitle>Call Transcript & AI Analysis</DialogTitle>
                        <DialogDescription>
                            {selectedTranscript && (
                                <div className="text-sm space-y-1">
                                    <div><strong>From:</strong> {selectedTranscript.from_number}</div>
                                    <div><strong>To:</strong> {selectedTranscript.to_number}</div>
                                    <div><strong>Date:</strong> {new Date(selectedTranscript.created_at).toLocaleString()}</div>
                                    {selectedTranscript.duration_seconds && (
                                        <div><strong>Duration:</strong> {selectedTranscript.duration_seconds}s</div>
                                    )}
                                </div>
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    
                    {/* AI Analysis Section */}
                    {selectedTranscript?.sentiment && (
                        <div className="space-y-3 p-4 bg-blue-50 dark:bg-blue-950 rounded-lg border border-blue-200 dark:border-blue-800">
                            <h4 className="font-semibold text-sm flex items-center gap-2">
                                <span className="text-blue-600 dark:text-blue-400">🤖 AI Analysis</span>
                            </h4>
                            
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <div className="text-xs text-muted-foreground mb-1">Sentiment</div>
                                    <Badge variant={(sentimentColors[selectedTranscript.sentiment] || 'outline') as 'default' | 'secondary' | 'destructive' | 'outline'}>
                                        {selectedTranscript.sentiment} ({selectedTranscript.sentiment_confidence}%)
                                    </Badge>
                                </div>
                                {selectedTranscript.lead_quality && (
                                    <div>
                                        <div className="text-xs text-muted-foreground mb-1">Lead Quality</div>
                                        <Badge variant={(leadQualityColors[selectedTranscript.lead_quality] || 'outline') as 'default' | 'secondary' | 'destructive' | 'outline'}>
                                            {selectedTranscript.lead_quality} {selectedTranscript.lead_score && `(${selectedTranscript.lead_score}/10)`}
                                        </Badge>
                                    </div>
                                )}
                            </div>
                            
                            {selectedTranscript.ai_summary && (
                                <div>
                                    <div className="text-xs text-muted-foreground mb-1">AI Summary</div>
                                    <p className="text-sm leading-relaxed">{selectedTranscript.ai_summary}</p>
                                </div>
                            )}
                            
                            {selectedTranscript.key_intents && selectedTranscript.key_intents.length > 0 && (
                                <div>
                                    <div className="text-xs text-muted-foreground mb-1">Key Intents</div>
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
                            <h4 className="font-semibold text-sm mb-2">Full Transcript</h4>
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
