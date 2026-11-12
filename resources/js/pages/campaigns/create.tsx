import AppLayout from '@/layouts/app-layout';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Link, useForm, Head } from '@inertiajs/react';
import { ArrowLeft, Save, Sparkles } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import {
  CampaignBasicInfo,
  CampaignVoiceSettings,
  CampaignSettings,
  AudioFileSelector,
  PhoneNumberSelector, AiAgentSelector,
  type SelectableAiAgent,
} from '@/components/campaigns/campaign-form-sections';
import { DtmfSettings } from '@/components/campaigns/dtmf-settings';
import VariableManager from '@/components/campaigns/variable-manager';
import EnhancedMessageInput from '@/components/campaigns/enhanced-message-input';
import CampaignScheduling from '@/components/campaigns/campaign-scheduling';
import { FormEventHandler, useEffect, useState } from 'react';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox';
import { Badge } from '@/components/ui/badge';

interface CampaignTemplate {
  id: number;
  name: string;
  description: string;
  category: string;
  template_data: {
    type: 'text_to_speech' | 'voice_to_voice';
    message?: string;
    language?: string;
    voice?: string;
    enable_recording?: boolean;
    enable_dtmf?: boolean;
    dtmf_actions?: Array<{ key: string; label: string; action?: string }>;
  };
  is_system_template: boolean;
  usage_count: number;
}

interface Agent {
  id: number;
  name: string;
  email: string;
}

interface ContactList {
  id: number;
  name: string;
  description?: string;
  contacts_count: number;
}

interface PhoneNumber {
  id: number;
  number: string;
  formatted_number?: string;
  friendly_name?: string;
  ai_agent?: {
    id: number;
    name: string;
    type: string;
    is_active: boolean;
  };
}

interface Props {
  audioFiles: Array<{ id: number; filename: string }>;
  templates: CampaignTemplate[];
  selectedTemplate?: CampaignTemplate;
  contactLists: ContactList[];
  phoneNumbers: PhoneNumber[];
  agents: Agent[];
  aiAgents: SelectableAiAgent[];
}

export default function CampaignCreate({ audioFiles, templates, selectedTemplate, contactLists, phoneNumbers, agents, aiAgents }: Props) {
  const { t } = useTranslation();
  const [selectedTemplateId, setSelectedTemplateId] = useState<number | null>(selectedTemplate?.id || null);
  const [selectedContactListIds, setSelectedContactListIds] = useState<number[]>([]);

  const { data, setData, post, errors, processing } = useForm({
    name: '',
    type: 'text_to_speech' as 'text_to_speech' | 'voice_to_voice',
    message: '',
    phone_number_id: null as number | null,
    audio_file_id: null as number | null,
    language: 'en-US',
    voice: 'Joanna',
    enable_recording: false,
    recording_mode: 'full' as string,
    recording_max_length: 300,
    enable_dtmf: false,
    dtmf_num_digits: 1,
    dtmf_timeout: 5,
    dtmf_prompt: '',
    dtmf_actions: [] as Array<{ key: string; label: string; action: string; value?: string }>,
    answer_delay_seconds: 0,
    enable_amd: false,
    amd_action: 'hangup' as string,
    max_concurrent_calls: 5,
    retry_attempts: 2,
    retry_delay_minutes: 5,
    contact_list_ids: [] as number[],
    assigned_agent_id: null as number | null,
    ai_agent_id: null as number | null,
    expected_variables: [] as string[], // Contact-level variables
    campaign_variables: {} as Record<string, string>, // Campaign-level variables with values
    launch_type: 'draft' as 'instant' | 'scheduled' | 'draft',
    scheduled_at: '',
  });

  const applyTemplate = (template: CampaignTemplate) => {
    const templateData = template.template_data;
    setData({
      ...data,
      name: template.name,
      // Every other field here falls back; this one did not, so a template
      // saved without a type left both radios unselected and the campaign
      // could not be saved until the author noticed why.
      type: templateData.type || data.type,
      message: templateData.message || '',
      language: templateData.language || 'en-US',
      voice: templateData.voice || 'Joanna',
      enable_recording: templateData.enable_recording || false,
      enable_dtmf: templateData.enable_dtmf || false,
    });
  };

  // Apply template when selected on initial load
  useEffect(() => {
    if (selectedTemplate) {
      applyTemplate(selectedTemplate);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [selectedTemplate]);

  const handleTemplateChange = (templateId: string) => {
    const id = parseInt(templateId);
    setSelectedTemplateId(id);
    const template = templates.find(t => t.id === id);
    if (template) {
      applyTemplate(template);
    }
  };

  const handleContactListToggle = (listId: number) => {
    setSelectedContactListIds(prev => {
      const newIds = prev.includes(listId)
        ? prev.filter(id => id !== listId)
        : [...prev, listId];
      setData('contact_list_ids', newIds);
      return newIds;
    });
  };

  const numberAgent =
    phoneNumbers.find((phone) => phone.id === data.phone_number_id)?.ai_agent ?? null;

  const handleChange = (field: string, value: unknown) => {
    setData(field as keyof typeof data, value as never);

    // Picking a number fills in the agent that answers on it — the same
    // agent the campaign used to be given silently. It stays overridable.
    if (field === 'phone_number_id') {
      const picked = phoneNumbers.find((phone) => phone.id === value);
      setData('ai_agent_id', (picked?.ai_agent?.id ?? null) as never);
    }
  };

  const handleVariablesChange = (contactVars: string[], campaignVars: Record<string, string>) => {
    setData('expected_variables', contactVars);
    setData('campaign_variables', campaignVars);
  };

  const handleSubmit: FormEventHandler = (e) => {
    e.preventDefault();
    post('/campaigns');
  };

  const breadcrumbs: BreadcrumbItem[] = [
    {
      title: t('common.dashboard'),
      href: dashboard().url,
    },
    {
      title: t('campaigns.title'),
      href: '/campaigns',
    },
    {
      title: t('campaigns.create'),
      href: '/campaigns/create',
    },
  ];

  const getCategoryBadgeColor = (category: string) => {
    const colors: Record<string, string> = {
      promotional: 'bg-purple-100 text-purple-800',
      lead_qualification: 'bg-blue-100 text-blue-800',
      survey: 'bg-green-100 text-green-800',
      notification: 'bg-yellow-100 text-yellow-800',
      personalized: 'bg-pink-100 text-pink-800',
    };
    return colors[category] || 'bg-gray-100 text-gray-800';
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={t('campaigns.create')} />
      <div className="mx-auto max-w-4xl">
        {/* Header */}
        <div className="flex items-center gap-4">
          <Link href="/campaigns">
            <Button variant="outline" size="icon">
              <ArrowLeft className="h-4 w-4" />
            </Button>
          </Link>
          <Heading title={t('campaigns.create')} description="Set up a new voice calling campaign" />
        </div>

        {/* Form */}
        <form onSubmit={handleSubmit} className="space-y-6">
          {/* Template Selector */}
          {templates.length > 0 && (
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Sparkles className="h-5 w-5 text-purple-600" />
                  Start from a Template
                </CardTitle>
                <CardDescription>
                  Choose a pre-built template to quickly set up your campaign
                </CardDescription>
              </CardHeader>
              <CardContent>
                <div className="space-y-3">
                  <Label>Select Template</Label>
                  <Select value={selectedTemplateId?.toString() || ''} onValueChange={handleTemplateChange}>
                    <SelectTrigger>
                      <SelectValue placeholder="Choose a template or start from scratch" />
                    </SelectTrigger>
                    <SelectContent>
                      {templates.map(template => (
                        <SelectItem key={template.id} value={template.id.toString()}>
                          <div className="flex items-center gap-2">
                            <span>{template.name}</span>
                            <Badge variant="outline" className={getCategoryBadgeColor(template.category)}>
                              {template.category.replace('_', ' ')}
                            </Badge>
                            {template.is_system_template && (
                              <Badge variant="secondary" className="text-xs">System</Badge>
                            )}
                          </div>
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  {selectedTemplateId && templates.find(t => t.id === selectedTemplateId) && (
                    <p className="text-sm text-muted-foreground">
                      {templates.find(t => t.id === selectedTemplateId)?.description}
                    </p>
                  )}
                </div>
              </CardContent>
            </Card>
          )}

          {/* Contact Lists Selector */}
          {contactLists.length > 0 && (
            <Card>
              <CardHeader>
                <CardTitle>Select Contact Lists</CardTitle>
                <CardDescription>
                  Choose one or more contact lists to add to this campaign
                </CardDescription>
              </CardHeader>
              <CardContent>
                <div className="space-y-3">
                  {contactLists.map(list => (
                    <div key={list.id} className="flex items-center space-x-3 rounded-lg border p-3 hover:bg-accent">
                      <Checkbox
                        id={`list-${list.id}`}
                        checked={selectedContactListIds.includes(list.id)}
                        onCheckedChange={() => handleContactListToggle(list.id)}
                      />
                      <Label htmlFor={`list-${list.id}`} className="flex-1 cursor-pointer">
                        <div className="flex items-center justify-between">
                          <div>
                            <p className="font-medium">{list.name}</p>
                            {list.description && (
                              <p className="text-sm text-muted-foreground">{list.description}</p>
                            )}
                          </div>
                          <Badge variant="secondary">{list.contacts_count} contacts</Badge>
                        </div>
                      </Label>
                    </div>
                  ))}
                  {selectedContactListIds.length > 0 && (
                    <p className="text-sm text-muted-foreground">
                      {selectedContactListIds.reduce((sum, id) => {
                        const list = contactLists.find(l => l.id === id);
                        return sum + (list?.contacts_count || 0);
                      }, 0)} total contacts selected
                    </p>
                  )}
                </div>
              </CardContent>
            </Card>
          )}
          {/* Assign to Agent */}
          {agents.length > 0 && (
            <Card>
              <CardHeader>
                <CardTitle>Assign to Agent</CardTitle>
                <CardDescription>
                  Optionally assign this campaign to a specific agent
                </CardDescription>
              </CardHeader>
              <CardContent>
                <div className="space-y-3">
                  <Label>Select Agent</Label>
                  <Select
                    value={data.assigned_agent_id?.toString() ?? ''}
                    onValueChange={(val) =>
                      setData('assigned_agent_id', val ? parseInt(val) : null)
                    }
                  >
                    <SelectTrigger>
                      <SelectValue placeholder="Unassigned" />
                    </SelectTrigger>
                    <SelectContent>
                      {agents.map((agent) => (
                        <SelectItem key={agent.id} value={agent.id.toString()}>
                          <span>{agent.name}</span>
                          <span className="ml-2 text-xs text-muted-foreground">{agent.email}</span>
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  {data.assigned_agent_id && (
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      onClick={() => setData('assigned_agent_id', null)}
                    >
                      Clear assignment
                    </Button>
                  )}
                </div>
              </CardContent>
            </Card>
          )}

          {/* Basic Info */}
          <CampaignBasicInfo
            data={{ name: data.name, type: data.type }}
            errors={{ name: errors.name, type: errors.type }}
            onChange={handleChange}
          />

          {/* Phone Number Selector */}
          <PhoneNumberSelector
            data={{ phone_number_id: data.phone_number_id }}
            phoneNumbers={phoneNumbers}
            errors={{ phone_number_id: errors.phone_number_id }}
            onChange={handleChange}
          />

          {/* AI Agent */}
          <AiAgentSelector
            data={{ ai_agent_id: data.ai_agent_id }}
            aiAgents={aiAgents}
            numberAgent={numberAgent}
            errors={{ ai_agent_id: errors.ai_agent_id }}
            onChange={handleChange}
          />

          {/* Message (only for TTS) */}
          {data.type === 'text_to_speech' && (
            <>
              {/* Variables Manager - Must come first */}
              <VariableManager
                message={data.message}
                onVariablesChange={handleVariablesChange}
                initialContactVars={data.expected_variables}
                initialCampaignVars={data.campaign_variables}
                error={errors.expected_variables || errors.campaign_variables}
              />

              {/* Enhanced Message Input */}
              <EnhancedMessageInput
                value={data.message}
                onChange={(value) => setData('message', value)}
                contactVariables={data.expected_variables}
                campaignVariables={data.campaign_variables}
                error={errors.message}
              />

              {/* AI Variants Info */}
              {data.message && (
                <Card className="border-blue-200 bg-blue-50/50 dark:bg-blue-950/20 dark:border-blue-900">
                  <CardContent className="pt-6">
                    <div className="flex items-start gap-3">
                      <div className="rounded-full bg-blue-100 dark:bg-blue-900 p-2">
                        <Sparkles className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                      </div>
                      <div className="flex-1">
                        <h3 className="font-semibold text-sm mb-1">AI Message Variants Available</h3>
                        <p className="text-sm text-muted-foreground mb-3">
                          After creating this campaign, you can generate 5 AI-powered message variants with different tones (Professional, Friendly, Urgent, Educational, Direct) for A/B testing. 
                        </p>
                        <p className="text-xs text-muted-foreground">
                          💡 Edit your campaign after creation to access the AI variant generator and track performance metrics.
                        </p>
                      </div>
                    </div>
                  </CardContent>
                </Card>
              )}

              <CampaignVoiceSettings
                data={{ language: data.language, voice: data.voice }}
                errors={{ language: errors.language, voice: errors.voice }}
                onChange={handleChange}
              />
            </>
          )}

          {/* Audio File (only for Voice-to-Voice) */}
          {data.type === 'voice_to_voice' && (
            <AudioFileSelector
              data={{ audio_file_id: data.audio_file_id }}
              errors={{ audio_file_id: errors.audio_file_id }}
              audioFiles={audioFiles}
              onChange={handleChange}
            />
          )}

          {/* Settings */}
          <CampaignSettings
            data={{
              enable_recording: data.enable_recording,
              enable_dtmf: data.enable_dtmf,
              max_concurrent_calls: data.max_concurrent_calls,
              retry_attempts: data.retry_attempts,
              retry_delay_minutes: data.retry_delay_minutes,
            }}
            errors={{
              enable_recording: errors.enable_recording,
              enable_dtmf: errors.enable_dtmf,
              max_concurrent_calls: errors.max_concurrent_calls,
              retry_attempts: errors.retry_attempts,
              retry_delay_minutes: errors.retry_delay_minutes,
            }}
            onChange={handleChange}
          />

          {/* Enhanced DTMF Settings */}
          <DtmfSettings
            data={{
              enable_dtmf: data.enable_dtmf,
              dtmf_num_digits: data.dtmf_num_digits,
              dtmf_timeout: data.dtmf_timeout,
              dtmf_prompt: data.dtmf_prompt,
              dtmf_actions: data.dtmf_actions,
            }}
            errors={{
              enable_dtmf: errors.enable_dtmf,
              dtmf_num_digits: errors.dtmf_num_digits,
              dtmf_timeout: errors.dtmf_timeout,
              dtmf_prompt: errors.dtmf_prompt,
              dtmf_actions: errors.dtmf_actions,
            }}
            onChange={handleChange}
          />

          {/* Campaign Scheduling */}
          <CampaignScheduling
            launchType={data.launch_type}
            scheduledAt={data.scheduled_at}
            onLaunchTypeChange={(type) => setData('launch_type', type)}
            onScheduledAtChange={(datetime) => setData('scheduled_at', datetime)}
            error={errors.scheduled_at}
          />

          {/* Actions */}
          <div className="flex justify-end gap-3">
            <Link href="/campaigns">
              <Button type="button" variant="outline" disabled={processing}>
                {t('common.cancel')}
              </Button>
            </Link>
            <Button type="submit" disabled={processing}>
              <Save className="mr-2 h-4 w-4" />
              {processing
                ? 'Creating...'
                : data.launch_type === 'instant'
                  ? 'Create & Launch'
                  : data.launch_type === 'scheduled'
                    ? 'Create & Schedule'
                    : 'Save as Draft'
              }
            </Button>
          </div>
        </form>
      </div>
    </AppLayout>
  );
}
