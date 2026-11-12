import AppLayout from '@/layouts/app-layout';
import { useTranslation } from 'react-i18next';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Head, Link } from '@inertiajs/react';
import { Plus, FileText } from 'lucide-react';
import { PageHelp } from '@/components/page-help';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import Pagination from '@/components/pagination';

interface CampaignTemplate {
    id: number;
    name: string;
    description: string;
    category: string;
    template_data: Record<string, unknown>;
    is_system_template: boolean;
    usage_count: number;
    created_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginationMeta {
    current_page: number;
    from: number | null;
    last_page: number;
    path: string;
    per_page: number;
    to: number | null;
    total: number;
}

interface Props {
    templates?: {
        data: CampaignTemplate[];
        links?: PaginationLink[];
        meta?: PaginationMeta;
    } | CampaignTemplate[];
}

export default function Index({ templates }: Props) {
  const { t } = useTranslation();
    // Handle both paginated and non-paginated data
    const isPaginated = templates && !Array.isArray(templates) && 'data' in templates;
    const templateArray = isPaginated 
        ? (templates as { data: CampaignTemplate[] }).data 
        : Array.isArray(templates) 
            ? templates 
            : [];
    
    const paginationLinks = isPaginated ? (templates as { data: CampaignTemplate[]; links?: PaginationLink[] }).links : undefined;
    const paginationMeta = isPaginated ? (templates as { data: CampaignTemplate[]; meta?: PaginationMeta }).meta : undefined;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('common.dashboard'), href: dashboard().url },
        { title: t('campaign_templates.campaign_templates'), href: '/campaign-templates' },
    ];

    const helpSections = [
        {
            title: 'Campaign Templates',
            content: 'Templates allow you to save campaign configurations for reuse. Create templates with predefined scripts, settings, and audio files to quickly launch similar campaigns.',
        },
        {
            title: 'Creating Templates',
            content: 'Click "Create Template" to save a campaign configuration. Include the campaign name, type (text-to-speech or voice), script, and default settings.',
        },
        {
            title: 'Using Templates',
            content: 'When creating a new campaign, select a template to pre-fill all settings. You can then customize specific details for the new campaign.',
        },
        {
            title: 'Template Types',
            content: 'Templates can be for Text-to-Speech campaigns (with scripts) or Voice-to-Voice campaigns (with audio files). Choose the type that matches your needs.',
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('campaign_templates.campaign_templates')} />

            <div className="mx-auto max-w-7xl space-y-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title={t('campaign_templates.campaign_templates')}
                        description={t('campaign_templates.manage_your_campaign_templates')}
                    />
                    <div className="flex items-center gap-2">
                        <PageHelp title={t('campaign_templates.campaign_templates_help')} sections={helpSections} />
                        <Button asChild>
                            <Link href="/campaign-templates/create">
                                <Plus className="mr-2 h-4 w-4" />
                                Create Template
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {templateArray.length === 0 ? (
                        <Card className="col-span-full">
                            <CardContent className="flex flex-col items-center justify-center py-12">
                                <FileText className="h-12 w-12 text-muted-foreground mb-4" />
                                <p className="text-lg font-medium">No templates found</p>
                                <p className="text-sm text-muted-foreground mb-4">
                                    Create your first campaign template to get started
                                </p>
                                <Button asChild>
                                    <Link href="/campaign-templates/create">
                                        <Plus className="mr-2 h-4 w-4" />
                                        Create Template
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                    ) : (
                        templateArray.map((template) => (
                            <Card key={template.id} className="hover:shadow-lg transition-shadow">
                                <CardHeader>
                                    <div className="flex items-start justify-between">
                                        <div>
                                            <CardTitle>{template.name}</CardTitle>
                                            <CardDescription className="mt-2">
                                                {template.description}
                                            </CardDescription>
                                        </div>
                                        <Badge variant={template.is_system_template ? 'default' : 'secondary'}>
                                            {template.is_system_template ? 'System' : 'Custom'}
                                        </Badge>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <div className="mb-3">
                                        <p className="text-sm text-muted-foreground capitalize">
                                            Category: {template.category.replace('_', ' ')}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            Used {template.usage_count} times
                                        </p>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <p className="text-sm text-muted-foreground">
                                            Created {new Date(template.created_at).toLocaleDateString()}
                                        </p>
                                        <div className="flex gap-2">
                                            <Button variant="outline" size="sm" asChild>
                                                <Link href={`/campaign-templates/${template.id}`}>
                                                    View
                                                </Link>
                                            </Button>
                                            <Button variant="default" size="sm" asChild>
                                                <Link href={`/campaigns/create?template=${template.id}`}>
                                                    Use
                                                </Link>
                                            </Button>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        ))
                    )}
                </div>

                {/* Pagination */}
                {isPaginated && paginationLinks && paginationMeta && templateArray.length > 0 && (
                    <Pagination links={paginationLinks} meta={paginationMeta} />
                )}
            </div>
        </AppLayout>
    );
}
