import AppLayout from '@/layouts/app-layout';
import { useTranslation } from 'react-i18next';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Tag, Trash2 } from 'lucide-react';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { PageHelp } from '@/components/page-help';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';

interface ContactTag {
    id: number;
    name: string;
    color: string;
    contacts_count: number;
    created_at: string;
}

interface Props {
    tags: ContactTag[];
}

export default function Index({ tags = [] }: Props) {
  const { t } = useTranslation();
    const tagArray = Array.isArray(tags) ? tags : [];

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('common.dashboard'), href: dashboard().url },
        { title: t('contact_tags.contact_tags'), href: '/contact-tags' },
    ];

    const helpSections = [
        {
            title: 'Contact Tags',
            content: 'Tags help you organize and categorize contacts. Use tags to segment contacts by interest, location, industry, or any custom criteria.',
        },
        {
            title: 'Creating Tags',
            content: 'Click "Create Tag" to add a new tag. Choose a descriptive name and color to easily identify the tag when filtering contacts.',
        },
        {
            title: 'Applying Tags',
            content: 'Apply tags to contacts when creating or editing them. A contact can have multiple tags for flexible organization.',
        },
        {
            title: 'Using Tags in Campaigns',
            content: 'Filter contact lists by tags when creating campaigns to target specific segments of your audience.',
        },
        {
            title: 'Tag Colors',
            content: 'Assign unique colors to tags for visual distinction. This makes it easier to identify tagged contacts at a glance.',
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('contact_tags.contact_tags')} />

            <div className="mx-auto max-w-7xl space-y-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title={t('contact_tags.contact_tags')}
                        description={t('contact_tags.organize_your_contacts_with_tags')}
                    />
                    <div className="flex items-center gap-2">
                        <PageHelp title={t('contact_tags.contact_tags_help')} sections={helpSections} />
                        <Button asChild>
                            <Link href="/contact-tags/create">
                                <Plus className="mr-2 h-4 w-4" />
                                Create Tag
                            </Link>
                        </Button>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('contact_tags.all_tags')}</CardTitle>
                        <CardDescription>
                            Manage tags to categorize and filter your contacts
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {tagArray.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-12">
                                <Tag className="h-12 w-12 text-muted-foreground mb-4" />
                                <p className="text-lg font-medium">No tags found</p>
                                <p className="text-sm text-muted-foreground mb-4">
                                    Create your first tag to organize your contacts
                                </p>
                                <Button asChild>
                                    <Link href="/contact-tags/create">
                                        <Plus className="mr-2 h-4 w-4" />
                                        Create Tag
                                    </Link>
                                </Button>
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Tag</TableHead>
                                        <TableHead>Contacts</TableHead>
                                        <TableHead>Created</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {tagArray.map((tag) => (
                                        <TableRow key={tag.id}>
                                            <TableCell>
                                                <Badge 
                                                    style={{ 
                                                        backgroundColor: tag.color,
                                                        color: '#ffffff'
                                                    }}
                                                >
                                                    <Tag className="mr-1 h-3 w-3" />
                                                    {tag.name}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                {Number(tag.contacts_count || 0)} contacts
                                            </TableCell>
                                            <TableCell>
                                                {new Date(tag.created_at).toLocaleDateString()}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex items-center justify-end gap-2">
                                                    <Button variant="outline" size="sm" asChild>
                                                        <Link href={`/contact-tags/${tag.id}/edit`}>
                                                            Edit
                                                        </Link>
                                                    </Button>
                                                    <Button 
                                                        variant="destructive" 
                                                        size="sm"
                                                        onClick={() => {
                                                            if (confirm(t('contact_tags.confirm_delete'))) {
                                                                router.delete(`/contact-tags/${tag.id}`);
                                                            }
                                                        }}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
