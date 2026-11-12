export interface AiAgent {
  id: number;
  name: string;
  description: string | null;
  type: 'inbound' | 'outbound' | 'both';
  system_prompt: string;
  first_message?: string | null;
  goodbye_message?: string | null;
  knowledge_base?: string | null;
  knowledge_base_id?: number | null;
  model: string;
  voice: string;
  max_tokens: number;
  temperature: number;
  active: boolean;
  phone_number?: string | null;
  twilio_auto_configured?: boolean;
  twilio_webhook_sid?: string | null;
  settings: Record<string, any> | null;
  enable_transfer: boolean;
  transfer_number: string | null;
  transfer_keywords?: string[] | null;
  enable_recording: boolean;
  max_duration: number;
  silence_timeout: number;
  response_timeout?: number;
  trigger_keywords: string[] | null;
  created_at: string;
  updated_at: string;
  deleted_at: string | null;
  calls_count?: number;
  active_calls_count?: number;
  completed_calls_count?: number;
}

export interface AiAgentCall {
  id: number;
  ai_agent_id: number;
  call_id: number | null;
  contact_id: number | null;
  campaign_id: number | null;
  call_sid: string;
  direction: 'inbound' | 'outbound';
  from_number: string;
  to_number: string;
  status: 'initiated' | 'ringing' | 'in-progress' | 'completed' | 'failed';
  duration: number | null;
  started_at: string;
  answered_at: string | null;
  ended_at: string | null;
  end_reason: string | null;
  recording_url: string | null;
  turn_count: number;
  transferred: boolean;
  transferred_to: string | null;
  cost_estimate: number;
  twilio_cost: number;
  openai_cost: number;
  total_tokens: number;
  input_tokens: number;
  output_tokens: number;
  metadata: Record<string, any> | null;
  created_at: string;
  updated_at: string;
  agent?: AiAgent;
  contact?: any;
  campaign?: any;
  conversations?: AiAgentConversation[];
}

export interface AiAgentConversation {
  id: number;
  ai_agent_call_id: number;
  turn_number: number;
  role: 'user' | 'assistant' | 'system';
  content: string;
  audio_url: string | null;
  duration_ms: number | null;
  confidence: number | null;
  metadata: Record<string, any> | null;
  created_at: string;
  updated_at: string;
}

export interface AiAgentStats {
  total_calls: number;
  completed_calls: number;
  success_rate: number;
  average_duration: number;
  total_cost: number;
}

export interface AiAgentFormData {
  name: string;
  description?: string;
  type: 'inbound' | 'outbound' | 'both';
  system_prompt: string;
  first_message?: string;
  goodbye_message?: string;
  knowledge_base?: string;
  knowledge_base_id?: number | null;
  model: string;
  voice: string;
  max_tokens?: number;
  temperature?: number;
  active?: boolean;
  enable_transfer?: boolean;
  transfer_number?: string;
  enable_recording?: boolean;
  max_duration?: number;
  silence_timeout?: number;
  response_timeout?: number;
  trigger_keywords?: string[];
}

export interface AiModel {
  id: string;
  name: string;
  provider: string;
  context_length: number;
  cost_per_1k_tokens: number;
  speed: string;
}

export interface AiVoice {
  id: string;
  name: string;
  gender: string;
  description: string;
}

export interface CallStats {
  total_calls: number;
  completed_calls: number;
  active_calls: number;
  failed_calls: number;
  average_duration: number;
  total_cost: number;
  transferred_calls: number;
}
