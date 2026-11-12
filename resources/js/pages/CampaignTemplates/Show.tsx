import AppLayout from '@/layouts/app-layout';
import { useTranslation } from 'react-i18next';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Edit, Trash2, FileText } from 'lucide-react';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';

interface CampaignTemplate {
    id: number;
    name: string;
    description: string;
    category: string;
    template_data: {
        message?: string;
        language?: string;
        voice?: string;
        enable_recording?: boolean;
        enable_dtmf?: boolean;
        max_concurrent_calls?: number;
        retry_attempts?: number;
        retry_delay_minutes?: number;
        dtmf_actions?: Record<string, unknown>;
    };
    is_system_template: boolean;
    usage_count: number;
    created_at: string;
    updated_at: string;
}

interface Props {
    template: CampaignTemplate;
}

export default function Show({ template }: Props) {
  const { t } = useTranslation();
    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('common.dashboard'), href: dashboard().url },
        { title: t('campaign_templates.campaign_templates'), href: '/campaign-templates' },
        { title: template.name, href: `/campaign-templates/${template.id}` },
    ];

    const handleDelete = () => {
        if (confirm('Are you sure you want to delete this template?')) {
            router.delete(`/campaign-templates/${template.id}`, {
                onSuccess: () => {
                    router.visit('/campaign-templates');
                },
            });
        }
    };

    const handleUseTemplate = () => {
        router.visit(`/campaigns/create?template=${template.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={template.name} />

            <div className="mx-auto max-w-4xl space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Link href="/campaign-templates">
                            <Button variant="outline" size="icon">
                                <ArrowLeft className="h-4 w-4" />
                            </Button>
                        </Link>
                        <div>
                            <Heading title={template.name} description={template.description} />
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Badge variant={template.is_system_template ? 'default' : 'secondary'}>
                            {template.is_system_template ? 'System Template' : 'Custom Template'}
                        </Badge>
                    </div>
                </div>

                {/* Actions */}
                <div className="flex gap-2">
                    <Button onClick={handleUseTemplate}>
                        <FileText className="mr-2 h-4 w-4" />
                        Use This Template
                    </Button>
                    {!template.is_system_template && (
                        <>
                            <Link href={`/campaign-templates/${template.id}/edit`}>
                                <Button variant="outline">
                                    <Edit className="mr-2 h-4 w-4" />
                                    Edit
                                </Button>
                            </Link>
                            <Button variant="destructive" onClick={handleDelete}>
                                <Trash2 className="mr-2 h-4 w-4" />
                                Delete
                            </Button>
                        </>
                    )}
                </div>

                {/* Template Details */}
                <div className="grid gap-6 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('campaign_templates.template_information')}</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <label className="text-sm font-medium text-slate-600">Category</label>
                                <p className="mt-1 capitalize">{template.category.replace('_', ' ')}</p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-slate-600">Usage Count</label>
                                <p className="mt-1">{template.usage_count} times</p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-slate-600">Created</label>
                                <p className="mt-1">{new Date(template.created_at).toLocaleString()}</p>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-slate-600">Last Updated</label>
                                <p className="mt-1">{new Date(template.updated_at).toLocaleString()}</p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{t('campaign_templates.campaign_settings')}</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {template.template_data.language && (
                                <div>
                                    <label className="text-sm font-medium text-slate-600">Language</label>
                                    <p className="mt-1">{template.template_data.language}</p>
                                </div>
                            )}
                            {template.template_data.voice && (
                                <div>
                                    <label className="text-sm font-medium text-slate-600">Voice</label>
                                    <p className="mt-1">{template.template_data.voice}</p>
                                </div>
                            )}
                            {template.template_data.max_concurrent_calls !== undefined && (
                                <div>
                                    <label className="text-sm font-medium text-slate-600">Max Concurrent Calls</label>
                                    <p className="mt-1">{template.template_data.max_concurrent_calls}</p>
                                </div>
                            )}
                            {template.template_data.retry_attempts !== undefined && (
                                <div>
                                    <label className="text-sm font-medium text-slate-600">Retry Attempts</label>
                                    <p className="mt-1">{template.template_data.retry_attempts}</p>
                                </div>
                            )}
                            {template.template_data.retry_delay_minutes !== undefined && (
                                <div>
                                    <label className="text-sm font-medium text-slate-600">Retry Delay</label>
                                    <p className="mt-1">{template.template_data.retry_delay_minutes} minutes</p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Message/Script */}
                {template.template_data.message && (
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('campaign_templates.message_script')}</CardTitle>
                            <CardDescription>The message that will be used in campaigns</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="rounded-md bg-slate-50 p-4">
                                <p className="whitespace-pre-wrap text-sm">{template.template_data.message}</p>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Features */}
                <Card>
                    <CardHeader>
                        <CardTitle>{t('campaign_templates.features')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="flex items-center justify-between rounded-lg border p-4">
                                <span className="text-sm font-medium">Call Recording</span>
                                <Badge variant={template.template_data.enable_recording ? 'default' : 'secondary'}>
                                    {template.template_data.enable_recording ? 'Enabled' : 'Disabled'}
                                </Badge>
                            </div>
                            <div className="flex items-center justify-between rounded-lg border p-4">
                                <span className="text-sm font-medium">DTMF Input</span>
                                <Badge variant={template.template_data.enable_dtmf ? 'default' : 'secondary'}>
                                    {template.template_data.enable_dtmf ? 'Enabled' : 'Disabled'}
                                </Badge>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
