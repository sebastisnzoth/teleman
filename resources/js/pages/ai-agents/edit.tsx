import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { Head, router } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import Heading from '@/components/heading';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Badge } from '@/components/ui/badge';
import axios from 'axios';
import { toast } from 'sonner';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import type { AiAgent, AiModel, AiVoice } from '@/types/ai-agent';
import { useFormatCurrency } from '@/hooks/use-currency';

interface Props {
  id: number;
}

export default function EditAiAgent({ id }: Props) {
  const { formatCurrency } = useFormatCurrency();
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [models, setModels] = useState<AiModel[]>([]);
  const [voices, setVoices] = useState<AiVoice[]>([]);
  const [agent, setAgent] = useState<AiAgent | null>(null);
  const [keywordInput, setKeywordInput] = useState('');
  const [availableNumbers, setAvailableNumbers] = useState<any[]>([]);
  const [knowledgeBases, setKnowledgeBases] = useState<{ id: number; name: string }[]>([]);

  const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Conversational AI', href: '/ai-agents' },
    { title: agent?.name || 'Edit Agent', href: `/ai-agents/${id}` },
  ];

  useEffect(() => {
    loadAgent();
    loadVoices();
    loadAvailableNumbers();
    loadKnowledgeBases();
  }, [id]);

  const loadKnowledgeBases = async () => {
    try {
      const res = await axios.get('/api/v1/knowledge-bases', { params: { per_page: 100 } });
      setKnowledgeBases(res.data.data ?? []);
    } catch {
      // ignore
    }
  };

  useEffect(() => {
    if (agent) {
      loadModels();
    }
  }, [agent]);

  const loadAgent = async () => {
    try {
      const response = await axios.get(`/api/v1/ai-agents/${id}`);
      setAgent(response.data.data);
    } catch (error) {
      console.error('Failed to load agent:', error);
      toast.error('Failed to load agent');
      router.visit('/ai-agents');
    } finally {
      setLoading(false);
    }
  };

  const loadModels = async () => {
    try {
      const response = await axios.get('/api/v1/ai-agents/models');
      setModels(Object.values(response.data));
    } catch (error) {
      console.error('Failed to load models:', error);
    }
  };

  const loadVoices = async () => {
    try {
      const response = await axios.get('/api/v1/ai-agents/voices');
      setVoices(Object.values(response.data));
    } catch (error) {
      console.error('Failed to load voices:', error);
    }
  };

  const loadAvailableNumbers = async () => {
    try {
      console.log('Loading available phone numbers...');
      const response = await axios.get('/numbers/api/all-numbers');
      console.log('Phone numbers response:', response.data);
      setAvailableNumbers(response.data.numbers || []);
      console.log('Available numbers set:', response.data.numbers?.length || 0);
    } catch (error) {
      console.error('Failed to load phone numbers:', error);
      if (error.response) {
        console.error('Error response:', error.response.status, error.response.data);
      }
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!agent) return;

    setSaving(true);
    try {
      const dataToSend: any = {
        ...agent,
        phone_number: agent.phone_number === 'none' ? null : agent.phone_number,
      };

      await axios.put(`/api/v1/ai-agents/${id}`, dataToSend);
      toast.success('AI Agent updated successfully');
      router.visit(`/ai-agents/${id}`);
    } catch (error: any) {
      console.error('Failed to update AI agent:', error);
      console.error('Error response:', error.response?.data);
      
      // Handle validation errors
      if (error.response?.data?.errors) {
        console.error('Validation errors:', error.response.data.errors);
        const errors = error.response.data.errors;
        
        // Show validation errors
        Object.entries(errors).forEach(([field, messages]: [string, any]) => {
          console.error(`  ${field}:`, messages);
          const errorMessages = Array.isArray(messages) ? messages : [messages];
          errorMessages.forEach((msg: string) => {
            const fieldName = field.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
            toast.error(`${fieldName}: ${msg}`);
          });
        });
      } else {
        // Show general error
        const errorMessage = error.response?.data?.message || 'Failed to update AI agent';
        toast.error(errorMessage);
      }
    } finally {
      setSaving(false);
    }
  };

  const addKeyword = () => {
    if (!agent) return;
    if (keywordInput.trim() && !agent.transfer_keywords?.includes(keywordInput.trim())) {
      setAgent({
        ...agent,
        transfer_keywords: [...(agent.transfer_keywords || []), keywordInput.trim()],
      });
      setKeywordInput('');
    }
  };

  const removeKeyword = (keyword: string) => {
    if (!agent) return;
    setAgent({
      ...agent,
      transfer_keywords: agent.transfer_keywords?.filter(k => k !== keyword) || [],
    });
  };

  if (loading) {
    return (
      <AppLayout breadcrumbs={breadcrumbs}>
        <Head title="Loading..." />
        <div className="flex items-center justify-center h-96">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-gray-900 dark:border-gray-100"></div>
        </div>
      </AppLayout>
    );
  }

  if (!agent) return null;

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={`Edit ${agent.name}`} />
      <div className="mx-auto max-w-7xl space-y-6">
        <div className="flex items-center space-x-4">
          <Button variant="ghost" size="icon" onClick={() => router.visit(`/ai-agents/${id}`)}>
            <ArrowLeft className="h-5 w-5" />
          </Button>
          <Heading 
            title={`Edit ${agent.name}`}
            description="Update AI agent configuration and settings"
          />
        </div>

        <form onSubmit={handleSubmit}>
          <div className="grid gap-6">
            {/* Basic Information */}
            <Card>
              <CardHeader>
                <CardTitle>Basic Information</CardTitle>
                <CardDescription>General details about your AI agent</CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="space-y-2">
                  <Label htmlFor="name">Agent Name *</Label>
                  <Input
                    id="name"
                    value={agent.name}
                    onChange={(e) => setAgent({ ...agent, name: e.target.value })}
                    placeholder="Support Agent"
                    required
                  />
                </div>

                <div className="space-y-2">
                  <Label htmlFor="description">Description</Label>
                  <Textarea
                    id="description"
                    value={agent.description || ''}
                    onChange={(e) => setAgent({ ...agent, description: e.target.value })}
                    placeholder="Describe what this agent does..."
                    rows={3}
                  />
                </div>

                <div className="space-y-2">
                  <Label htmlFor="type">Agent Type *</Label>
                  <Select
                    value={agent.type}
                    onValueChange={(value: 'inbound' | 'outbound' | 'both') => setAgent({ ...agent, type: value })}
                  >
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="inbound">Inbound (Answer Calls)</SelectItem>
                      <SelectItem value="outbound">Outbound (Make Calls)</SelectItem>
                      <SelectItem value="both">Both (Inbound & Outbound)</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </CardContent>
            </Card>

            {/* Phone Number Assignment */}
            <Card>
              <CardHeader>
                <CardTitle>Phone Number Assignment</CardTitle>
                <CardDescription>Assign a dedicated phone number to this AI agent</CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="space-y-2">
                  <Label htmlFor="phone_number">Phone Number (Optional)</Label>
                  <Select
                    value={agent.phone_number || 'none'}
                    onValueChange={(value) => setAgent({ ...agent, phone_number: value === 'none' ? null : value })}
                  >
                    <SelectTrigger>
                      <SelectValue placeholder="Select a phone number" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="none">No Phone Number</SelectItem>
                      {availableNumbers.length === 0 && (
                        <SelectItem value="loading" disabled>Loading numbers...</SelectItem>
                      )}
                      {availableNumbers.map((number) => (
                        <SelectItem key={number.id} value={number.number}>
                          {number.formatted_number || number.number} 
                          {number.friendly_name && ` - ${number.friendly_name}`}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <p className="text-sm text-muted-foreground">
                    Assign a phone number for inbound calls. This number will be dedicated to this AI agent.
                  </p>
                </div>
              </CardContent>
            </Card>

            {/* AI Configuration */}
            <Card>
              <CardHeader>
                <CardTitle>AI Configuration</CardTitle>
                <CardDescription>Model and voice settings</CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="space-y-2">
                  <Label htmlFor="model">AI Model *</Label>
                  <Select
                    value={agent.model}
                    onValueChange={(value) => setAgent({ ...agent, model: value })}
                  >
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      {models.map((model) => (
                        <SelectItem key={model.id} value={model.id}>
                          <div className="flex items-center justify-between w-full">
                            <span>{model.name}</span>
                            <Badge variant="secondary" className="ml-2">
                              {model.cost_per_1k_tokens === 0 ? 'FREE' : `${formatCurrency(model.cost_per_1k_tokens)}/1K`}
                            </Badge>
                          </div>
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>

                <div className="space-y-2">
                  <Label htmlFor="voice">Voice *</Label>
                  <Select
                    value={agent.voice}
                    onValueChange={(value) => setAgent({ ...agent, voice: value })}
                  >
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      {voices.map((voice: any) => (
                        <SelectItem key={voice.id} value={voice.id}>
                          {voice.name} ({voice.gender}) - {voice.description}
                          {voice.engine && <span className="text-muted-foreground ml-1">[{voice.engine}]</span>}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>

                <div className="grid grid-cols-2 gap-4">
                  <div className="space-y-2">
                    <Label htmlFor="max_tokens">Max Tokens</Label>
                    <Input
                      id="max_tokens"
                      type="number"
                      value={agent.max_tokens || 80}
                      onChange={(e) => setAgent({ ...agent, max_tokens: parseInt(e.target.value) })}
                      min={50}
                      max={4000}
                    />
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="temperature">Temperature</Label>
                    <Input
                      id="temperature"
                      type="number"
                      step="0.1"
                      value={agent.temperature || 0.7}
                      onChange={(e) => setAgent({ ...agent, temperature: parseFloat(e.target.value) })}
                      min={0}
                      max={2}
                    />
                  </div>
                </div>

                <div className="space-y-2">
                  <Label htmlFor="system_prompt">System Prompt *</Label>
                  <Textarea
                    id="system_prompt"
                    value={agent.system_prompt}
                    onChange={(e) => setAgent({ ...agent, system_prompt: e.target.value })}
                    placeholder="You are a helpful assistant..."
                    rows={6}
                    required
                  />
                  <p className="text-sm text-muted-foreground">
                    ℹ️ Voice AI base instructions (concise responses, natural pauses, safety rules) are automatically applied. Add your specific role and task instructions here.
                  </p>
                </div>

                <div className="space-y-2">
                  <Label htmlFor="first_message">First Message (Optional)</Label>
                  <Textarea
                    id="first_message"
                    value={agent.first_message || ''}
                    onChange={(e) => setAgent({ ...agent, first_message: e.target.value })}
                    placeholder="Hi! Thanks for calling. How can I help you today?"
                    maxLength={500}
                    rows={2}
                  />
                  <p className="text-sm text-muted-foreground">Greeting played when call connects</p>
                </div>

                <div className="space-y-2">
                  <Label htmlFor="goodbye_message">Goodbye Message (Optional)</Label>
                  <Textarea
                    id="goodbye_message"
                    value={agent.goodbye_message || ''}
                    onChange={(e) => setAgent({ ...agent, goodbye_message: e.target.value })}
                    placeholder="Thank you for your time. Have a great day!"
                    maxLength={500}
                    rows={2}
                  />
                  <p className="text-sm text-muted-foreground">Message played before call ends</p>
                </div>

                <div className="space-y-2">
                  <Label>Knowledge Base</Label>
                  <Select
                    value={agent.knowledge_base_id != null ? String(agent.knowledge_base_id) : 'none'}
                    onValueChange={(v) => setAgent({
                      ...agent,
                      knowledge_base_id: v === 'none' ? null : Number(v),
                      knowledge_base: v === 'none' ? agent.knowledge_base ?? '' : '',
                    })}
                  >
                    <SelectTrigger>
                      <SelectValue placeholder="Select or use custom below" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="none">None (use custom text below)</SelectItem>
                      {knowledgeBases.map((kb) => (
                        <SelectItem key={kb.id} value={String(kb.id)}>{kb.name}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <p className="text-sm text-muted-foreground">Select a saved knowledge base or enter custom text below</p>
                </div>
                {agent.knowledge_base_id == null && (
                  <div className="space-y-2">
                    <Label htmlFor="knowledge_base">Custom Knowledge Base</Label>
                    <Textarea
                      id="knowledge_base"
                      value={agent.knowledge_base || ''}
                      onChange={(e) => setAgent({ ...agent, knowledge_base: e.target.value })}
                      placeholder="Product details, FAQs, company information, policies..."
                      rows={8}
                    />
                    <p className="text-sm text-muted-foreground">Context and information for the AI to reference during conversations</p>
                  </div>
                )}
              </CardContent>
            </Card>

            {/* Call Settings */}
            <Card>
              <CardHeader>
                <CardTitle>Call Settings</CardTitle>
                <CardDescription>Configure call behavior and limits</CardDescription>
              </CardHeader>
              <CardContent className="space-y-4">
                <div className="flex items-center justify-between">
                  <div className="space-y-0.5">
                    <Label>Active</Label>
                    <p className="text-sm text-muted-foreground">Enable this agent to handle calls</p>
                  </div>
                  <Switch
                    checked={agent.active}
                    onCheckedChange={(checked) => setAgent({ ...agent, active: checked })}
                  />
                </div>

                <div className="flex items-center justify-between">
                  <div className="space-y-0.5">
                    <Label>Enable Recording</Label>
                    <p className="text-sm text-muted-foreground">Record all calls for quality assurance</p>
                  </div>
                  <Switch
                    checked={agent.enable_recording}
                    onCheckedChange={(checked) => setAgent({ ...agent, enable_recording: checked })}
                  />
                </div>

                <div className="grid grid-cols-2 gap-4">
                  <div className="space-y-2">
                    <Label htmlFor="max_duration">Max Duration (seconds)</Label>
                    <Input
                      id="max_duration"
                      type="number"
                      value={agent.max_duration || 600}
                      onChange={(e) => setAgent({ ...agent, max_duration: parseInt(e.target.value) })}
                      min={60}
                      max={3600}
                    />
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="silence_timeout">Silence Timeout (seconds)</Label>
                    <Input
                      id="silence_timeout"
                      type="number"
                      value={agent.silence_timeout || 10}
                      onChange={(e) => setAgent({ ...agent, silence_timeout: parseInt(e.target.value) })}
                      min={5}
                      max={60}
                    />
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="response_timeout">Response Timeout (seconds)</Label>
                    <Input
                      id="response_timeout"
                      type="number"
                      value={agent.response_timeout || 10}
                      onChange={(e) => setAgent({ ...agent, response_timeout: parseInt(e.target.value) })}
                      min={5}
                      max={30}
                    />
                    <p className="text-sm text-muted-foreground">How long to wait for AI response before timing out</p>
                  </div>
                </div>

                <div className="flex items-center justify-between">
                  <div className="space-y-0.5">
                    <Label>Enable Transfer to Human</Label>
                    <p className="text-sm text-muted-foreground">Allow agent to transfer calls</p>
                  </div>
                  <Switch
                    checked={agent.enable_transfer}
                    onCheckedChange={(checked) => setAgent({ ...agent, enable_transfer: checked })}
                  />
                </div>

                {agent.enable_transfer && (
                  <>
                    <div className="space-y-2">
                      <Label htmlFor="transfer_number">Transfer Number *</Label>
                      <Input
                        id="transfer_number"
                        value={agent.transfer_number || ''}
                        onChange={(e) => setAgent({ ...agent, transfer_number: e.target.value })}
                        placeholder="+1234567890"
                        required={agent.enable_transfer}
                      />
                    </div>

                    <div className="space-y-2">
                      <Label>Transfer Keywords</Label>
                      <p className="text-sm text-muted-foreground">Keywords that trigger call transfer. Examples: agent, human, representative, speak to someone</p>
                      <div className="flex space-x-2">
                        <Input
                          value={keywordInput}
                          onChange={(e) => setKeywordInput(e.target.value)}
                          onKeyPress={(e) => e.key === 'Enter' && (e.preventDefault(), addKeyword())}
                          placeholder="e.g., agent, human, representative..."
                        />
                        <Button type="button" onClick={addKeyword}>Add</Button>
                      </div>
                      <div className="flex flex-wrap gap-2 mt-2">
                        {agent.transfer_keywords?.map((keyword) => (
                          <Badge key={keyword} variant="secondary" className="cursor-pointer" onClick={() => removeKeyword(keyword)}>
                            {keyword} ×
                          </Badge>
                        ))}
                      </div>
                    </div>
                  </>
                )}
              </CardContent>
            </Card>

            <div className="flex justify-end space-x-4">
              <Button type="button" variant="outline" onClick={() => router.visit(`/ai-agents/${id}`)} disabled={saving}>
                Cancel
              </Button>
              <Button type="submit" disabled={saving}>
                {saving ? (
                  <>Saving...</>
                ) : (
                  <>
                    <Save className="mr-2 h-4 w-4" />
                    Save Changes
                  </>
                )}
              </Button>
            </div>
          </div>
        </form>
      </div>
    </AppLayout>
  );
}
