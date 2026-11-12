import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { useState, useCallback, useRef } from 'react';
import { Search, Download, Upload, Save, Filter, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Switch } from '@/components/ui/switch';
import { toast } from 'sonner';

interface TranslationItem {
    id: number;
    language_id: number;
    group: string;
    key: string;
    value: string | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Language {
    id: number;
    code: string;
    name: string;
    native_name: string | null;
    flag: string | null;
}

interface Props {
    language: Language;
    translations: {
        data: TranslationItem[];
        links: PaginationLink[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    groups: string[];
    stats: {
        total: number;
        translated: number;
        percentage: number;
    };
    filters: {
        search?: string;
        group?: string;
        untranslated?: string;
        per_page?: string;
    };
}

export default function TranslationsEditor({ language, translations, groups, stats, filters }: Props) {
    const { t } = useTranslation();
    const [modifiedRows, setModifiedRows] = useState<Map<number, string>>(new Map());
    const [search, setSearch] = useState(filters.search || '');
    const [group, setGroup] = useState(filters.group || 'all');
    const [untranslated, setUntranslated] = useState(filters.untranslated === '1');
    const [importOpen, setImportOpen] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: t('admin.languages.title'), href: '/admin/languages' },
        { title: `${language.flag || ''} ${language.name} ${t('admin.languages.translations')}`, href: '#' },
    ];

    const handleValueChange = useCallback((id: number, value: string) => {
        setModifiedRows((prev) => {
            const next = new Map(prev);
            next.set(id, value);
            return next;
        });
    }, []);

    const handleSave = () => {
        if (modifiedRows.size === 0) {
            toast.info(t('admin.languages.no_changes'));
            return;
        }

        const translationsToUpdate = Array.from(modifiedRows.entries()).map(([id, value]) => ({
            id,
            value: value || null,
        }));

        router.put(
            `/admin/languages/${language.id}/translations`,
            { translations: translationsToUpdate },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setModifiedRows(new Map());
                    toast.success(`${translationsToUpdate.length} ${t('admin.languages.translations_updated')}`);
                },
                onError: () => toast.error(t('admin.languages.save_failed')),
            },
        );
    };

    const handleFilter = () => {
        router.get(
            `/admin/languages/${language.id}/translations`,
            {
                search: search || undefined,
                group: group !== 'all' ? group : undefined,
                untranslated: untranslated ? '1' : undefined,
                per_page: filters.per_page,
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const handleClearFilters = () => {
        setSearch('');
        setGroup('all');
        setUntranslated(false);
        router.get(`/admin/languages/${language.id}/translations`, {}, { preserveState: true });
    };

    const handleExport = () => {
        window.location.href = `/admin/languages/${language.id}/translations/export`;
    };

    const handleImport = (e: React.FormEvent) => {
        e.preventDefault();
        const file = fileInputRef.current?.files?.[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('file', file);
        formData.append('group', 'app');

        router.post(`/admin/languages/${language.id}/translations/import`, formData, {
            forceFormData: true,
            onSuccess: () => {
                setImportOpen(false);
                toast.success(t('admin.languages.import_success'));
            },
            onError: () => toast.error(t('admin.languages.import_failed')),
        });
    };

    const handlePageChange = (url: string | null) => {
        if (!url) return;
        router.get(url, {}, { preserveState: true, preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${language.name} ${t('admin.languages.translations')}`} />

            <div className="space-y-6 p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-2xl font-bold tracking-tight">
                            {language.flag} {language.name} {t('admin.languages.translations')}
                        </h2>
                        <p className="text-muted-foreground">
                            {t('admin.languages.manage_strings_for')} {language.native_name || language.name}.
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Dialog open={importOpen} onOpenChange={setImportOpen}>
                            <DialogTrigger asChild>
                                <Button variant="outline" size="sm">
                                    <Upload className="mr-2 h-4 w-4" />
                                    {t('common.import')}
                                </Button>
                            </DialogTrigger>
                            <DialogContent>
                                <form onSubmit={handleImport}>
                                    <DialogHeader>
                                        <DialogTitle>{t('admin.languages.import_translations')}</DialogTitle>
                                        <DialogDescription>
                                            {t('admin.languages.import_description')}
                                        </DialogDescription>
                                    </DialogHeader>
                                    <div className="py-4">
                                        <Label htmlFor="import-file">{t('admin.languages.json_file')}</Label>
                                        <Input id="import-file" type="file" accept=".json" ref={fileInputRef} className="mt-2" />
                                        <p className="text-muted-foreground mt-1 text-xs">
                                            {t('admin.languages.json_format')} {`{"key": "translated value", ...}`}
                                        </p>
                                    </div>
                                    <DialogFooter>
                                        <Button type="button" variant="outline" onClick={() => setImportOpen(false)}>{t('common.cancel')}</Button>
                                        <Button type="submit">{t('common.import')}</Button>
                                    </DialogFooter>
                                </form>
                            </DialogContent>
                        </Dialog>
                        <Button variant="outline" size="sm" onClick={handleExport}>
                            <Download className="mr-2 h-4 w-4" />
                            {t('common.export')}
                        </Button>
                        <Button size="sm" onClick={handleSave} disabled={modifiedRows.size === 0}>
                            <Save className="mr-2 h-4 w-4" />
                            {t('common.save')} ({modifiedRows.size})
                        </Button>
                    </div>
                </div>

                {/* Progress */}
                <Card>
                    <CardContent className="pt-6">
                        <div className="flex items-center gap-4">
                            <div className="flex-1">
                                <div className="mb-1 flex justify-between text-sm">
                                    <span>{t('admin.languages.translation_progress')}</span>
                                    <span className="font-medium">
                                        {stats.translated} / {stats.total} ({stats.percentage}%)
                                    </span>
                                </div>
                                <Progress value={stats.percentage} className="h-3" />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Filters */}
                <Card>
                    <CardContent className="pt-6">
                        <div className="flex flex-wrap items-end gap-3">
                            <div className="min-w-[200px] flex-1">
                                <Label className="mb-1 block text-xs">{t('common.search')}</Label>
                                <div className="relative">
                                    <Search className="text-muted-foreground absolute left-2.5 top-2.5 h-4 w-4" />
                                    <Input
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        onKeyDown={(e) => e.key === 'Enter' && handleFilter()}
                                        placeholder={t('admin.languages.search_placeholder')}
                                        className="pl-9"
                                    />
                                </div>
                            </div>
                            <div className="w-[160px]">
                                <Label className="mb-1 block text-xs">{t('admin.languages.group')}</Label>
                                <Select value={group} onValueChange={setGroup}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">{t('admin.languages.all_groups')}</SelectItem>
                                        {groups.map((g) => (
                                            <SelectItem key={g} value={g}>{g}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="flex items-center gap-2">
                                <Switch
                                    id="untranslated"
                                    checked={untranslated}
                                    onCheckedChange={setUntranslated}
                                />
                                <Label htmlFor="untranslated" className="text-xs whitespace-nowrap">{t('admin.languages.untranslated_only')}</Label>
                            </div>
                            <Button size="sm" onClick={handleFilter}>
                                <Filter className="mr-1 h-4 w-4" />
                                {t('common.filter')}
                            </Button>
                            {(filters.search || filters.group || filters.untranslated) && (
                                <Button size="sm" variant="ghost" onClick={handleClearFilters}>
                                    <X className="mr-1 h-4 w-4" />
                                    {t('common.clear')}
                                </Button>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Translations Table */}
                <Card>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-[80px]">{t('admin.languages.group')}</TableHead>
                                    <TableHead className="w-[35%]">{t('admin.languages.key')}</TableHead>
                                    <TableHead>{t('admin.languages.translation')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {translations.data.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={3} className="text-muted-foreground py-8 text-center">
                                            {t('admin.languages.no_keys_found')}
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    translations.data.map((item) => {
                                        const isModified = modifiedRows.has(item.id);
                                        const currentValue = isModified ? modifiedRows.get(item.id)! : (item.value || '');
                                        const isEmpty = !currentValue;

                                        return (
                                            <TableRow key={item.id} className={isModified ? 'bg-yellow-50 dark:bg-yellow-950/20' : ''}>
                                                <TableCell>
                                                    <Badge variant="outline" className="text-xs">{item.group}</Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <code className="text-muted-foreground text-xs">{item.key}</code>
                                                </TableCell>
                                                <TableCell>
                                                    <Textarea
                                                        value={currentValue}
                                                        onChange={(e) => handleValueChange(item.id, e.target.value)}
                                                        placeholder={t('admin.languages.enter_translation')}
                                                        rows={1}
                                                        className={`min-h-[36px] resize-y text-sm ${isEmpty ? 'border-orange-300 dark:border-orange-700' : ''}`}
                                                    />
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                {/* Pagination */}
                {translations.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <p className="text-muted-foreground text-sm">
                            {t('common.showing')} {(translations.current_page - 1) * translations.per_page + 1} {t('common.to')}{' '}
                            {Math.min(translations.current_page * translations.per_page, translations.total)} {t('common.of')}{' '}
                            {translations.total} {t('common.entries')}
                        </p>
                        <div className="flex items-center gap-1">
                            {translations.links.map((link, i) => {
                                // Safely decode HTML entities in pagination labels (e.g. &laquo; → «)
                                const decodedLabel = link.label
                                    .replace(/&laquo;/g, '\u00AB')
                                    .replace(/&raquo;/g, '\u00BB')
                                    .replace(/&amp;/g, '&');
                                return (
                                    <Button
                                        key={i}
                                        variant={link.active ? 'default' : 'outline'}
                                        size="sm"
                                        disabled={!link.url}
                                        onClick={() => handlePageChange(link.url)}
                                    >
                                        {decodedLabel}
                                    </Button>
                                );
                            })}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
