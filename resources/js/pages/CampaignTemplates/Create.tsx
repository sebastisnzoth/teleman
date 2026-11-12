import AppLayout from '@/layouts/app-layout';
import { useTranslation } from 'react-i18next';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { FormEventHandler } from 'react';

interface Props {
    categories: Record<string, string>;
}

export default function Create({ categories }: Props) {
  const { t } = useTranslation();
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        description: '',
        category: 'promotional',
        template_data: {
            message: '',
            language: 'en-US',
            voice: 'Polly.Joanna',
            enable_recording: false,
            enable_dtmf: false,
            max_concurrent_calls: 5,
            retry_attempts: 3,
            retry_delay_minutes: 30,
        },
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('common.dashboard'), href: dashboard().url },
        { title: t('campaign_templates.campaign_templates'), href: '/campaign-templates' },
        { title: t('campaign_templates.create_template'), href: '/campaign-templates/create' },
    ];

    const handleSubmit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/campaign-templates', {
            onSuccess: () => {
                // Redirect handled by controller
            },
        });
    };

    const updateTemplateData = (key: string, value: unknown) => {
        setData('template_data', {
            ...data.template_data,
            [key]: value,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('campaign_templates.create_campaign_template')} />

            <div className="mx-auto max-w-4xl space-y-6">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Link href="/campaign-templates">
                        <Button variant="outline" size="icon">
                            <ArrowLeft className="h-4 w-4" />
                        </Button>
                    </Link>
                    <Heading
                        title={t('campaign_templates.create_campaign_template')}
                        description={t('campaign_templates.create_a_reusable_template_for_your_campaigns')}
                    />
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Basic Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('campaign_templates.template_information')}</CardTitle>
                            <CardDescription>Basic details about your template</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="name">Template Name</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="e.g., Welcome Call Template"
                                    required
                                />
                                {errors.name && <p className="text-sm text-red-500">{errors.name}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="description">Description</Label>
                                <Textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    placeholder="Describe what this template is for..."
                                    rows={3}
                                />
                                {errors.description && <p className="text-sm text-red-500">{errors.description}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="category">Category</Label>
                                <Select
                                    value={data.category}
                                    onValueChange={(value) => setData('category', value)}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {Object.entries(categories).map(([key, label]) => (
                                            <SelectItem key={key} value={key}>
                                                {label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.category && <p className="text-sm text-red-500">{errors.category}</p>}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Message Script */}
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('campaign_templates.message_script')}</CardTitle>
                            <CardDescription>The message to be spoken in campaigns</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="message">Message</Label>
                                <Textarea
                                    id="message"
                                    value={data.template_data.message}
                                    onChange={(e) => updateTemplateData('message', e.target.value)}
                                    placeholder="Enter the message script..."
                                    rows={6}
                                    required
                                />
                                <p className="text-sm text-slate-500">
                                    This text will be converted to speech during calls
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Voice Settings */}
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('campaign_templates.voice_settings')}</CardTitle>
                            <CardDescription>Configure text-to-speech settings</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="language">Language</Label>
                                    <Select
                                        value={data.template_data.language}
                                        onValueChange={(value) => updateTemplateData('language', value)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="en-US">English (US)</SelectItem>
                                            <SelectItem value="en-GB">English (UK)</SelectItem>
                                            <SelectItem value="es-ES">Spanish</SelectItem>
                                            <SelectItem value="fr-FR">French</SelectItem>
                                            <SelectItem value="de-DE">German</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="voice">Voice</Label>
                                    <Select
                                        value={data.template_data.voice}
                                        onValueChange={(value) => updateTemplateData('voice', value)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="Polly.Joanna">Joanna (Female)</SelectItem>
                                            <SelectItem value="Polly.Matthew">Matthew (Male)</SelectItem>
                                            <SelectItem value="Polly.Ivy">Ivy (Female)</SelectItem>
                                            <SelectItem value="Polly.Joey">Joey (Male)</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Campaign Settings */}
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('campaign_templates.campaign_settings')}</CardTitle>
                            <CardDescription>Default settings for campaigns using this template</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-4 md:grid-cols-3">
                                <div className="space-y-2">
                                    <Label htmlFor="max_concurrent_calls">Max Concurrent Calls</Label>
                                    <Input
                                        id="max_concurrent_calls"
                                        type="number"
                                        min="1"
                                        max="100"
                                        value={data.template_data.max_concurrent_calls}
                                        onChange={(e) =>
                                            updateTemplateData('max_concurrent_calls', parseInt(e.target.value))
                                        }
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="retry_attempts">Retry Attempts</Label>
                                    <Input
                                        id="retry_attempts"
                                        type="number"
                                        min="0"
                                        max="10"
                                        value={data.template_data.retry_attempts}
                                        onChange={(e) =>
                                            updateTemplateData('retry_attempts', parseInt(e.target.value))
                                        }
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="retry_delay_minutes">Retry Delay (minutes)</Label>
                                    <Input
                                        id="retry_delay_minutes"
                                        type="number"
                                        min="1"
                                        max="1440"
                                        value={data.template_data.retry_delay_minutes}
                                        onChange={(e) =>
                                            updateTemplateData('retry_delay_minutes', parseInt(e.target.value))
                                        }
                                    />
                                </div>
                            </div>

                            <div className="space-y-4 pt-4">
                                <div className="flex items-center justify-between rounded-lg border p-4">
                                    <div className="space-y-0.5">
                                        <Label htmlFor="enable_recording">Call Recording</Label>
                                        <p className="text-sm text-slate-500">
                                            Enable recording for all calls in campaigns
                                        </p>
                                    </div>
                                    <Switch
                                        id="enable_recording"
                                        checked={data.template_data.enable_recording}
                                        onCheckedChange={(checked: boolean) => updateTemplateData('enable_recording', checked)}
                                    />
                                </div>

                                <div className="flex items-center justify-between rounded-lg border p-4">
                                    <div className="space-y-0.5">
                                        <Label htmlFor="enable_dtmf">DTMF Input</Label>
                                        <p className="text-sm text-slate-500">
                                            Allow recipients to provide keypad input
                                        </p>
                                    </div>
                                    <Switch
                                        id="enable_dtmf"
                                        checked={data.template_data.enable_dtmf}
                                        onCheckedChange={(checked: boolean) => updateTemplateData('enable_dtmf', checked)}
                                    />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Actions */}
                    <div className="flex justify-end gap-2">
                        <Link href="/campaign-templates">
                            <Button type="button" variant="outline">
                                Cancel
                            </Button>
                        </Link>
                        <Button type="submit" disabled={processing}>
                            <Save className="mr-2 h-4 w-4" />
                            {processing ? 'Creating...' : 'Create Template'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
