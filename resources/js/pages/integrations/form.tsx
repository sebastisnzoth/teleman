import { useState, FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Checkbox } from '@/components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ArrowLeft } from 'lucide-react';
import { CrmIntegration, CrmEventType, CrmIntegrationFormData } from '@/types/crm';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { dashboard } from '@/routes';
import { PageHelp } from '@/components/page-help';

interface Props {
  integration?: CrmIntegration;
}

const availableEvents: Array<{ value: CrmEventType; label: string; description: string }> = [
  {
    value: 'call_completed',
    label: 'Call Completed',
    description: 'Triggered when a call ends with transcript and sentiment data',
  },
  {
    value: 'lead_qualified',
    label: 'Lead Qualified',
    description: 'Triggered when AI detects a hot lead (high sentiment/score)',
  },
  {
    value: 'contact_added',
    label: 'Contact Added',
    description: 'Triggered when a new contact is created or imported',
  },
  {
    value: 'contact_updated',
    label: 'Contact Updated',
    description: 'Triggered when contact information is modified',
  },
  {
    value: 'campaign_started',
    label: 'Campaign Started',
    description: 'Triggered when a campaign is launched',
  },
  {
    value: 'dtmf_response',
    label: 'DTMF Response',
    description: 'Triggered when a contact presses a phone key',
  },
];

export default function Form({ integration }: Props) {
  const { t } = useTranslation();
  const { name: appName } = usePage<SharedData>().props;
  const isEdit = !!integration;

  const [formData, setFormData] = useState<CrmIntegrationFormData>({
    name: integration?.name || '',
    webhook_url: integration?.webhook_url || '',
    events: integration?.events || [],
    auth_type: integration?.auth_type || 'none',
    auth_credentials: integration?.auth_credentials || {},
    is_active: integration?.is_active !== undefined ? integration.is_active : true,
  });

  const [errors, setErrors] = useState<Record<string, string>>({});
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setIsSubmitting(true);
    setErrors({});

    try {
      const response = await fetch(
        isEdit ? `/api/v1/crm-integrations/${integration.id}` : '/api/v1/crm-integrations',
        {
          method: isEdit ? 'PUT' : 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
          },
          body: JSON.stringify(formData),
        }
      );

      const result = await response.json();

      if (result.success) {
        router.visit('/integrations', {
          onSuccess: () => {
            alert(`Integration ${isEdit ? 'updated' : 'created'} successfully!`);
          },
        });
      } else if (result.errors) {
        setErrors(result.errors);
      }
    } catch (error) {
      alert('Failed to save integration: ' + (error as Error).message);
    } finally {
      setIsSubmitting(false);
    }
  };

  const toggleEvent = (event: CrmEventType) => {
    setFormData((prev) => ({
      ...prev,
      events: prev.events.includes(event)
        ? prev.events.filter((e) => e !== event)
        : [...prev.events, event],
    }));
  };

  const breadcrumbs: BreadcrumbItem[] = [
    { title: t('common.dashboard'), href: dashboard().url },
    { title: t('integrations.crm_integrations'), href: '/integrations' },
    { title: isEdit ? 'Edit Integration' : 'Create Integration', href: '' },
  ];

  const helpSections = [
    {
      title: 'Creating an Integration',
      content: `1. Name: Give your integration a descriptive name (e.g., "ERPNext Production", "Salesforce CRM")
2. Webhook URL: Your CRM's endpoint that will receive data (must accept POST requests)
3. Events: Select which events should trigger webhooks to your CRM
4. Authentication: Choose the method your CRM requires
5. Test: Always test before activating to verify connectivity`,
    },
    {
      title: 'Webhook URL Setup',
      content: `Your CRM must have an endpoint that:
• Accepts HTTP POST requests
• Returns 200 OK status on success
• Processes JSON payload data
• Responds within 30 seconds

Example URLs:
• ERPNext: https://your-erpnext.com/api/method/your_app.api.teleman_webhook
• Salesforce: https://yourinstance.salesforce.com/services/apexrest/teleman
• Custom: https://your-system.com/webhooks/${appName.toLowerCase().replace(/\s+/g, '-')}`,

    },
    {
      title: 'Authentication Setup',
      content: `Bearer Token (Most Common):
• Used by: HubSpot, Salesforce, ERPNext, most OAuth 2.0 systems
• Format: Your access token or "token api_key:api_secret"
• Header sent: Authorization: Bearer YOUR_TOKEN

API Key:
• Used by: Custom systems, Pipedrive, some legacy APIs
• Header Name: Usually X-API-Key (or custom)
• Header Value: Your API key

HMAC Signature:
• Maximum security with cryptographic verification
• Secret: Shared secret key between systems
• Headers sent: X-Teleman-Signature, X-Teleman-Timestamp
• Your CRM must verify signature matches

None:
• For testing only - no authentication headers sent
• Never use in production environments`,
    },
    {
      title: 'Event Selection Guide',
      content: `Choose events based on your CRM needs:

Call Completed:
• Creates call logs in CRM
• Includes: transcript, duration, sentiment, recording URL
• Use for: Call history tracking, conversation analysis

Lead Qualified:
• Creates hot lead records automatically
• Includes: sentiment score (>0.8), qualification reason
• Use for: Sales alerts, opportunity creation

Contact Added/Updated:
• Syncs contacts in real-time
• Includes: name, phone, email, company
• Use for: CRM contact database sync

Campaign Started:
• Notifies when campaigns launch
• Includes: campaign details, target count
• Use for: CRM campaign tracking

DTMF Response:
• Tracks phone keypress responses
• Includes: key pressed, timestamp, call context
• Use for: IVR analytics, survey responses`,
    },
    {
      title: 'Testing Your Integration',
      content: `After creating:
1. Click "Test" button to send sample webhook
2. Check the response status and message
3. Verify data appears in your CRM
4. Review webhook logs for any errors
5. Retry failed webhooks if needed

Sample test payload sent:
{
  "event": "call_completed",
  "call_id": 999,
  "contact_name": "Test User",
  "phone_number": "+15551234567",
  "duration": 120,
  "status": "completed",
  "transcript": "This is a test webhook...",
  "sentiment": "positive"
}`,
    },
    {
      title: 'ERPNext Integration Example',
      content: `To integrate with ERPNext:

1. Create Python endpoint in ERPNext:
# frappe-bench/apps/your_app/api.py
@frappe.whitelist(allow_guest=False)
def teleman_webhook():
    data = frappe.request.get_json()
    if data.get('event') == 'call_completed':
        doc = frappe.get_doc({
            "doctype": "Call Log",
            "duration": data.get('duration'),
            "notes": data.get('transcript')
        })
        doc.insert()
    return {"status": "success"}

2. Get API credentials:
   User → API Access → Generate Keys

3. In ${appName} form:
   • URL: https://your-erpnext.com/api/method/your_app.api.teleman_webhook
   • Auth: Bearer Token
   • Token: token YOUR_API_KEY:YOUR_API_SECRET`,
    },
    {
      title: 'Common Issues & Solutions',
      content: `Problem: "Connection Timeout"
Solution: Your CRM endpoint must respond within 30s. Use background jobs for long operations.

Problem: "401 Unauthorized"
Solution: Double-check API credentials. Test with curl manually first.

Problem: "Invalid SSL Certificate"
Solution: Ensure your CRM has valid HTTPS certificate. Use Let's Encrypt for free SSL.

Problem: "Webhooks not triggering"
Solution: Check integration is Active. Verify events are selected. Ensure Laravel queue is running.

Problem: "Wrong data format"
Solution: Check your CRM expects JSON payload. Review webhook logs for exact payload sent.`,
    },
  ];

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={isEdit ? 'Edit Integration' : 'Create Integration'} />

      <div className="mx-auto max-w-3xl space-y-6">
        {/* Header */}
        <div className="flex items-center gap-4">
          <Link href="/integrations">
            <Button variant="ghost" size="icon">
              <ArrowLeft className="h-5 w-5" />
            </Button>
          </Link>
          <div className="flex-1">
            <Heading
              title={isEdit ? 'Edit Integration' : 'Create CRM Integration'}
              description={t('integrations.connect_teleman_to_your_crm_or_marketing_automatio', { appName })}
            />
          </div>
          <PageHelp 
            title={t('integrations.integration_form_help')} 
            sections={helpSections}
            documentationUrl="/docs/documentation.html#crm-integrations"
          />
        </div>

        <form onSubmit={handleSubmit} className="space-y-6">
          {/* Basic Information */}
          <Card>
            <CardHeader>
              <CardTitle>{t('integrations.basic_information')}</CardTitle>
              <CardDescription>
                Configure the basic settings for your CRM integration
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="space-y-2">
                <Label htmlFor="name">Integration Name</Label>
                <Input
                  id="name"
                  value={formData.name}
                  onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                  placeholder="e.g., HubSpot Production, Salesforce CRM"
                  required
                />
                {errors.name && <p className="text-sm text-red-600">{errors.name}</p>}
              </div>

              <div className="space-y-2">
                <Label htmlFor="webhook_url">Webhook URL</Label>
                <Input
                  id="webhook_url"
                  type="url"
                  value={formData.webhook_url}
                  onChange={(e) => setFormData({ ...formData, webhook_url: e.target.value })}
                  placeholder={`https://your-crm.com/webhooks/${appName.toLowerCase().replace(/\s+/g, '-')}`}
                  required
                />
                <p className="text-sm text-muted-foreground">
                  The endpoint URL where webhook data will be sent
                </p>
                {errors.webhook_url && <p className="text-sm text-red-600">{errors.webhook_url}</p>}
              </div>

              <div className="flex items-center justify-between rounded-lg border p-4">
                <div className="space-y-0.5">
                  <Label htmlFor="is_active">Active Status</Label>
                  <p className="text-sm text-muted-foreground">
                    Enable or disable this integration
                  </p>
                </div>
                <Switch
                  id="is_active"
                  checked={formData.is_active}
                  onCheckedChange={(checked) => setFormData({ ...formData, is_active: checked })}
                />
              </div>
            </CardContent>
          </Card>

          {/* Events */}
          <Card>
            <CardHeader>
              <CardTitle>{t('integrations.events_to_forward')}</CardTitle>
              <CardDescription>
                Select which events should trigger webhooks to your CRM
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-3">
              {availableEvents.map((event) => (
                <div key={event.value} className="flex items-start space-x-3 rounded-lg border p-4">
                  <Checkbox
                    id={event.value}
                    checked={formData.events.includes(event.value)}
                    onCheckedChange={() => toggleEvent(event.value)}
                  />
                  <div className="flex-1 space-y-1">
                    <label
                      htmlFor={event.value}
                      className="cursor-pointer text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                    >
                      {event.label}
                    </label>
                    <p className="text-sm text-muted-foreground">{event.description}</p>
                  </div>
                </div>
              ))}
              {errors.events && <p className="text-sm text-red-600">{errors.events}</p>}
            </CardContent>
          </Card>

          {/* Authentication */}
          <Card>
            <CardHeader>
              <CardTitle>{t('integrations.authentication')}</CardTitle>
              <CardDescription>
                Configure how {appName} authenticates with your webhook endpoint
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="space-y-2">
                <Label htmlFor="auth_type">Authentication Type</Label>
                <Select
                  value={formData.auth_type}
                  onValueChange={(value: any) =>
                    setFormData({ ...formData, auth_type: value, auth_credentials: {} })
                  }
                >
                  <SelectTrigger id="auth_type">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="none">None (No Authentication)</SelectItem>
                    <SelectItem value="bearer">Bearer Token</SelectItem>
                    <SelectItem value="api_key">API Key (Custom Header)</SelectItem>
                    <SelectItem value="hmac">HMAC Signature</SelectItem>
                  </SelectContent>
                </Select>
              </div>

              {formData.auth_type === 'bearer' && (
                <div className="space-y-2">
                  <Label htmlFor="bearer_token">Bearer Token</Label>
                  <Input
                    id="bearer_token"
                    type="password"
                    value={formData.auth_credentials?.token || ''}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        auth_credentials: { token: e.target.value },
                      })
                    }
                    placeholder="your-bearer-token"
                  />
                  <p className="text-sm text-muted-foreground">
                    Sent as: Authorization: Bearer [token]
                  </p>
                </div>
              )}

              {formData.auth_type === 'api_key' && (
                <>
                  <div className="space-y-2">
                    <Label htmlFor="api_key_header">Header Name</Label>
                    <Input
                      id="api_key_header"
                      value={formData.auth_credentials?.key || ''}
                      onChange={(e) =>
                        setFormData({
                          ...formData,
                          auth_credentials: {
                            ...formData.auth_credentials,
                            key: e.target.value,
                          },
                        })
                      }
                      placeholder="X-API-Key"
                    />
                  </div>
                  <div className="space-y-2">
                    <Label htmlFor="api_key_value">API Key Value</Label>
                    <Input
                      id="api_key_value"
                      type="password"
                      value={formData.auth_credentials?.value || ''}
                      onChange={(e) =>
                        setFormData({
                          ...formData,
                          auth_credentials: {
                            ...formData.auth_credentials,
                            value: e.target.value,
                          },
                        })
                      }
                      placeholder="your-api-key"
                    />
                  </div>
                </>
              )}

              {formData.auth_type === 'hmac' && (
                <div className="space-y-2">
                  <Label htmlFor="hmac_secret">HMAC Secret</Label>
                  <Input
                    id="hmac_secret"
                    type="password"
                    value={formData.auth_credentials?.secret || ''}
                    onChange={(e) =>
                      setFormData({
                        ...formData,
                        auth_credentials: { secret: e.target.value },
                      })
                    }
                    placeholder="your-hmac-secret"
                  />
                  <p className="text-sm text-muted-foreground">
                    Signature sent in X-Teleman-Signature header (HMAC-SHA256)
                  </p>
                </div>
              )}
            </CardContent>
          </Card>

          {/* Actions */}
          <div className="flex justify-end gap-3">
            <Link href="/integrations">
              <Button type="button" variant="outline">
                Cancel
              </Button>
            </Link>
            <Button type="submit" disabled={isSubmitting || formData.events.length === 0}>
              {isSubmitting ? 'Saving...' : isEdit ? 'Update Integration' : 'Create Integration'}
            </Button>
          </div>
        </form>
      </div>
    </AppLayout>
  );
}
