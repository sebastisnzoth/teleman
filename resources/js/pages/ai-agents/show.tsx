import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { Head, router } from '@inertiajs/react';
import { Bot, Phone, TrendingUp, DollarSign, Clock, Edit, Trash2, Power, PlayCircle, StopCircle, Settings, PhoneCall, AlertTriangle, Archive } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { MakeCallDialog } from '@/components/ai-agents/make-call-dialog';
import { CallStatusCard } from '@/components/ai-agents/call-status-card';
import { ConfigurationTestDialog } from '@/components/ai-agents/configuration-test-dialog';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import axios from 'axios';
import { toast } from 'sonner';
import type { AiAgent, AiAgentCall } from '@/types/ai-agent';
import { useFormatCurrency } from '@/hooks/use-currency';
import { isHidden, useTelephony } from '@/lib/telephony';

interface Props {
  id: number;
}

export default function ShowAiAgent({ id }: Props) {
  const { formatCurrency } = useFormatCurrency();
  // Outbound agent calls are not offered on an installation whose
  // telephony provider this product cannot place them through — see
  // config('telephony.hidden_features').
  const { hidden } = useTelephony();
  const [agent, setAgent] = useState<AiAgent | null>(null);
  const [stats, setStats] = useState<any>(null);
  const [recentCalls, setRecentCalls] = useState<AiAgentCall[]>([]);
  const [activeCalls, setActiveCalls] = useState<AiAgentCall[]>([]);
  const [loading, setLoading] = useState(true);
  const [showDeleteModal, setShowDeleteModal] = useState(false);
  const [showMakeCallDialog, setShowMakeCallDialog] = useState(false);
  const [showConfigTestDialog, setShowConfigTestDialog] = useState(false);

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Conversational AI', href: '/ai-agents' },
    { title: agent?.name || 'AI Agent', href: '' },
  ];

  useEffect(() => {
    loadAgent();
    loadRecentCalls();
    loadActiveCalls();
    
    // Poll for active calls every 5 seconds
    const interval = setInterval(() => {
      if (!showDeleteModal) {
        loadActiveCalls();
      }
    }, 5000);
    
    return () => clearInterval(interval);
  }, [id]);

  const loadAgent = async () => {
    try {
      const response = await axios.get(`/api/v1/ai-agents/${id}`);
      setAgent(response.data.data);
      setStats(response.data.stats);
    } catch (error) {
      console.error('Failed to load agent:', error);
      toast.error('Failed to load agent details');
    } finally {
      setLoading(false);
    }
  };

  const loadRecentCalls = async () => {
    try {
      const response = await axios.get(`/api/v1/ai-agents/${id}/calls`);
      setRecentCalls(response.data.data || []);
    } catch (error) {
      console.error('Failed to load calls:', error);
    }
  };

  const loadActiveCalls = async () => {
    try {
      const response = await axios.get(`/api/v1/ai-agent-calls/active`);
      const agentCalls = (response.data || []).filter((call: AiAgentCall) => call.ai_agent_id === id);
      setActiveCalls(agentCalls);
    } catch (error) {
      console.error('Failed to load active calls:', error);
    }
  };

  const toggleAgentStatus = async () => {
    try {
      await axios.patch(`/api/v1/ai-agents/${id}`, {
        active: !agent?.active,
      });
      toast.success(`Agent ${!agent?.active ? 'activated' : 'deactivated'} successfully`);
      loadAgent();
    } catch (error) {
      console.error('Failed to toggle agent status:', error);
      toast.error('Failed to update agent status');
    }
  };

  const deleteAgent = async () => {
    try {
      await axios.delete(`/api/v1/ai-agents/${id}`);
      toast.success('Agent deleted successfully');
      router.visit('/ai-agents');
    } catch (error: any) {
      const message = error.response?.data?.message || 'Failed to delete agent';
      const suggestion = error.response?.data?.suggestion;
      toast.error(message);
      if (suggestion) {
        toast.info(suggestion);
      }
    } finally {
      setShowDeleteModal(false);
    }
  };

  const archiveAgent = async () => {
    try {
      const response = await axios.post(`/api/v1/ai-agents/${id}/archive`);
      toast.success(response.data.message);
      toast.info(`Preserved ${response.data.preserved_calls} call records`);
      router.visit('/ai-agents');
    } catch (error: any) {
      const message = error.response?.data?.message || 'Failed to archive agent';
      toast.error(message);
    } finally {
      setShowDeleteModal(false);
    }
  };

  const handleCallInitiated = (call: AiAgentCall) => {
    setActiveCalls([...activeCalls, call]);
    loadRecentCalls();
    loadAgent(); // Refresh stats
  };

  const handleCallEnded = () => {
    loadActiveCalls();
    loadRecentCalls();
    loadAgent(); // Refresh stats
  };

  if (loading) {
    return (
      <AppLayout breadcrumbs={breadcrumbs}>
        <Head title="Loading..." />
        <div className="flex h-full flex-1 flex-col gap-4 p-4 md:gap-8 md:p-8">
          <div className="flex items-center justify-center h-96">
            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900"></div>
          </div>
        </div>
      </AppLayout>
    );
  }

  if (!agent) {
    return (
      <AppLayout breadcrumbs={breadcrumbs}>
        <Head title="Not Found" />
        <div className="flex h-full flex-1 flex-col gap-4 p-4 md:gap-8 md:p-8">
          <div className="flex flex-col items-center justify-center h-96">
            <h2 className="text-2xl font-bold mb-2">AI Agent Not Found</h2>
            <p className="text-muted-foreground mb-4">The agent you're looking for doesn't exist.</p>
            <Button onClick={() => router.visit('/ai-agents')}>
              Back to AI Agents
            </Button>
          </div>
        </div>
      </AppLayout>
    );
  }

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={agent.name} />
      <div className="flex h-full flex-1 flex-col gap-4 p-4 md:gap-8 md:p-8">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-3">
            <Bot className="h-8 w-8 text-primary" />
            <div>
              <Heading 
                title={agent.name}
                description={agent.description || 'AI-powered conversational agent'}
              />
            </div>
          </div>
          <div className="flex items-center space-x-2">
            {agent.type !== 'inbound' && agent.phone_number && !isHidden('ai_agent_outbound_call', hidden) && (
              <Button
                onClick={() => setShowMakeCallDialog(true)}
                disabled={!agent.active}
              >
                <PhoneCall className="mr-2 h-4 w-4" />
                Make Call
              </Button>
            )}
            <Button
              variant="outline"
              onClick={() => setShowConfigTestDialog(true)}
            >
              <Settings className="mr-2 h-4 w-4" />
              Test Config
            </Button>
            <Button
              variant={agent.active ? 'destructive' : 'default'}
              onClick={toggleAgentStatus}
            >
              {agent.active ? (
                <>
                  <StopCircle className="mr-2 h-4 w-4" />
                  Deactivate
                </>
              ) : (
                <>
                  <PlayCircle className="mr-2 h-4 w-4" />
                  Activate
                </>
              )}
            </Button>
            <Button variant="outline" onClick={() => router.visit(`/ai-agents/${id}/edit`)}>
              <Edit className="mr-2 h-4 w-4" />
              Edit
            </Button>
            <Button variant="outline" onClick={() => setShowDeleteModal(true)}>
              <Trash2 className="mr-2 h-4 w-4" />
              Delete
            </Button>
          </div>
        </div>

        {/* Status Badge */}
        <div className="flex items-center space-x-2">
          <Badge variant={agent.active ? 'default' : 'secondary'} className="text-sm">
            {agent.active ? (
              <>
                <Power className="mr-1 h-3 w-3" />
                Active
              </>
            ) : (
              'Inactive'
            )}
          </Badge>
          <Badge variant="outline">{agent.type}</Badge>
        </div>

        {/* Stats Cards */}
        {stats && (
          <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Total Calls</CardTitle>
                <Phone className="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{stats.total_calls || 0}</div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Success Rate</CardTitle>
                <TrendingUp className="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{stats.success_rate || 0}%</div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Total Cost</CardTitle>
                <DollarSign className="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{formatCurrency(stats.total_cost || 0)}</div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Avg Duration</CardTitle>
                <Clock className="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{Math.round(stats.average_duration || 0)}s</div>
              </CardContent>
            </Card>
          </div>
        )}

        {/* Active Calls */}
        {activeCalls.length > 0 && (
          <div className="space-y-4">
            <h3 className="text-lg font-semibold">Active Calls</h3>
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
              {activeCalls.map((call) => (
                <CallStatusCard
                  key={call.id}
                  call={call}
                  onCallEnded={handleCallEnded}
                />
              ))}
            </div>
          </div>
        )}

        {/* Tabs */}
        <Tabs defaultValue="details" className="space-y-4">
          <TabsList>
            <TabsTrigger value="details">Details</TabsTrigger>
            <TabsTrigger value="calls">Recent Calls</TabsTrigger>
            <TabsTrigger value="configuration">Configuration</TabsTrigger>
          </TabsList>

          <TabsContent value="details" className="space-y-4">
            <Card>
              <CardHeader>
                <CardTitle>Agent Information</CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <p className="text-sm font-medium text-muted-foreground">Type</p>
                    <p className="text-base">{agent.type}</p>
                  </div>
                  <div>
                    <p className="text-sm font-medium text-muted-foreground">Model</p>
                    <p className="text-base">{agent.model}</p>
                  </div>
                  <div>
                    <p className="text-sm font-medium text-muted-foreground">Voice</p>
                    <p className="text-base">{agent.voice}</p>
                  </div>
                  <div>
                    <p className="text-sm font-medium text-muted-foreground">Type</p>
                    <p className="text-base capitalize">{agent.type || 'inbound'}</p>
                  </div>
                  {agent.phone_number && (
                    <div>
                      <p className="text-sm font-medium text-muted-foreground">Phone Number</p>
                      <p className="text-base">{agent.phone_number}</p>
                    </div>
                  )}
                  {agent.transfer_number && (
                    <div>
                      <p className="text-sm font-medium text-muted-foreground">Transfer Number</p>
                      <p className="text-base">{agent.transfer_number}</p>
                    </div>
                  )}
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>System Prompt</CardTitle>
                <CardDescription>The instructions that guide this AI agent's behavior</CardDescription>
              </CardHeader>
              <CardContent>
                <pre className="whitespace-pre-wrap text-sm bg-muted p-4 rounded-md">
                  {agent.system_prompt}
                </pre>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="calls" className="space-y-4">
            <Card>
              <CardHeader>
                <CardTitle>Recent Calls</CardTitle>
                <CardDescription>Latest calls handled by this AI agent</CardDescription>
              </CardHeader>
              <CardContent>
                {recentCalls.length === 0 ? (
                  <p className="text-center text-muted-foreground py-8">No calls yet</p>
                ) : (
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Date</TableHead>
                        <TableHead>Direction</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Duration</TableHead>
                        <TableHead>Cost</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {recentCalls.map((call) => (
                        <TableRow key={call.id}>
                          <TableCell>
                            {new Date(call.created_at).toLocaleString()}
                          </TableCell>
                          <TableCell>
                            <Badge variant="outline">{call.direction}</Badge>
                          </TableCell>
                          <TableCell>
                            <Badge variant={
                              call.status === 'completed' ? 'default' :
                              call.status === 'failed' ? 'destructive' :
                              'secondary'
                            }>
                              {call.status}
                            </Badge>
                          </TableCell>
                          <TableCell>{call.duration || 0}s</TableCell>
                          <TableCell>{formatCurrency(call.cost_estimate || 0)}</TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                )}
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="configuration" className="space-y-4">
            <Card>
              <CardHeader>
                <CardTitle>AI Configuration</CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <p className="text-sm font-medium text-muted-foreground">Max Tokens</p>
                    <p className="text-base">{agent.max_tokens || 150}</p>
                  </div>
                  <div>
                    <p className="text-sm font-medium text-muted-foreground">Temperature</p>
                    <p className="text-base">{agent.temperature || 0.7}</p>
                  </div>
                  {agent.transfer_keywords && agent.transfer_keywords.length > 0 && (
                    <div className="col-span-2">
                      <p className="text-sm font-medium text-muted-foreground mb-2">Transfer Keywords</p>
                      <div className="flex flex-wrap gap-2">
                        {agent.transfer_keywords.map((keyword, index) => (
                          <Badge key={index} variant="secondary">{keyword}</Badge>
                        ))}
                      </div>
                    </div>
                  )}
                </div>
              </CardContent>
            </Card>
          </TabsContent>
        </Tabs>

        {/* Delete Confirmation Modal */}
        <Dialog open={showDeleteModal} onOpenChange={setShowDeleteModal}>
          <DialogContent className="max-w-2xl">
            <DialogHeader>
              <DialogTitle className="flex items-center space-x-2">
                <AlertTriangle className="h-5 w-5 text-destructive" />
                <span>Delete AI Agent?</span>
              </DialogTitle>
              <DialogDescription className="space-y-4 pt-4">
                <div>
                  <p className="font-semibold text-foreground mb-2">
                    You are about to delete <strong>{agent?.name}</strong>
                  </p>
                  
                  {stats && stats.total_calls > 0 && (
                    <div className="bg-destructive/10 border border-destructive/20 rounded-md p-4 space-y-2">
                      <p className="font-semibold text-destructive">⚠️ This will PERMANENTLY delete:</p>
                      <ul className="list-disc pl-5 space-y-1 text-sm">
                        <li><strong>{stats.total_calls}</strong> call records</li>
                        <li>All conversation history and transcripts</li>
                        <li>All cost tracking data ({formatCurrency(stats.total_cost || 0)})</li>
                        <li>All call recordings and analytics</li>
                      </ul>
                    </div>
                  )}
                  
                  <div className="bg-muted rounded-md p-4 mt-4 space-y-2">
                    <p className="font-semibold">💡 Recommended Alternative:</p>
                    <p className="text-sm">
                      Use <strong>Archive</strong> instead to preserve all historical data while deactivating the agent.
                      This allows you to:
                    </p>
                    <ul className="list-disc pl-5 space-y-1 text-sm">
                      <li>Keep all call records for auditing and compliance</li>
                      <li>Maintain cost tracking and analytics</li>
                      <li>Restore the agent later if needed</li>
                    </ul>
                  </div>
                </div>
              </DialogDescription>
            </DialogHeader>
            <div className="flex justify-end space-x-2 mt-4">
              <Button variant="outline" onClick={() => setShowDeleteModal(false)}>
                Cancel
              </Button>
              {stats && stats.total_calls > 0 && (
                <Button variant="default" onClick={archiveAgent}>
                  <Archive className="mr-2 h-4 w-4" />
                  Archive Instead (Recommended)
                </Button>
              )}
              <Button 
                variant="destructive" 
                onClick={deleteAgent}
                disabled={stats && stats.total_calls > 0}
              >
                <Trash2 className="mr-2 h-4 w-4" />
                {stats && stats.total_calls > 0 ? 'Cannot Delete (Has Data)' : 'Delete Permanently'}
              </Button>
            </div>
          </DialogContent>
        </Dialog>

        {/* Make Call Dialog */}
        {agent && (
          <MakeCallDialog
            open={showMakeCallDialog}
            onOpenChange={setShowMakeCallDialog}
            agent={agent}
            onCallInitiated={handleCallInitiated}
          />
        )}

        {/* Configuration Test Dialog */}
        {agent && (
          <ConfigurationTestDialog
            open={showConfigTestDialog}
            onOpenChange={setShowConfigTestDialog}
            agent={agent}
          />
        )}
      </div>
    </AppLayout>
  );
}
