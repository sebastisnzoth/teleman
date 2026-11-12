import AppLayout from '@/layouts/app-layout';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Head, Link, router } from '@inertiajs/react';
import { Plus, Users, Eye, Trash2 } from 'lucide-react';
import { PageHelp } from '@/components/page-help';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { useTranslation } from 'react-i18next';

interface ContactList {
    id: number;
    name: string;
    description: string;
    contacts_count: number;
    created_at: string;
}

interface Props {
    lists: ContactList[];
}

export default function Index({ lists = [] }: Props) {
    const { t } = useTranslation();
    const listArray = Array.isArray(lists) ? lists : [];

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Dashboard',
            href: dashboard().url,
        },
        {
            title: t('contact_lists.title'),
            href: '/contact-lists',
        },
    ];

    const helpSections = [
        {
            title: t('contact_lists.overview_help'),
            content: t('contact_lists.overview_help_description'),
        },
        {
            title: t('contact_lists.creating_help'),
            content: t('contact_lists.creating_help_description'),
        },
        {
            title: t('contact_lists.adding_contacts_help'),
            content: t('contact_lists.adding_contacts_help_description'),
        },
        {
            title: t('contact_lists.campaigns_help'),
            content: t('contact_lists.campaigns_help_description'),
        },
        {
            title: t('contact_lists.management_help'),
            content: t('contact_lists.management_help_description'),
        },
    ];

    const handleDelete = (id: number) => {
        if (confirm(t('contact_lists.delete_confirm'))) {
            router.delete(`/contact-lists/${id}`);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('contact_lists.title')} />

            <div className="mx-auto max-w-7xl space-y-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title={t('contact_lists.title')}
                        description={t('contact_lists.description')}
                    />
                    <div className="flex items-center gap-2">
                        <PageHelp title="Contact Lists Help" sections={helpSections} />
                        <Button asChild>
                            <Link href="/contact-lists/create">
                                <Plus className="mr-2 h-4 w-4" />
                                {t('contact_lists.add')}
                            </Link>
                        </Button>
                    </div>
                </div>

                <Card className="p-6">
                    {listArray.length === 0 ? (
                        <div className="flex flex-col items-center justify-center py-12">
                            <Users className="h-12 w-12 text-muted-foreground mb-4" />
                            <p className="text-lg font-medium">{t('contact_lists.no_lists')}</p>
                            <p className="text-sm text-muted-foreground mb-4">
                                {t('contact_lists.no_lists_description')}
                            </p>
                            <Button asChild>
                                <Link href="/contact-lists/create">
                                    <Plus className="mr-2 h-4 w-4" />
                                    {t('contact_lists.add')}
                                </Link>
                            </Button>
                        </div>
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{t('common.name')}</TableHead>
                                    <TableHead>{t('common.description')}</TableHead>
                                    <TableHead className="text-center">{t('contact_lists.contact_count')}</TableHead>
                                    <TableHead>{t('common.date')}</TableHead>
                                    <TableHead className="text-right">{t('common.actions')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {listArray.map((list) => (
                                    <TableRow key={list.id}>
                                        <TableCell className="font-medium">{list.name}</TableCell>
                                        <TableCell className="text-muted-foreground max-w-md truncate">
                                            {list.description || '-'}
                                        </TableCell>
                                        <TableCell className="text-center">
                                            <Badge variant="secondary">
                                                {Number(list.contacts_count || 0)}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {new Date(list.created_at).toLocaleDateString()}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex items-center justify-end gap-2">
                                                <Button variant="outline" size="sm" asChild>
                                                    <Link href={`/contact-lists/${list.id}`}>
                                                        <Eye className="mr-2 h-4 w-4" />
                                                        {t('contact_lists.view')}
                                                    </Link>
                                                </Button>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => handleDelete(list.id)}
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
                </Card>
            </div>
        </AppLayout>
    );
}
