import { useEffect, useState } from 'react';
import { Phone, PhoneOff, Clock, User, Mic } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import axios from 'axios';
import { toast } from 'sonner';
import type { AiAgentCall } from '@/types/ai-agent';

interface CallStatusCardProps {
  call: AiAgentCall;
  onCallEnded?: () => void;
}

export function CallStatusCard({ call: initialCall, onCallEnded }: CallStatusCardProps) {
  const [call, setCall] = useState(initialCall);
  const [loading, setLoading] = useState(false);
  const [conversations, setConversations] = useState<any[]>([]);

  // Sync state when prop changes (e.g. different call selected)
  useEffect(() => {
    setCall(initialCall);
  }, [initialCall]);

  // Poll for call updates every 2 seconds
  useEffect(() => {
    const interval = setInterval(async () => {
      try {
        const response = await axios.get(`/api/v1/ai-agent-calls/${call.id}`);
        setCall(response.data.call);
        setConversations(response.data.call.conversations || []);
        
        // Stop polling if call ended
        if (['completed', 'failed'].includes(response.data.call.status)) {
          clearInterval(interval);
          if (onCallEnded) {
            onCallEnded();
          }
        }
      } catch (error) {
        console.error('Failed to fetch call status:', error);
      }
    }, 2000);

    return () => clearInterval(interval);
  }, [call.id]);

  const handleEndCall = async () => {
    setLoading(true);
    try {
      await axios.post(`/api/v1/ai-agent-calls/${call.id}/stop`);
      toast.success('Call ended successfully');
      if (onCallEnded) {
        onCallEnded();
      }
    } catch (error: any) {
      const message = error.response?.data?.message || 'Failed to end call';
      toast.error(message);
    } finally {
      setLoading(false);
    }
  };

  const getStatusBadge = (status: string) => {
    const variants: Record<string, { variant: any; label: string }> = {
      initiated: { variant: 'secondary', label: 'Initiating' },
      ringing: { variant: 'default', label: 'Ringing' },
      'in-progress': { variant: 'default', label: 'Active' },
      completed: { variant: 'secondary', label: 'Completed' },
      failed: { variant: 'destructive', label: 'Failed' },
    };

    const config = variants[status] || { variant: 'secondary', label: status };
    return <Badge variant={config.variant as any}>{config.label}</Badge>;
  };

  const formatDuration = (seconds: number | null) => {
    if (!seconds) return '0:00';
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
  };

  const isActive = ['initiated', 'ringing', 'in-progress'].includes(call.status);

  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
        <CardTitle className="text-sm font-medium">
          <div className="flex items-center gap-2">
            {isActive ? (
              <Phone className="h-4 w-4 text-green-500 animate-pulse" />
            ) : (
              <PhoneOff className="h-4 w-4 text-muted-foreground" />
            )}
            Call {call.id}
          </div>
        </CardTitle>
        {getStatusBadge(call.status)}
      </CardHeader>
      <CardContent>
        <div className="space-y-4">
          {/* Call Details */}
          <div className="grid grid-cols-2 gap-2 text-sm">
            <div className="flex items-center gap-2 text-muted-foreground">
              <User className="h-3 w-3" />
              <span className="truncate">{call.to_number}</span>
            </div>
            <div className="flex items-center gap-2 text-muted-foreground">
              <Clock className="h-3 w-3" />
              <span>{formatDuration(call.duration)}</span>
            </div>
          </div>

          {/* Conversations */}
          {conversations.length > 0 && (
            <div className="space-y-2">
              <div className="flex items-center gap-2 text-sm font-medium">
                <Mic className="h-3 w-3" />
                Conversation
              </div>
              <ScrollArea className="h-32 rounded border p-2">
                <div className="space-y-2">
                  {conversations.map((conv, index) => (
                    <div
                      key={index}
                      className={`text-xs p-2 rounded ${
                        conv.role === 'user'
                          ? 'bg-blue-50 dark:bg-blue-950'
                          : 'bg-muted'
                      }`}
                    >
                      <div className="font-medium mb-1">
                        {conv.role === 'user' ? 'Caller' : 'AI Agent'}:
                      </div>
                      <div className="text-muted-foreground">{conv.content}</div>
                    </div>
                  ))}
                </div>
              </ScrollArea>
            </div>
          )}

          {/* Actions */}
          {isActive && (
            <Button
              variant="destructive"
              size="sm"
              onClick={handleEndCall}
              disabled={loading}
              className="w-full"
            >
              <PhoneOff className="mr-2 h-3 w-3" />
              End Call
            </Button>
          )}
        </div>
      </CardContent>
    </Card>
  );
}
