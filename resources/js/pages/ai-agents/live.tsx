import { useState, useEffect, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { Radio, Phone, Clock, User, RefreshCw } from 'lucide-react';
import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import Heading from '@/components/heading';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import axios from 'axios';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import type { AiAgentCall, AiAgentConversation } from '@/types/ai-agent';
import { useFormatCurrency } from '@/hooks/use-currency';

export default function LiveCalls() {
  const { t } = useTranslation();
  const { formatCurrency } = useFormatCurrency();
  const breadcrumbs: BreadcrumbItem[] = [
  { title: t('common.dashboard'), href: dashboard().url },
  { title: t('nav.conversational_ai'), href: '/ai-agents' },
  { title: t('nav.live_calls'), href: '/ai-agents/live' },
];
  const [activeCalls, setActiveCalls] = useState<AiAgentCall[]>([]);
  const [selectedCall, setSelectedCall] = useState<AiAgentCall | null>(null);
  const [conversations, setConversations] = useState<AiAgentConversation[]>([]);
  const [loading, setLoading] = useState(true);
  const [syncing, setSyncing] = useState(false);
  
  // Use refs to track ongoing requests and intervals
  const abortControllerRef = useRef<AbortController | null>(null);
  const refreshIntervalRef = useRef<NodeJS.Timeout | null>(null);
  const isLoadingRef = useRef(false);
  const selectedCallIdRef = useRef<number | null>(null);

  // Calculate agent statistics
  const getAgentStats = () => {
    const stats = new Map<number, { name: string; callCount: number; inProgress: number }>();
    
    activeCalls.forEach(call => {
      if (call.agent) {
        const existing = stats.get(call.agent.id) || { name: call.agent.name, callCount: 0, inProgress: 0 };
        existing.callCount++;
        if (call.status === 'in-progress') {
          existing.inProgress++;
        }
        stats.set(call.agent.id, existing);
      }
    });
    
    return Array.from(stats.entries()).map(([id, data]) => ({ id, ...data }));
  };

  // Initial load - fetch active calls once
  useEffect(() => {
    loadActiveCalls();
    
    // Cleanup on unmount
    return () => {
      if (abortControllerRef.current) {
        abortControllerRef.current.abort();
      }
      if (refreshIntervalRef.current) {
        clearInterval(refreshIntervalRef.current);
      }
    };
  }, []);

  // Auto-refresh only the selected call details with proper cleanup
  useEffect(() => {
    // Clear any existing interval
    if (refreshIntervalRef.current) {
      clearInterval(refreshIntervalRef.current);
      refreshIntervalRef.current = null;
    }

    const callId = selectedCallIdRef.current;
    if (callId) {
      // Initial load
      loadCallDetails(callId);
      
      // Set up interval with longer delay (5 seconds) to prevent overload
      refreshIntervalRef.current = setInterval(() => {
        // Only load if not already loading and not syncing
        if (!isLoadingRef.current && !syncing) {
          loadCallDetails(callId);
        }
      }, 5000); // Increased to 5 seconds to reduce load
    }

    return () => {
      if (refreshIntervalRef.current) {
        clearInterval(refreshIntervalRef.current);
        refreshIntervalRef.current = null;
      }
    };
  }, [selectedCall?.id, syncing]); // Use selectedCall state (not ref) for reactivity

  const loadActiveCalls = async () => {
    try {
      const response = await axios.get('/api/v1/ai-agent-calls/active');
      const newCalls = response.data;
      setActiveCalls(newCalls);
      
      // Auto-select first call ONLY on initial load (when loading is true)
      if (loading && !selectedCallIdRef.current && newCalls.length > 0) {
        setSelectedCall(newCalls[0]);
        selectedCallIdRef.current = newCalls[0].id;
      }
      // If there's a selected call, update it with fresh data from the list
      else if (selectedCallIdRef.current) {
        const updatedCall = newCalls.find(c => c.id === selectedCallIdRef.current);
        if (updatedCall) {
          // Only update if the data is different to prevent unnecessary re-renders
          setSelectedCall(prev => {
            if (JSON.stringify(prev) !== JSON.stringify(updatedCall)) {
              return updatedCall;
            }
            return prev;
          });
        }
      }
    } catch (error) {
      console.error('Failed to load active calls:', error);
    } finally {
      setLoading(false);
    }
  };

  const loadCallDetails = async (callId: number) => {
    // Prevent concurrent requests
    if (isLoadingRef.current) {
      return;
    }

    isLoadingRef.current = true;

    // Cancel any ongoing request
    if (abortControllerRef.current) {
      abortControllerRef.current.abort();
    }

    // Create new abort controller for this request
    const controller = new AbortController();
    abortControllerRef.current = controller;

    try {
      const response = await axios.get(`/api/v1/ai-agent-calls/${callId}`, {
        signal: controller.signal,
      });
      const callData = response.data.call;
      
      // Check if call has ended
      if (callData.status === 'completed' || callData.status === 'failed' || callData.ended_at) {
        // Trigger final sync with Twilio to get accurate final details
        await syncCallWithTwilio(callId);
        
        // Remove from active calls after a short delay to show final details
        setTimeout(() => {
          setActiveCalls(prevCalls => prevCalls.filter(call => call.id !== callId));
          
          // If this was the selected call, clear selection
          if (selectedCallIdRef.current === callId) {
            setSelectedCall(null);
            setConversations([]);
            selectedCallIdRef.current = null;
          }
        }, 3000); // Show final details for 3 seconds
        return;
      }
      
      // Only update if this call is still selected (prevent race conditions)
      if (selectedCallIdRef.current === callId) {
        // Update selected call with fresh data (only if changed)
        setSelectedCall(prev => {
          if (JSON.stringify(prev) !== JSON.stringify(callData)) {
            return callData;
          }
          return prev;
        });
        
        // Update conversations (only if changed)
        setConversations(prev => {
          const newConvs = callData.conversations || [];
          if (JSON.stringify(prev) !== JSON.stringify(newConvs)) {
            return newConvs;
          }
          return prev;
        });
      }
      
      // Update the call in the active calls list
      setActiveCalls(prevCalls => 
        prevCalls.map(call => call.id === callId ? callData : call)
      );
    } catch (error) {
      // Ignore abort errors
      if (axios.isCancel(error)) {
        return;
      }
      
      console.error('Failed to load call details:', error);
      // If 404, remove from list
      if ((error as any)?.response?.status === 404) {
        setActiveCalls(prevCalls => prevCalls.filter(call => call.id !== callId));
        if (selectedCallIdRef.current === callId) {
          setSelectedCall(null);
          setConversations([]);
          selectedCallIdRef.current = null;
        }
      }
    } finally {
      isLoadingRef.current = false;
      abortControllerRef.current = null;
    }
  };

  const syncCallWithTwilio = async (callId: number) => {
    // Don't sync if already syncing
    if (syncing) {
      return;
    }

    setSyncing(true);
    
    try {
      await axios.post(`/api/v1/ai-agent-calls/${callId}/sync`);
      // Wait a bit before reloading to let sync complete
      await new Promise(resolve => setTimeout(resolve, 500));
      // Only reload if not already loading
      if (!isLoadingRef.current) {
        await loadCallDetails(callId);
      }
    } catch (error) {
      console.error('Failed to sync call with Twilio:', error);
    } finally {
      setSyncing(false);
    }
  };

  const stopCall = async (callId: number) => {
    try {
      await axios.post(`/api/v1/ai-agent-calls/${callId}/stop`);
      loadActiveCalls();
      if (selectedCallIdRef.current === callId) {
        setSelectedCall(null);
        setConversations([]);
        selectedCallIdRef.current = null;
      }
    } catch (error) {
      console.error('Failed to stop call:', error);
    }
  };

  const formatDuration = (seconds: number | null) => {
    if (!seconds) return '0:00';
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
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
      <Head title="Live Calls" />
      <div className="flex h-full flex-1 flex-col gap-4 p-4 md:gap-8 md:p-8">
        <Heading
          title="Live Calls"
          description="Monitor AI agent conversations in real-time with live transcripts"
        />

      {activeCalls.length === 0 ? (
        <Card>
          <CardContent className="flex flex-col items-center justify-center py-16">
            <div className="rounded-full bg-muted p-6 mb-4">
              <Phone className="h-12 w-12 text-muted-foreground" />
            </div>
            <h3 className="text-xl font-semibold mb-2">No Active Calls</h3>
            <p className="text-muted-foreground text-center max-w-sm">
              All AI agents are currently idle. Active calls will appear here automatically.
            </p>
          </CardContent>
        </Card>
      ) : (
        <>
          {/* Compact Agent Summary */}
          {getAgentStats().length > 0 && (
            <Card className="mb-6">
              <CardContent className="py-3 px-4">
                <div className="flex items-center gap-6 flex-wrap">
                  <div className="flex items-center gap-2 text-sm text-muted-foreground">
                    <span className="font-medium">Active Agents:</span>
                    <Badge variant="outline">{getAgentStats().length}</Badge>
                  </div>
                  {getAgentStats().map((agent) => (
                    <div 
                      key={agent.id} 
                      className="flex items-center gap-2 text-sm"
                      title={`${agent.name}: ${agent.inProgress} in-progress call${agent.inProgress !== 1 ? 's' : ''} out of ${agent.callCount} total active call${agent.callCount !== 1 ? 's' : ''}`}
                    >
                      <div className="h-2 w-2 rounded-full bg-green-500 animate-pulse" />
                      <span className="font-medium">{agent.name}</span>
                      <Badge variant="secondary" className="text-xs h-5">
                        {agent.inProgress}/{agent.callCount}
                      </Badge>
                    </div>
                  ))}
                </div>
              </CardContent>
            </Card>
          )}

          <div className="grid gap-6 lg:grid-cols-3">
          {/* Active Calls List */}
          <Card className="lg:col-span-1">
            <CardHeader>
              <div className="flex items-center justify-between">
                <CardTitle className="text-lg">
                  Active Calls ({activeCalls.length})
                </CardTitle>
                <Button 
                  variant="ghost" 
                  size="sm" 
                  onClick={loadActiveCalls}
                  className="h-8 w-8 p-0"
                >
                  <RefreshCw className="h-4 w-4" />
                </Button>
              </div>
            </CardHeader>
            <CardContent className="p-0">
              <ScrollArea className="h-[600px]">
                {activeCalls.map((call) => (
                  <div
                    key={call.id}
                    className={`p-4 border-b cursor-pointer hover:bg-accent transition-colors ${
                      selectedCall?.id === call.id ? 'bg-accent border-l-4 border-l-primary' : ''
                    }`}
                    onClick={() => {
                      setSelectedCall(call);
                      selectedCallIdRef.current = call.id;
                    }}
                  >
                    <div className="flex items-start justify-between mb-2">
                      <div className="flex items-center space-x-2">
                        {call.status === 'in-progress' ? (
                          <Radio className="h-4 w-4 text-green-500 animate-pulse" />
                        ) : (
                          <Radio className="h-4 w-4 text-yellow-500 animate-pulse" />
                        )}
                        <div className="flex items-center gap-2">
                          <span className="font-medium">{call.agent?.name}</span>
                          <div className="h-2 w-2 rounded-full bg-green-500" />
                        </div>
                      </div>
                      <Badge variant={call.status === 'in-progress' ? 'default' : 'secondary'}>
                        {call.status}
                      </Badge>
                    </div>
                    <div className="space-y-1 text-sm text-muted-foreground">
                      <div className="flex items-center space-x-2">
                        <Phone className="h-3 w-3" />
                        <span>{call.from_number} → {call.to_number}</span>
                      </div>
                      <div className="flex items-center space-x-2">
                        <Clock className="h-3 w-3" />
                        <span>{formatDuration(call.duration)}</span>
                        <span className="text-xs">• {call.turn_count} turns</span>
                      </div>
                    </div>
                  </div>
                ))}
              </ScrollArea>
            </CardContent>
          </Card>

          {/* Call Details & Transcript */}
          {selectedCall && (
            <Card className="lg:col-span-2">
              <CardHeader>
                <div className="flex items-center justify-between">
                  <div>
                    <CardTitle>{selectedCall.agent?.name}</CardTitle>
                    <CardDescription>
                      {selectedCall.from_number} → {selectedCall.to_number}
                    </CardDescription>
                  </div>
                  <div className="flex items-center space-x-4">
                    <div className="text-right">
                      <p className="text-sm text-muted-foreground">Duration</p>
                      <p className="font-semibold">{formatDuration(selectedCall.duration)}</p>
                    </div>
                    <Button variant="destructive" size="sm" onClick={() => stopCall(selectedCall.id)}>
                      End Call
                    </Button>
                  </div>
                </div>
              </CardHeader>
              <CardContent>
                <div className="space-y-2 mb-4">
                  <div className="grid grid-cols-4 gap-4 text-sm">
                    <div>
                      <p className="text-muted-foreground">Status</p>
                      <Badge variant="default">{selectedCall.status}</Badge>
                    </div>
                    <div>
                      <p className="text-muted-foreground">Call SID</p>
                      <p className="font-mono text-xs">{selectedCall.call_sid}</p>
                    </div>
                    <div>
                      <p className="text-muted-foreground">Turns</p>
                      <p className="font-semibold">{selectedCall.turn_count}</p>
                    </div>
                    <div>
                      <p className="text-muted-foreground">Cost Est.</p>
                      <p className="font-semibold">{formatCurrency(Number(selectedCall.cost_estimate))}</p>
                    </div>
                  </div>
                </div>

                <div className="border-t pt-4">
                  <h3 className="font-semibold mb-4">Live Transcript</h3>
                  <ScrollArea className="h-[450px]">
                    <div className="space-y-4">
                      {conversations.length === 0 ? (
                        <p className="text-muted-foreground text-center py-8">
                          Waiting for conversation...
                        </p>
                      ) : (
                        conversations.map((turn) => (
                          <div
                            key={turn.id}
                            className={`flex ${turn.role === 'user' ? 'justify-start' : 'justify-end'}`}
                          >
                            <div
                              className={`max-w-[80%] rounded-lg p-3 ${
                                turn.role === 'user'
                                  ? 'bg-muted'
                                  : turn.role === 'assistant'
                                  ? 'bg-primary text-primary-foreground'
                                  : 'bg-accent'
                              }`}
                            >
                              <div className="flex items-center space-x-2 mb-1">
                                <User className="h-3 w-3" />
                                <span className="text-xs font-semibold">
                                  {turn.role === 'user' ? 'Customer' : turn.role === 'assistant' ? 'AI Agent' : 'System'}
                                </span>
                                {turn.confidence && (
                                  <Badge variant="outline" className="text-xs">
                                    {(turn.confidence * 100).toFixed(0)}%
                                  </Badge>
                                )}
                              </div>
                              <p className="text-sm">{turn.content}</p>
                              {turn.duration_ms && (
                                <p className="text-xs opacity-70 mt-1">
                                  {(turn.duration_ms / 1000).toFixed(1)}s
                                </p>
                              )}
                            </div>
                          </div>
                        ))
                      )}
                    </div>
                  </ScrollArea>
                </div>
              </CardContent>
            </Card>
          )}
        </div>
        </>
      )}
      </div>
    </AppLayout>
  );
}
