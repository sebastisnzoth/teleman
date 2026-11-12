import AppLayout from '@/layouts/app-layout';
import Heading from '@/components/heading';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { FileText, Pencil, ExternalLink } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';

interface Page {
    id: number;
    slug: string;
    title: string;
    is_active: boolean;
    updated_at: string;
    updated_by?: {
        id: number;
        name: string;
    };
}

interface Props {
    pages: Page[];
}

export default function PagesIndex({ pages }: Props) {
    const { t } = useTranslation();

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('common.dashboard'), href: '/dashboard' },
        { title: t('admin.pages.title'), href: '/admin/pages' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('admin.pages.title')} />

            <div className="mx-auto max-w-7xl space-y-6">
                <Heading
                    title={t('admin.pages.title')}
                    description={t('admin.pages.description')}
                />

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <FileText className="h-5 w-5" />
                            {t('admin.pages.all_pages')}
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('admin.pages.page_title')}</TableHead>
                                    <TableHead>{t('admin.pages.slug')}</TableHead>
                                    <TableHead>{t('admin.pages.status')}</TableHead>
                                    <TableHead>{t('admin.pages.last_updated')}</TableHead>
                                    <TableHead className="text-right">{t('common.actions')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {pages.map((page) => (
                                    <TableRow key={page.id}>
                                        <TableCell className="font-medium">{page.title}</TableCell>
                                        <TableCell>
                                            <code className="rounded bg-muted px-1.5 py-0.5 text-xs">
                                                /page/{page.slug}
                                            </code>
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant={page.is_active ? 'default' : 'secondary'}>
                                                {page.is_active ? t('admin.pages.active') : t('admin.pages.inactive')}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-sm text-muted-foreground">
                                            {new Date(page.updated_at).toLocaleDateString()}
                                            {page.updated_by && (
                                                <span className="ml-1">
                                                    {t('common.by')} {page.updated_by.name}
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex items-center justify-end gap-2">
                                                <Button variant="outline" size="sm" asChild>
                                                    <a
                                                        href={`/page/${page.slug}`}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                    >
                                                        <ExternalLink className="mr-1 h-3.5 w-3.5" />
                                                        {t('admin.pages.preview')}
                                                    </a>
                                                </Button>
                                                <Button size="sm" asChild>
                                                    <Link href={`/admin/pages/${page.slug}`}>
                                                        <Pencil className="mr-1 h-3.5 w-3.5" />
                                                        {t('common.edit')}
                                                    </Link>
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
