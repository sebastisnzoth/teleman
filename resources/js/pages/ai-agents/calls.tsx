import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { History, Phone, Clock, DollarSign, Filter, Download, Eye, Volume2 } from 'lucide-react';
import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import Heading from '@/components/heading';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import axios from 'axios';
import type { AiAgentCall, AiAgentConversation } from '@/types/ai-agent';
import { useFormatCurrency } from '@/hooks/use-currency';

export default function AiAgentCalls() {
  const { t } = useTranslation();
  const { formatCurrency } = useFormatCurrency();
  const breadcrumbs: BreadcrumbItem[] = [
    { title: t('common.dashboard'), href: dashboard().url },
    { title: t('ai_agents.title'), href: '/ai-agents' },
    { title: t('ai_agents.call_history'), href: '' },
  ];
  const [calls, setCalls] = useState<AiAgentCall[]>([]);
  const [loading, setLoading] = useState(true);
  const [selectedCall, setSelectedCall] = useState<AiAgentCall | null>(null);
  const [transcript, setTranscript] = useState('');
  const [showTranscript, setShowTranscript] = useState(false);
  
  const [filters, setFilters] = useState({
    status: 'all',
    direction: 'all',
    from_date: '',
    to_date: '',
  });

  useEffect(() => {
    loadCalls();
  }, [filters]);

  const loadCalls = async () => {
    try {
      const params = new URLSearchParams();
      Object.entries(filters).forEach(([key, value]) => {
        if (value && value !== 'all') params.append(key, value);
      });

      const response = await axios.get(`/api/v1/ai-agent-calls?${params.toString()}`);
      setCalls(response.data.data || []);
    } catch (error) {
      console.error('Failed to load calls:', error);
    } finally {
      setLoading(false);
    }
  };

  const viewTranscript = async (call: AiAgentCall) => {
    try {
      const response = await axios.get(`/api/v1/ai-agent-calls/${call.id}/transcript`);
      setSelectedCall(call);
      setTranscript(response.data.transcript);
      setShowTranscript(true);
    } catch (error) {
      console.error('Failed to load transcript:', error);
    }
  };

  const formatDuration = (seconds: number | null) => {
    if (!seconds) return '-';
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'completed': return 'default';
      case 'in-progress': return 'secondary';
      case 'failed': return 'destructive';
      default: return 'outline';
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-96">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900"></div>
      </div>
    );
  }

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={t('ai_agents.call_history')} />
      <div className="flex h-full flex-1 flex-col gap-4 p-4 md:gap-8 md:p-8">
        {/* Header */}
        <div className="flex items-center justify-between">
          <Heading
            title={t('ai_agents.call_history')}
            description={t('ai_agents.call_history_description')}
          />
          <Button 
            variant="outline" 
            size="lg"
            onClick={() => {
              const params = new URLSearchParams();
              if (filters.status && filters.status !== 'all') params.append('status', filters.status);
              if (filters.direction && filters.direction !== 'all') params.append('direction', filters.direction);
              if (filters.from_date) params.append('from_date', filters.from_date);
              if (filters.to_date) params.append('to_date', filters.to_date);
              window.location.href = `/api/v1/ai-agent-calls/export?${params.toString()}`;
            }}
          >
            <Download className="mr-2 h-4 w-4" />
            {t('ai_agents.export_data')}
          </Button>
        </div>

      {/* Filters */}
      <Card>
        <CardHeader>
          <CardTitle className="text-lg flex items-center">
            <Filter className="mr-2 h-5 w-5" />
            {t('common.filters')}
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
            <Select
              value={filters.status}
              onValueChange={(value) => setFilters({ ...filters, status: value })}
            >
              <SelectTrigger>
                <SelectValue placeholder={t('ai_agents.all_statuses')} />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">{t('ai_agents.all_statuses')}</SelectItem>
                <SelectItem value="completed">{t('common.completed')}</SelectItem>
                <SelectItem value="failed">{t('common.failed')}</SelectItem>
                <SelectItem value="in_progress">{t('common.in_progress')}</SelectItem>
              </SelectContent>
            </Select>

            <Select
              value={filters.direction}
              onValueChange={(value) => setFilters({ ...filters, direction: value })}
            >
              <SelectTrigger>
                <SelectValue placeholder={t('ai_agents.all_directions')} />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">{t('ai_agents.all_directions')}</SelectItem>
                <SelectItem value="inbound">{t('calls.inbound')}</SelectItem>
                <SelectItem value="outbound">{t('calls.outbound')}</SelectItem>
              </SelectContent>
            </Select>

            <Input
              type="date"
              value={filters.from_date}
              onChange={(e) => setFilters({ ...filters, from_date: e.target.value })}
              placeholder="From Date"
            />

            <Input
              type="date"
              value={filters.to_date}
              onChange={(e) => setFilters({ ...filters, to_date: e.target.value })}
              placeholder="To Date"
            />
          </div>
        </CardContent>
      </Card>

      {/* Calls Table */}
      <Card>
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>{t('common.agent')}</TableHead>
              <TableHead>{t('ai_agents.direction')}</TableHead>
              <TableHead>{t('ai_agents.numbers')}</TableHead>
              <TableHead>{t('common.status')}</TableHead>
              <TableHead>{t('calls.duration')}</TableHead>
              <TableHead>{t('ai_agents.turns')}</TableHead>
              <TableHead>{t('common.cost')}</TableHead>
              <TableHead>{t('ai_agents.started_at')}</TableHead>
              <TableHead>{t('common.actions')}</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {calls.length === 0 ? (
              <TableRow>
                <TableCell colSpan={9} className="text-center py-8 text-muted-foreground">
                  {t('calls.no_calls')}
                </TableCell>
              </TableRow>
            ) : (
              calls.map((call) => (
                <TableRow key={call.id}>
                  <TableCell className="font-medium">{call.agent?.name}</TableCell>
                  <TableCell>
                    <Badge variant="outline">{call.direction}</Badge>
                  </TableCell>
                  <TableCell className="text-sm">
                    <div>{call.from_number}</div>
                    <div className="text-muted-foreground">→ {call.to_number}</div>
                  </TableCell>
                  <TableCell>
                    <Badge variant={getStatusColor(call.status)}>{call.status}</Badge>
                  </TableCell>
                  <TableCell>
                    <div className="flex items-center space-x-1">
                      <Clock className="h-3 w-3" />
                      <span>{formatDuration(call.duration)}</span>
                    </div>
                  </TableCell>
                  <TableCell>{call.turn_count}</TableCell>
                  <TableCell>
                    {call.twilio_cost || call.openai_cost ? (
                      <div className="flex flex-col space-y-1">
                        <div className="flex items-center space-x-1">
                          <DollarSign className="h-3 w-3" />
                          <span className="font-medium">{formatCurrency(Number(call.cost_estimate || 0))}</span>
                        </div>
                        <div className="text-xs text-muted-foreground space-y-0.5">
                          {call.twilio_cost > 0 && (
                            <div>{t('ai_agents.telephony_cost')}: {formatCurrency(Number(call.twilio_cost))}</div>
                          )}
                          {call.openai_cost > 0 && (
                            <div>{t('ai_agents.llm_cost')}: {formatCurrency(Number(call.openai_cost))}</div>
                          )}
                        </div>
                      </div>
                    ) : (
                      <span className="text-muted-foreground">-</span>
                    )}
                  </TableCell>
                  <TableCell className="text-sm">
                    {new Date(call.started_at).toLocaleString()}
                  </TableCell>
                  <TableCell>
                    <div className="flex items-center gap-1">
                      <Button
                        variant="ghost"
                        size="sm"
                        title={t('ai_agents.view_transcript')}
                        onClick={() => viewTranscript(call)}
                      >
                        <Eye className="h-4 w-4" />
                      </Button>
                      {call.recording_url && (
                        <Volume2
                          className="h-4 w-4 text-green-600"
                          aria-label="Recording available"
                        />
                      )}
                    </div>
                  </TableCell>
                </TableRow>
              ))
            )}
          </TableBody>
        </Table>
      </Card>

      {/* Transcript Dialog */}
      <Dialog open={showTranscript} onOpenChange={setShowTranscript}>
        <DialogContent className="max-w-3xl">
          <DialogHeader>
            <DialogTitle>{t('ai_agents.call_transcript')}</DialogTitle>
            <DialogDescription>
              {selectedCall && (
                <>
                  {selectedCall.agent?.name} • {selectedCall.from_number} → {selectedCall.to_number}
                  <br />
                  {new Date(selectedCall.started_at).toLocaleString()} • Duration: {formatDuration(selectedCall.duration)}
                </>
              )}
            </DialogDescription>
          </DialogHeader>

          {/* Call Recording Player — streams through our backend so the
              browser's audio tag can authenticate against Twilio. */}
          {selectedCall?.recording_url && (
            <div className="mt-4 rounded-md border border-border bg-muted/40 p-3">
              <div className="mb-2 flex items-center gap-2 text-sm font-medium">
                <Volume2 className="h-4 w-4 text-green-600" />
                {t('ai_agents.call_recording')}
              </div>
              <audio
                controls
                preload="metadata"
                className="w-full"
                src={`/api/v1/ai-agent-calls/${selectedCall.id}/recording`}
              >
                Your browser does not support the audio element.
              </audio>
              <div className="mt-1 text-right">
                <a
                  href={`/api/v1/ai-agent-calls/${selectedCall.id}/recording?download=1`}
                  className="text-xs text-muted-foreground hover:text-foreground underline"
                >
                  {t('common.download')}
                </a>
              </div>
            </div>
          )}

          <ScrollArea className="h-[400px] mt-4">
            <div className="whitespace-pre-wrap text-sm">{transcript}</div>
          </ScrollArea>
        </DialogContent>
      </Dialog>
      </div>
    </AppLayout>
  );
}
