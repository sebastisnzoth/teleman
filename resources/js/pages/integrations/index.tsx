import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Plus, Activity, CheckCircle2, XCircle, Clock, Trash2, Edit, TestTube } from 'lucide-react';
import { CrmIntegration, CrmIntegrationStats } from '@/types/crm';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { PageHelp } from '@/components/page-help';
import { dashboard } from '@/routes';
import { format } from 'date-fns';

interface Props {
  integrations: CrmIntegration[];
  stats: CrmIntegrationStats;
}

export default function Index({ integrations, stats }: Props) {
  const { t } = useTranslation();
  const { name: appName } = usePage<SharedData>().props;
  const [testingId, setTestingId] = useState<number | null>(null);

  const handleDelete = (id: number) => {
    if (confirm('Are you sure you want to delete this integration?')) {
      router.delete(`/api/v1/crm-integrations/${id}`, {
        preserveScroll: true,
        onSuccess: () => {
          router.reload({ only: ['integrations', 'stats'] });
        },
      });
    }
  };

  const handleTest = async (id: number) => {
    setTestingId(id);
    try {
      const response = await fetch(`/api/v1/crm-integrations/${id}/test`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
      });
      
      const result = await response.json();
      
      if (result.success) {
        alert(`✅ ${result.data.message}\n\nStatus: ${result.data.status}`);
      } else {
        alert(`❌ ${result.data.message}`);
      }
      
      router.reload({ only: ['integrations'] });
    } catch (error) {
      alert('Failed to test webhook: ' + (error as Error).message);
    } finally {
      setTestingId(null);
    }
  };

  const getEventBadgeColor = (event: string) => {
    const colors: Record<string, string> = {
      call_completed: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
      lead_qualified: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
      contact_added: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
      contact_updated: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
      campaign_started: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-400',
      dtmf_response: 'bg-pink-100 text-pink-800 dark:bg-pink-900/30 dark:text-pink-400',
    };
    return colors[event] || 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400';
  };

  const getEventLabel = (event: string) => {
    const labels: Record<string, string> = {
      call_completed: 'Call Completed',
      lead_qualified: 'Lead Qualified',
      contact_added: 'Contact Added',
      contact_updated: 'Contact Updated',
      campaign_started: 'Campaign Started',
      dtmf_response: 'DTMF Response',
    };
    return labels[event] || event;
  };

  const breadcrumbs: BreadcrumbItem[] = [
    {
      title: t('common.dashboard'), href: dashboard().url,
    },
    {
      title: t('integrations.crm_integrations'), href: '/integrations',
    },
  ];

  const helpSections = [
    {
      title: 'What are CRM Integrations?',
      content: `CRM Integrations allow you to automatically send call data, leads, and contact updates to your CRM or marketing tools in real-time using webhooks. ${appName} pushes data TO your CRM via HTTP POST requests - no code changes needed in ${appName} for each CRM.`,
    },
    {
      title: 'How to Integrate Any CRM',
      content: `Step 1: Create a webhook endpoint in your CRM that accepts POST requests with JSON data.
Step 2: Generate API credentials (Bearer token, API key, or HMAC secret).
Step 3: Click "Add Integration" in ${appName}, fill in the webhook URL and credentials.
Step 4: Select which events to track and click "Test Integration" to verify.
Step 5: Activate and monitor webhook logs for delivery status.`,
    },
    {
      title: 'Supported Events',
      content: `• Call Completed: Full call data with transcripts, duration, sentiment analysis
• Lead Qualified: Hot leads detected by AI with sentiment scores
• Contact Added: New contacts created or imported
• Contact Updated: Changes to contact information
• Campaign Started: Campaign launch notifications
• DTMF Response: Phone keypress tracking for IVR`,
    },
    {
      title: 'Authentication Methods',
      content: `• Bearer Token (OAuth 2.0): Standard for most modern CRMs like HubSpot, Salesforce
• API Key: Custom header authentication (e.g., X-API-Key)
• HMAC Signature: Maximum security with cryptographic payload verification
• None: For testing only (not recommended for production)`,
    },
    {
      title: 'Webhook Payload Example',
      content: `When a call completes, your CRM receives:
{
  "event": "call_completed",
  "call_id": 123,
  "contact_name": "John Doe",
  "phone_number": "+15551234567",
  "duration": 120,
  "transcript": "Full conversation...",
  "sentiment_score": 0.85,
  "sentiment": "positive",
  "recording_url": "https://..."
}`,
    },
    {
      title: 'Testing & Monitoring',
      content: 'Use the Test button to send a sample webhook and verify your integration. Check Webhook Logs for delivery history, response status codes, and error messages. Failed webhooks can be retried with a single click. Monitor success rate and recent activity in the stats dashboard.',
    },
    {
      title: 'Popular CRM Examples',
      content: `• ERPNext: Create @frappe.whitelist() endpoint in api.py
• Salesforce: Create Apex REST Resource
• HubSpot: Use workflow webhook trigger or Private App
• Zoho: Create Deluge script or webhook handler
• Pipedrive: Use API endpoint with API Key
• Custom CRM: Any REST API accepting POST requests`,
    },
    {
      title: 'Troubleshooting',
      content: `If webhooks aren't sending: Ensure integration is Active and events are selected. Check Laravel queue is running.

If getting 401 Unauthorized: Verify API credentials are correct. Test token with curl first.

If getting 500 errors: Check your CRM logs for endpoint errors. Ensure data format matches expected structure.

If timeouts occur: CRM endpoint must respond within 30 seconds. Use background jobs for long operations.`,
    },
  ];

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={t('integrations.crm_integrations')} />

      <div className="mx-auto max-w-7xl space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <Heading title={t('integrations.crm_integrations')} description={t('integrations.connect_teleman_to_your_crm_and_marketing_tools', { appName })} />
          <div className="flex items-center gap-2">
            <PageHelp 
              title={t('integrations.crm_integrations_help')} 
              sections={helpSections} 
              documentationUrl="/docs/documentation.html#crm-integrations"
            />
            <Link href="/integrations/create">
              <Button>
                <Plus className="mr-2 h-4 w-4" />
                Add Integration
              </Button>
            </Link>
          </div>
        </div>

        {/* Stats Cards */}
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">{t('integrations.total_integrations')}</CardTitle>
              <Activity className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{stats.total_integrations}</div>
              <p className="text-xs text-muted-foreground">
                {stats.active_integrations} active
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">{t('integrations.webhooks_sent')}</CardTitle>
              <CheckCircle2 className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{stats.total_webhooks_sent}</div>
              <p className="text-xs text-muted-foreground">
                {stats.webhooks_last_24h} in last 24h
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">{t('integrations.success_rate')}</CardTitle>
              <CheckCircle2 className="h-4 w-4 text-green-600 dark:text-green-400" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{stats.success_rate}%</div>
              <p className="text-xs text-muted-foreground">
                {stats.successful_webhooks} successful
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">{t('integrations.failed_webhooks')}</CardTitle>
              <XCircle className="h-4 w-4 text-red-600 dark:text-red-400" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{stats.failed_webhooks}</div>
              <p className="text-xs text-muted-foreground">
                Need attention
              </p>
            </CardContent>
          </Card>
        </div>

        {/* Integrations List */}
        {integrations.length === 0 ? (
          <Card>
            <CardContent className="flex flex-col items-center justify-center py-16">
              <Activity className="mb-4 h-12 w-12 text-muted-foreground" />
              <h3 className="mb-2 text-lg font-semibold">No CRM integrations yet</h3>
              <p className="mb-6 text-center text-muted-foreground">
                Connect your CRM to automatically sync calls, leads, and contacts
              </p>
              <Link href="/integrations/create">
                <Button>
                  <Plus className="mr-2 h-4 w-4" />
                  Create Your First Integration
                </Button>
              </Link>
            </CardContent>
          </Card>
        ) : (
          <div className="grid gap-6 md:grid-cols-2">
            {integrations.map((integration) => (
              <Card key={integration.id} className={!integration.is_active ? 'opacity-60' : ''}>
                <CardHeader>
                  <div className="flex items-start justify-between">
                    <div className="flex-1">
                      <CardTitle className="flex items-center gap-2">
                        {integration.name}
                        {integration.is_active ? (
                          <Badge variant="default" className="bg-green-600">
                            <CheckCircle2 className="mr-1 h-3 w-3" />
                            Active
                          </Badge>
                        ) : (
                          <Badge variant="secondary">Inactive</Badge>
                        )}
                      </CardTitle>
                      <CardDescription className="mt-2 break-all">
                        {integration.webhook_url}
                      </CardDescription>
                    </div>
                  </div>
                </CardHeader>
                <CardContent className="space-y-4">
                  {/* Events */}
                  <div>
                    <p className="mb-2 text-sm font-medium">Subscribed Events</p>
                    <div className="flex flex-wrap gap-2">
                      {integration.events.map((event) => (
                        <Badge
                          key={event}
                          variant="secondary"
                          className={getEventBadgeColor(event)}
                        >
                          {getEventLabel(event)}
                        </Badge>
                      ))}
                    </div>
                  </div>

                  {/* Stats */}
                  <div className="flex items-center gap-4 text-sm text-muted-foreground">
                    <div className="flex items-center gap-1">
                      <Clock className="h-4 w-4" />
                      <span>
                        {integration.total_triggers} triggers
                      </span>
                    </div>
                    {integration.last_triggered_at && (
                      <div>
                        Last: {format(new Date(integration.last_triggered_at), 'MMM d, HH:mm')}
                      </div>
                    )}
                  </div>

                  {/* Last Error */}
                  {integration.last_error && (
                    <div className="rounded-md bg-red-50 p-3 dark:bg-red-950/20">
                      <p className="text-sm text-red-800 dark:text-red-400">
                        <strong>Last Error:</strong> {integration.last_error}
                      </p>
                    </div>
                  )}

                  {/* Actions */}
                  <div className="flex gap-2">
                    <Button
                      variant="outline"
                      size="sm"
                      onClick={() => handleTest(integration.id)}
                      disabled={testingId === integration.id}
                    >
                      <TestTube className="mr-2 h-4 w-4" />
                      {testingId === integration.id ? 'Testing...' : 'Test'}
                    </Button>
                    <Link href={`/integrations/${integration.id}/logs`}>
                      <Button variant="outline" size="sm">
                        View Logs
                      </Button>
                    </Link>
                    <Link href={`/integrations/${integration.id}/edit`}>
                      <Button variant="outline" size="sm">
                        <Edit className="mr-2 h-4 w-4" />
                        Edit
                      </Button>
                    </Link>
                    <Button
                      variant="destructive"
                      size="sm"
                      onClick={() => handleDelete(integration.id)}
                    >
                      <Trash2 className="mr-2 h-4 w-4" />
                      Delete
                    </Button>
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>
        )}
      </div>
    </AppLayout>
  );
}
