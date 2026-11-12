export interface CrmIntegration {
  id: number;
  user_id: number;
  name: string;
  webhook_url: string;
  events: CrmEventType[];
  auth_type: 'none' | 'bearer' | 'api_key' | 'hmac';
  auth_credentials?: {
    token?: string;
    key?: string;
    value?: string;
    secret?: string;
  };
  is_active: boolean;
  last_triggered_at: string | null;
  total_triggers: number;
  last_error: string | null;
  created_at: string;
  updated_at: string;
}

export interface CrmWebhookLog {
  id: number;
  user_id: number;
  crm_integration_id: number | null;
  event_type: string;
  payload: Record<string, any>;
  response_status: number | null;
  response_body: string | null;
  triggered_at: string;
  crm_integration?: {
    id: number;
    name: string;
  };
}

export type CrmEventType =
  | 'call_completed'
  | 'lead_qualified'
  | 'contact_added'
  | 'contact_updated'
  | 'campaign_started'
  | 'dtmf_response';

export interface CrmIntegrationStats {
  total_integrations: number;
  active_integrations: number;
  total_webhooks_sent: number;
  successful_webhooks: number;
  failed_webhooks: number;
  webhooks_last_24h: number;
  success_rate: number;
}

export interface CrmIntegrationFormData {
  name: string;
  webhook_url: string;
  events: CrmEventType[];
  auth_type: 'none' | 'bearer' | 'api_key' | 'hmac';
  auth_credentials?: {
    token?: string;
    key?: string;
    value?: string;
    secret?: string;
  };
  is_active: boolean;
}

export interface WebhookTestResult {
  success: boolean;
  status: number | null;
  response: string | null;
  message: string;
}
