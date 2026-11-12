import AppLayout from '@/layouts/app-layout';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { router, Head, useForm, Link } from '@inertiajs/react';
import { Save, Plus, Trash2, ArrowUp, ArrowDown, ArrowLeft } from 'lucide-react';
import { type BreadcrumbItem } from '@/types';
import { useState } from 'react';
import { PageHelp } from '@/components/page-help';
import { useTranslation } from 'react-i18next';

interface Step {
  step_name: string;
  delay_amount: number;
  delay_unit: 'minutes' | 'hours' | 'days';
  action_type: 'call' | 'sms' | 'email' | 'wait' | 'webhook';
  action_config: Record<string, any>;
}

interface Sequence {
  id: number;
  name: string;
  description: string;
  trigger_type: string;
  is_active: boolean;
  use_smart_timing: boolean;
  priority: number;
  steps: Step[];
}

interface Props {
  sequence?: Sequence;
  isEditing?: boolean;
}

export default function SequencesCreate({ sequence, isEditing = false }: Props) {
  const { t } = useTranslation();
  const { data, setData, post, put, processing, errors } = useForm({
    name: sequence?.name || '',
    description: sequence?.description || '',
    trigger_type: (sequence?.trigger_type || 'no_answer') as 'no_answer' | 'interested' | 'callback_requested' | 'not_interested' | 'manual' | 'completed',
    is_active: sequence?.is_active || false,
    use_smart_timing: sequence?.use_smart_timing ?? true,
    priority: sequence?.priority || 5,
    steps: sequence?.steps || [
      {
        step_name: 'First follow-up',
        delay_amount: 24,
        delay_unit: 'hours' as 'minutes' | 'hours' | 'days',
        action_type: 'call' as 'call' | 'sms' | 'email' | 'wait' | 'webhook',
        action_config: {},
      },
    ] as Step[],
  });

  const breadcrumbs: BreadcrumbItem[] = [
    { title: t('common.dashboard'), href: '/dashboard' },
    { title: t('sequences.title'), href: '/sequences' },
    { title: isEditing ? t('common.edit') : t('sequences.create'), href: isEditing ? `/sequences/${sequence?.id}/edit` : '/sequences/create' },
  ];

  const helpSections = [
    {
      title: 'Sequence Builder',
      content: 'Create automated follow-up sequences that trigger based on call outcomes. Define multiple steps with delays and actions to nurture leads systematically.',
    },
    {
      title: 'Trigger Types',
      content: 'Choose when this sequence activates: No Answer (unanswered calls), Interested (positive responses), Callback Requested, Not Interested, Manual (triggered manually), or Completed (after campaign completion).',
    },
    {
      title: 'Smart Timing',
      content: 'Enable smart timing to optimize contact times based on previous successful connections. The system learns the best times to reach each contact for higher answer rates.',
    },
    {
      title: 'Step Configuration',
      content: 'Each step has a delay (minutes, hours, or days) and an action type: Call (automated phone call), SMS (text message), Email, Wait (pause), or Webhook (integrate with external systems).',
    },
    {
      title: 'Step Management',
      content: 'Add multiple steps to create complex workflows. Reorder steps using up/down arrows, remove unnecessary steps, or adjust delays between actions to optimize engagement.',
    },
    {
      title: 'Priority & Activation',
      content: 'Set priority (1-10) to control execution order when multiple sequences match. Activate the sequence immediately or keep it inactive for testing before going live.',
    },
  ];

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (isEditing && sequence) {
      put(`/api/v1/sequences/${sequence.id}`, {
        onSuccess: () => router.visit('/sequences'),
      });
    } else {
      post('/api/v1/sequences', {
        onSuccess: () => router.visit('/sequences'),
      });
    }
  };

  const addStep = () => {
    setData('steps', [
      ...data.steps,
      {
        step_name: `Step ${data.steps.length + 1}`,
        delay_amount: 24,
        delay_unit: 'hours',
        action_type: 'call',
        action_config: {},
      },
    ]);
  };

  const removeStep = (index: number) => {
    setData('steps', data.steps.filter((_, i) => i !== index));
  };

  const moveStep = (index: number, direction: 'up' | 'down') => {
    const newSteps = [...data.steps];
    const targetIndex = direction === 'up' ? index - 1 : index + 1;
    if (targetIndex >= 0 && targetIndex < newSteps.length) {
      [newSteps[index], newSteps[targetIndex]] = [newSteps[targetIndex], newSteps[index]];
      setData('steps', newSteps);
    }
  };

  const updateStep = (index: number, field: keyof Step, value: any) => {
    const newSteps = [...data.steps];
    newSteps[index] = { ...newSteps[index], [field]: value };
    setData('steps', newSteps);
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={isEditing ? t('sequences.edit') : t('sequences.create')} />

      <div className="mx-auto max-w-5xl space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-4">
          <Link href="/sequences">
            <Button variant="outline" size="icon">
              <ArrowLeft className="h-4 w-4" />
            </Button>
          </Link>
          <Heading
            title={isEditing ? t('sequences.edit') : t('sequences.create')}
            description={isEditing ? 'Update your automated follow-up sequence' : 'Set up automated follow-ups based on call outcomes'}
          />
        </div>
        <PageHelp title="Sequence Builder Help" sections={helpSections} />
      </div>        {/* Form */}
        <form onSubmit={handleSubmit} className="space-y-6">
          {/* Basic Information */}
          <Card>
            <CardHeader>
              <CardTitle>Basic Information</CardTitle>
              <CardDescription>
                Configure the sequence name, trigger, and settings
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid gap-4 md:grid-cols-2">
                <div className="space-y-2">
                  <Label htmlFor="name">{t('sequences.name')} *</Label>
                  <Input
                    id="name"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    placeholder="e.g., No Answer Follow-Up"
                    required
                  />
                  {errors.name && <p className="text-sm text-red-500">{errors.name}</p>}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="trigger_type">Trigger Type *</Label>
                  <Select value={data.trigger_type} onValueChange={(value: any) => setData('trigger_type', value)}>
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="no_answer">No Answer</SelectItem>
                      <SelectItem value="interested">Interested</SelectItem>
                      <SelectItem value="callback_requested">Callback Requested</SelectItem>
                      <SelectItem value="not_interested">Not Interested</SelectItem>
                      <SelectItem value="completed">Completed</SelectItem>
                      <SelectItem value="manual">Manual</SelectItem>
                    </SelectContent>
                  </Select>
                  {errors.trigger_type && <p className="text-sm text-red-500">{errors.trigger_type}</p>}
                </div>
              </div>

              <div className="space-y-2">
                <Label htmlFor="description">Description</Label>
                <Textarea
                  id="description"
                  value={data.description}
                  onChange={(e) => setData('description', e.target.value)}
                  placeholder="Describe what this sequence does..."
                  rows={3}
                />
              </div>

              <div className="grid gap-4 md:grid-cols-3">
                <div className="space-y-2">
                  <Label htmlFor="priority">Priority (1-10)</Label>
                  <Input
                    id="priority"
                    type="number"
                    min="1"
                    max="10"
                    value={data.priority}
                    onChange={(e) => setData('priority', parseInt(e.target.value))}
                  />
                  <p className="text-xs text-muted-foreground">Higher priority sequences are checked first</p>
                </div>

                <div className="flex items-center justify-between space-x-2 pt-8">
                  <Label htmlFor="use_smart_timing">AI-Powered Timing</Label>
                  <Switch
                    id="use_smart_timing"
                    checked={data.use_smart_timing}
                    onCheckedChange={(checked) => setData('use_smart_timing', checked)}
                  />
                </div>

                <div className="flex items-center justify-between space-x-2 pt-8">
                  <Label htmlFor="is_active">Activate Immediately</Label>
                  <Switch
                    id="is_active"
                    checked={data.is_active}
                    onCheckedChange={(checked) => setData('is_active', checked)}
                  />
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Steps Builder */}
          <Card>
            <CardHeader>
              <div className="flex items-center justify-between">
                <div>
                  <CardTitle>{t('sequences.steps')}</CardTitle>
                  <CardDescription>
                    Define the steps in your follow-up sequence
                  </CardDescription>
                </div>
                <Button type="button" variant="outline" onClick={addStep}>
                  <Plus className="mr-2 h-4 w-4" />
                  {t('sequences.add_step')}
                </Button>
              </div>
            </CardHeader>
            <CardContent className="space-y-4">
              {data.steps.map((step, index) => (
                <Card key={index} className="border-2">
                  <CardHeader>
                    <div className="flex items-center justify-between">
                      <CardTitle className="text-base">Step {index + 1}</CardTitle>
                      <div className="flex items-center gap-2">
                        <Button
                          type="button"
                          variant="outline"
                          size="sm"
                          onClick={() => moveStep(index, 'up')}
                          disabled={index === 0}
                        >
                          <ArrowUp className="h-4 w-4" />
                        </Button>
                        <Button
                          type="button"
                          variant="outline"
                          size="sm"
                          onClick={() => moveStep(index, 'down')}
                          disabled={index === data.steps.length - 1}
                        >
                          <ArrowDown className="h-4 w-4" />
                        </Button>
                        <Button
                          type="button"
                          variant="outline"
                          size="sm"
                          onClick={() => removeStep(index)}
                          disabled={data.steps.length === 1}
                        >
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      </div>
                    </div>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    <div className="space-y-2">
                      <Label>Step Name</Label>
                      <Input
                        value={step.step_name}
                        onChange={(e) => updateStep(index, 'step_name', e.target.value)}
                        placeholder="e.g., First retry call"
                      />
                    </div>

                    <div className="grid gap-4 md:grid-cols-3">
                      <div className="space-y-2">
                        <Label>Delay Amount</Label>
                        <Input
                          type="number"
                          min="0"
                          value={step.delay_amount}
                          onChange={(e) => updateStep(index, 'delay_amount', parseInt(e.target.value))}
                        />
                      </div>

                      <div className="space-y-2">
                        <Label>Delay Unit</Label>
                        <Select
                          value={step.delay_unit}
                          onValueChange={(value: any) => updateStep(index, 'delay_unit', value)}
                        >
                          <SelectTrigger>
                            <SelectValue />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem value="minutes">Minutes</SelectItem>
                            <SelectItem value="hours">Hours</SelectItem>
                            <SelectItem value="days">Days</SelectItem>
                          </SelectContent>
                        </Select>
                      </div>

                      <div className="space-y-2">
                        <Label>Action Type</Label>
                        <Select
                          value={step.action_type}
                          onValueChange={(value: any) => updateStep(index, 'action_type', value)}
                        >
                          <SelectTrigger>
                            <SelectValue />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem value="call">Phone Call</SelectItem>
                            <SelectItem value="sms">SMS Message</SelectItem>
                            <SelectItem value="email">Email</SelectItem>
                            <SelectItem value="wait">Wait</SelectItem>
                            <SelectItem value="webhook">Webhook</SelectItem>
                          </SelectContent>
                        </Select>
                      </div>
                    </div>

                    {step.action_type === 'sms' && (
                      <div className="space-y-2">
                        <Label>SMS Message</Label>
                        <Textarea
                          placeholder="Enter SMS message..."
                          rows={2}
                        />
                      </div>
                    )}

                    {step.action_type === 'email' && (
                      <div className="space-y-4">
                        <div className="space-y-2">
                          <Label>Email Subject</Label>
                          <Input placeholder="Follow-up from your call" />
                        </div>
                        <div className="space-y-2">
                          <Label>Email Body</Label>
                          <Textarea
                            placeholder="Enter email content..."
                            rows={3}
                          />
                        </div>
                      </div>
                    )}

                    {step.action_type === 'webhook' && (
                      <div className="space-y-2">
                        <Label>Webhook URL</Label>
                        <Input
                          type="url"
                          placeholder="https://example.com/webhook"
                        />
                      </div>
                    )}
                  </CardContent>
                </Card>
              ))}
            </CardContent>
          </Card>

          {/* Actions */}
          <div className="flex items-center justify-between">
            <Button
              type="button"
              variant="outline"
              onClick={() => router.visit('/sequences')}
            >
              {t('common.cancel')}
            </Button>
            <Button type="submit" disabled={processing}>
              <Save className="mr-2 h-4 w-4" />
              {processing ? (isEditing ? 'Updating...' : 'Creating...') : (isEditing ? t('common.update') : t('sequences.create'))}
            </Button>
          </div>
        </form>
      </div>
    </AppLayout>
  );
}
