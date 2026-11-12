import { useState, useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
import { ArrowLeft, Sparkles } from 'lucide-react';
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
import type { AiAgentFormData, AiModel, AiVoice } from '@/types/ai-agent';
import { useTranslation } from 'react-i18next';
import { useFormatCurrency } from '@/hooks/use-currency';

export default function CreateAiAgent() {
  const { t } = useTranslation();
  const { formatCurrency } = useFormatCurrency();
  const [loading, setLoading] = useState(false);
  const [models, setModels] = useState<AiModel[]>([]);
  const [voices, setVoices] = useState<AiVoice[]>([]);
  const breadcrumbs: BreadcrumbItem[] = [
    {
      title: t('nav.home'),
      href: dashboard().url,
    },
    {
      title: t('nav.conversational_ai'),
      href: '/ai-agents',
    },
    {
      title: t('ai_agents.create_agent'),
      href: '/ai-agents/create',
    },
  ];

  const [formData, setFormData] = useState<AiAgentFormData>({
    name: '',
    description: '',
    type: 'inbound',
    system_prompt: '',
    first_message: '',
    goodbye_message: '',
    knowledge_base: '',
    knowledge_base_id: null as number | null,
    model: 'openrouter/polaris-alpha',
    voice: 'joanna',
    max_tokens: 80,
    temperature: 0.7,
    active: true,
    enable_transfer: false,
    transfer_number: '',
    enable_recording: true,
    max_duration: 600,
    silence_timeout: 10,
    response_timeout: 10,
    trigger_keywords: [],
  });

  const [keywordInput, setKeywordInput] = useState('');
  const [knowledgeBases, setKnowledgeBases] = useState<{ id: number; name: string }[]>([]);

  useEffect(() => {
    loadModelsAndVoices();
    loadKnowledgeBases();
  }, []);

  const loadKnowledgeBases = async () => {
    try {
      const res = await axios.get('/api/v1/knowledge-bases', { params: { per_page: 100 } });
      setKnowledgeBases(res.data.data ?? []);
    } catch {
      // ignore
    }
  };

  const loadModelsAndVoices = async () => {
    try {
      const [modelsRes, voicesRes] = await Promise.all([
        axios.get('/api/v1/ai-agents/models'),
        axios.get('/api/v1/ai-agents/voices'),
      ]);
      const modelsArray = Object.values(modelsRes.data) as any[];
      const voicesArray = Object.values(voicesRes.data) as any[];
      setModels(modelsArray);
      setVoices(voicesArray);

      if (modelsArray.length > 0) {
        setFormData(prev => ({ ...prev, model: modelsArray[0].id }));
      }
    } catch (error) {
      console.error('Failed to load models/voices:', error);
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);

    try {
      await axios.post('/api/v1/ai-agents', formData);
      toast.success(t('ai_agents.agent_created'));
      router.visit('/ai-agents');
    } catch (error: unknown) {
      console.error('Failed to create AI agent:', error);
      if (axios.isAxiosError(error) && error.response?.data) {
        const data = error.response.data;
        const message = data.message || (data.errors ? Object.values(data.errors).flat().join(', ') : t('ai_agents.create_failed'));
        toast.error(message);
      } else {
        toast.error(t('ai_agents.create_failed'));
      }
    } finally {
      setLoading(false);
    }
  };

  const addKeyword = () => {
    if (keywordInput.trim() && !formData.trigger_keywords?.includes(keywordInput.trim())) {
      setFormData({
        ...formData,
        trigger_keywords: [...(formData.trigger_keywords || []), keywordInput.trim()],
      });
      setKeywordInput('');
    }
  };

  const removeKeyword = (keyword: string) => {
    setFormData({
      ...formData,
      trigger_keywords: formData.trigger_keywords?.filter(k => k !== keyword) || [],
    });
  };

  const defaultPrompts = {
    inbound_support: "You are a helpful customer support agent. Listen carefully to customer inquiries and provide clear, accurate assistance. Be empathetic and professional.",
    inbound_sales: "You are a sales agent. Engage with potential customers, understand their needs, and present relevant solutions. Be persuasive but not pushy.",
    outbound_survey: "You are conducting a brief survey. Be polite and respectful of people's time. Ask questions clearly and record responses accurately.",
    outbound_lead: "You are a lead qualification agent. Your goal is to assess if the contact is a good fit for our services. Ask qualifying questions professionally.",
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={t('ai_agents.create_agent')} />
      <div className="mx-auto max-w-7xl space-y-6">
        <div className="flex items-center space-x-4">
          <Button variant="ghost" size="icon" onClick={() => router.visit('/ai-agents')}>
            <ArrowLeft className="h-5 w-5" />
          </Button>
          <Heading
            title={t('ai_agents.create_agent')}
            description={t('ai_agents.create_description')}
          />
        </div>

      <form onSubmit={handleSubmit}>
        <div className="grid gap-6">
          {/* Basic Information */}
          <Card>
            <CardHeader className="pb-3">
              <CardTitle className="text-base">{t('ai_agents.basic_information')}</CardTitle>
              <CardDescription className="text-sm">{t('ai_agents.basic_information_description')}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="space-y-2">
                <Label htmlFor="name">{t('ai_agents.agent_name')} *</Label>
                <Input
                  id="name"
                  value={formData.name}
                  onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                  placeholder={t('ai_agents.agent_name_placeholder')}
                  required
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="description">{t('common.description')}</Label>
                <Textarea
                  id="description"
                  value={formData.description}
                  onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                  placeholder={t('ai_agents.description_placeholder')}
                  rows={3}
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="type">{t('ai_agents.agent_type')} *</Label>
                <Select
                  value={formData.type}
                  onValueChange={(value: 'inbound' | 'outbound' | 'both') => setFormData({ ...formData, type: value })}
                >
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="inbound">{t('ai_agents.inbound_answer_calls')}</SelectItem>
                    <SelectItem value="outbound">{t('ai_agents.outbound_make_calls')}</SelectItem>
                    <SelectItem value="both">{t('ai_agents.both_inbound_outbound')}</SelectItem>
                  </SelectContent>
                </Select>
                <p className="text-sm text-muted-foreground">
                  {t('ai_agents.agent_type_hint')}
                </p>
              </div>
            </CardContent>
          </Card>

          {/* AI Configuration */}
          <Card>
            <CardHeader>
              <CardTitle>{t('ai_agents.text_generation_config')}</CardTitle>
              <CardDescription>{t('ai_agents.text_generation_config_description')}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="space-y-2">
                <Label htmlFor="model">{t('ai_agents.ai_model')} *</Label>
                <Select
                  value={formData.model}
                  onValueChange={(value) => setFormData({ ...formData, model: value })}
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
                            {model.cost_per_1k_tokens === 0 ? t('common.free') : `${formatCurrency(model.cost_per_1k_tokens)}/1K`}
                          </Badge>
                        </div>
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <div className="space-y-2">
                <Label htmlFor="voice">{t('common.voice')} *</Label>
                <Select
                  value={formData.voice}
                  onValueChange={(value) => setFormData({ ...formData, voice: value })}
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
                  <Label htmlFor="max_tokens">{t('ai_agents.max_tokens')}</Label>
                  <Input
                    id="max_tokens"
                    type="number"
                    value={formData.max_tokens}
                    onChange={(e) => setFormData({ ...formData, max_tokens: parseInt(e.target.value) })}
                    min={50}
                    max={4000}
                  />
                </div>

                <div className="space-y-2">
                  <Label htmlFor="temperature">{t('ai_agents.temperature')}</Label>
                  <Input
                    id="temperature"
                    type="number"
                    step="0.1"
                    value={formData.temperature}
                    onChange={(e) => setFormData({ ...formData, temperature: parseFloat(e.target.value) })}
                    min={0}
                    max={2}
                  />
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Call Behavior */}
          <Card>
            <CardHeader>
              <CardTitle>{t('ai_agents.call_behavior')}</CardTitle>
              <CardDescription>{t('ai_agents.call_behavior_description')}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="max_duration">{t('ai_agents.max_call_duration')}</Label>
                  <Input
                    id="max_duration"
                    type="number"
                    value={formData.max_duration}
                    onChange={(e) => setFormData({ ...formData, max_duration: parseInt(e.target.value) })}
                    min={60}
                    max={3600}
                  />
                </div>
              </div>

              <div className="space-y-2">
                <div className="flex items-center justify-between">
                  <Label htmlFor="system_prompt">{t('ai_agents.system_prompt')} *</Label>
                  <Select onValueChange={(value) => setFormData({ ...formData, system_prompt: defaultPrompts[value as keyof typeof defaultPrompts] })}>
                    <SelectTrigger className="w-[200px]">
                      <SelectValue placeholder={t('ai_agents.use_template')} />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="inbound_support">{t('ai_agents.support_template')}</SelectItem>
                      <SelectItem value="inbound_sales">{t('ai_agents.sales_template')}</SelectItem>
                      <SelectItem value="outbound_survey">{t('ai_agents.survey_template')}</SelectItem>
                      <SelectItem value="outbound_lead">{t('ai_agents.lead_qual_template')}</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <Textarea
                  id="system_prompt"
                  value={formData.system_prompt}
                  onChange={(e) => setFormData({ ...formData, system_prompt: e.target.value })}
                  placeholder={t('ai_agents.system_prompt_placeholder')}
                  rows={6}
                  required
                />
                <p className="text-sm text-muted-foreground">
                  {t('ai_agents.system_prompt_hint')}
                </p>
              </div>

              <div className="space-y-2">
                <Label htmlFor="first_message">{t('ai_agents.first_message')}</Label>
                <Textarea
                  id="first_message"
                  value={formData.first_message}
                  onChange={(e) => setFormData({ ...formData, first_message: e.target.value })}
                  placeholder={t('ai_agents.first_message_placeholder')}
                  maxLength={500}
                  rows={2}
                />
                <p className="text-sm text-muted-foreground">{t('ai_agents.first_message_hint')}</p>
              </div>

              <div className="space-y-2">
                <Label htmlFor="goodbye_message">{t('ai_agents.goodbye_message')}</Label>
                <Textarea
                  id="goodbye_message"
                  value={formData.goodbye_message}
                  onChange={(e) => setFormData({ ...formData, goodbye_message: e.target.value })}
                  placeholder={t('ai_agents.goodbye_message_placeholder')}
                  maxLength={500}
                  rows={2}
                />
                <p className="text-sm text-muted-foreground">{t('ai_agents.goodbye_message_hint')}</p>
              </div>

              <div className="space-y-2">
                <Label>{t('nav.knowledge_base')}</Label>
                <Select
                  value={formData.knowledge_base_id != null ? String(formData.knowledge_base_id) : 'none'}
                  onValueChange={(v) => setFormData({ ...formData, knowledge_base_id: v === 'none' ? null : Number(v), knowledge_base: v === 'none' ? formData.knowledge_base : '' })}
                >
                  <SelectTrigger>
                    <SelectValue placeholder={t('ai_agents.select_or_use_custom')} />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="none">{t('ai_agents.none_use_custom')}</SelectItem>
                    {knowledgeBases.map((kb) => (
                      <SelectItem key={kb.id} value={String(kb.id)}>{kb.name}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <p className="text-sm text-muted-foreground">{t('ai_agents.knowledge_base_hint')}</p>
              </div>
              {formData.knowledge_base_id == null && (
                <div className="space-y-2">
                  <Label htmlFor="knowledge_base">{t('ai_agents.custom_knowledge_base')}</Label>
                  <Textarea
                    id="knowledge_base"
                    value={formData.knowledge_base}
                    onChange={(e) => setFormData({ ...formData, knowledge_base: e.target.value })}
                    placeholder={t('ai_agents.custom_knowledge_base_placeholder')}
                    rows={8}
                  />
                  <p className="text-sm text-muted-foreground">{t('ai_agents.custom_knowledge_base_hint')}</p>
                </div>
              )}
            </CardContent>
          </Card>

          {/* Call Settings */}
          <Card>
            <CardHeader>
              <CardTitle>{t('ai_agents.call_settings')}</CardTitle>
              <CardDescription>{t('ai_agents.call_settings_description')}</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="flex items-center justify-between">
                <div className="space-y-0.5">
                  <Label>{t('common.active')}</Label>
                  <p className="text-sm text-muted-foreground">{t('ai_agents.active_hint')}</p>
                </div>
                <Switch
                  checked={formData.active}
                  onCheckedChange={(checked) => setFormData({ ...formData, active: checked })}
                />
              </div>

              <div className="flex items-center justify-between">
                <div className="space-y-0.5">
                  <Label>{t('ai_agents.enable_recording')}</Label>
                  <p className="text-sm text-muted-foreground">{t('ai_agents.enable_recording_hint')}</p>
                </div>
                <Switch
                  checked={formData.enable_recording}
                  onCheckedChange={(checked) => setFormData({ ...formData, enable_recording: checked })}
                />
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="silence_timeout">{t('ai_agents.silence_timeout')}</Label>
                  <Input
                    id="silence_timeout"
                    type="number"
                    value={formData.silence_timeout}
                    onChange={(e) => setFormData({ ...formData, silence_timeout: parseInt(e.target.value) })}
                    min={5}
                    max={60}
                  />
                </div>

                <div className="space-y-2">
                  <Label htmlFor="response_timeout">{t('ai_agents.response_timeout')}</Label>
                  <Input
                    id="response_timeout"
                    type="number"
                    value={formData.response_timeout}
                    onChange={(e) => setFormData({ ...formData, response_timeout: parseInt(e.target.value) })}
                    min={5}
                    max={30}
                  />
                  <p className="text-sm text-muted-foreground">{t('ai_agents.response_timeout_hint')}</p>
                </div>
              </div>

              <div className="flex items-center justify-between">
                <div className="space-y-0.5">
                  <Label>{t('ai_agents.enable_transfer')}</Label>
                  <p className="text-sm text-muted-foreground">{t('ai_agents.enable_transfer_hint')}</p>
                </div>
                <Switch
                  checked={formData.enable_transfer}
                  onCheckedChange={(checked) => setFormData({ ...formData, enable_transfer: checked })}
                />
              </div>

              {formData.enable_transfer && (
                <>
                  <div className="space-y-2">
                    <Label htmlFor="transfer_number">{t('ai_agents.transfer_number')} *</Label>
                    <Input
                      id="transfer_number"
                      value={formData.transfer_number}
                      onChange={(e) => setFormData({ ...formData, transfer_number: e.target.value })}
                      placeholder="+1234567890"
                      required={formData.enable_transfer}
                    />
                  </div>

                  <div className="space-y-2">
                    <Label>{t('ai_agents.transfer_keywords')}</Label>
                    <p className="text-sm text-muted-foreground">{t('ai_agents.transfer_keywords_hint')}</p>
                    <div className="flex space-x-2">
                      <Input
                        value={keywordInput}
                        onChange={(e) => setKeywordInput(e.target.value)}
                        onKeyPress={(e) => e.key === 'Enter' && (e.preventDefault(), addKeyword())}
                        placeholder={t('ai_agents.transfer_keywords_placeholder')}
                      />
                      <Button type="button" onClick={addKeyword}>{t('common.add')}</Button>
                    </div>
                    <div className="flex flex-wrap gap-2 mt-2">
                      {formData.trigger_keywords?.map((keyword) => (
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
            <Button type="button" variant="outline" onClick={() => router.visit('/ai-agents')} disabled={loading}>
              {t('common.cancel')}
            </Button>
            <Button type="submit" disabled={loading}>
              {loading ? (
                <>{t('ai_agents.creating')}</>
              ) : (
                <>
                  <Sparkles className="mr-2 h-4 w-4" />
                  {t('ai_agents.create_agent')}
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
