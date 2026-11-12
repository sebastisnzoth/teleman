import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Globe, Plus, Pencil, Trash2, Languages as LanguagesIcon, Check, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { toast } from 'sonner';

interface Language {
    id: number;
    code: string;
    name: string;
    native_name: string | null;
    flag: string | null;
    is_rtl: boolean;
    is_active: boolean;
    is_default: boolean;
    sort_order: number;
    translations_count: number;
}

interface Props {
    languages: Language[];
    totalKeys: number;
}

export default function LanguagesIndex({ languages, totalKeys }: Props) {
    const { t } = useTranslation();
  const breadcrumbs: BreadcrumbItem[] = [
    { title: t('common.dashboard'), href: '/dashboard' },
    { title: t('admin.languages.title'), href: '/admin/languages' },
];
    const activeCount = languages.filter((l) => l.is_active).length;

    const handleDelete = (language: Language) => {
        router.delete(`/admin/languages/${language.id}`, {
            preserveScroll: true,
            onSuccess: () => toast.success(t('admin.languages.delete') + ' successful.'),
            onError: () => toast.error('Failed to delete language.'),
        });
    };

    const handleToggle = (language: Language) => {
        router.post(`/admin/languages/${language.id}/toggle`, {}, {
            preserveScroll: true,
            onSuccess: () => toast.success(language.is_active ? t('common.disabled') + '.' : t('common.enabled') + '.'),
        });
    };

    const getCompletion = (count: number) => {
        if (totalKeys === 0) return 0;
        return Math.round((count / totalKeys) * 100);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('admin.languages.title')} />

            <div className="space-y-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-2xl font-bold tracking-tight">{t('admin.languages.title')}</h2>
                        <p className="text-muted-foreground">{t('admin.languages.description')}</p>
                    </div>
                    <Link href="/admin/languages/create">
                        <Button>
                            <Plus className="mr-2 h-4 w-4" />
                            {t('admin.languages.add')}
                        </Button>
                    </Link>
                </div>

                {/* Stats */}
                <div className="grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">{t('admin.languages.total')}</CardTitle>
                            <Globe className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{languages.length}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">{t('common.active')}</CardTitle>
                            <Check className="h-4 w-4 text-green-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{activeCount}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">{t('admin.languages.total_keys')}</CardTitle>
                            <LanguagesIcon className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{totalKeys}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">{t('admin.languages.avg_completion')}</CardTitle>
                            <LanguagesIcon className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">
                                {languages.length > 0
                                    ? Math.round(languages.reduce((sum, l) => sum + getCompletion(l.translations_count), 0) / languages.length)
                                    : 0}%
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Table */}
                <Card>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-[60px]">{t('admin.languages.flag')}</TableHead>
                                    <TableHead>{t('admin.languages.code')}</TableHead>
                                    <TableHead>{t('common.name')}</TableHead>
                                    <TableHead>{t('admin.languages.native_name')}</TableHead>
                                    <TableHead className="text-center">{t('admin.languages.rtl')}</TableHead>
                                    <TableHead className="text-center">{t('admin.languages.active')}</TableHead>
                                    <TableHead className="text-center">{t('admin.languages.default_language')}</TableHead>
                                    <TableHead>{t('admin.languages.translation_progress')}</TableHead>
                                    <TableHead className="text-right">{t('common.actions')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {languages.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={9} className="text-muted-foreground py-8 text-center">
                                            {t('admin.languages.no_languages')}
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    languages.map((language) => {
                                        const completion = getCompletion(language.translations_count);
                                        return (
                                            <TableRow key={language.id}>
                                                <TableCell className="text-xl">{language.flag || '🌐'}</TableCell>
                                                <TableCell>
                                                    <Badge variant="outline">{language.code.toUpperCase()}</Badge>
                                                </TableCell>
                                                <TableCell className="font-medium">{language.name}</TableCell>
                                                <TableCell className="text-muted-foreground">{language.native_name || '-'}</TableCell>
                                                <TableCell className="text-center">
                                                    {language.is_rtl ? <Check className="mx-auto h-4 w-4 text-blue-500" /> : <X className="mx-auto h-4 w-4 text-muted-foreground/30" />}
                                                </TableCell>
                                                <TableCell className="text-center">
                                                    <button
                                                        onClick={() => handleToggle(language)}
                                                        className="cursor-pointer"
                                                        disabled={language.is_default && language.is_active}
                                                    >
                                                        {language.is_active ? (
                                                            <Badge variant="default" className="bg-green-500 hover:bg-green-600">{t('common.active')}</Badge>
                                                        ) : (
                                                            <Badge variant="secondary">{t('common.inactive')}</Badge>
                                                        )}
                                                    </button>
                                                </TableCell>
                                                <TableCell className="text-center">
                                                    {language.is_default && <Badge variant="default">{t('common.default')}</Badge>}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-2">
                                                        <Progress value={completion} className="h-2 w-24" />
                                                        <span className="text-muted-foreground text-xs">
                                                            {language.translations_count}/{totalKeys} ({completion}%)
                                                        </span>
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <div className="flex items-center justify-end gap-1">
                                                        <Link href={`/admin/languages/${language.id}/translations`}>
                                                            <Button variant="ghost" size="sm">
                                                                <LanguagesIcon className="h-4 w-4" />
                                                            </Button>
                                                        </Link>
                                                        <Link href={`/admin/languages/${language.id}/edit`}>
                                                            <Button variant="ghost" size="sm">
                                                                <Pencil className="h-4 w-4" />
                                                            </Button>
                                                        </Link>
                                                        {!language.is_default && (
                                                            <AlertDialog>
                                                                <AlertDialogTrigger asChild>
                                                                    <Button variant="ghost" size="sm" className="text-red-500 hover:text-red-700">
                                                                        <Trash2 className="h-4 w-4" />
                                                                    </Button>
                                                                </AlertDialogTrigger>
                                                                <AlertDialogContent>
                                                                    <AlertDialogHeader>
                                                                        <AlertDialogTitle>{t('admin.languages.delete')}</AlertDialogTitle>
                                                                        <AlertDialogDescription>
                                                                            {t('admin.languages.delete_confirm')}
                                                                        </AlertDialogDescription>
                                                                    </AlertDialogHeader>
                                                                    <AlertDialogFooter>
                                                                        <AlertDialogCancel>{t('common.cancel')}</AlertDialogCancel>
                                                                        <AlertDialogAction onClick={() => handleDelete(language)} className="bg-red-500 hover:bg-red-600">
                                                                            {t('common.delete')}
                                                                        </AlertDialogAction>
                                                                    </AlertDialogFooter>
                                                                </AlertDialogContent>
                                                            </AlertDialog>
                                                        )}
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
