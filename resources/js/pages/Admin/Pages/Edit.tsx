import AppLayout from '@/layouts/app-layout';
import Heading from '@/components/heading';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { useState } from 'react';
import { Save, ExternalLink, Eye, EyeOff } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import { RichTextEditor } from '@/components/rich-text-editor';
import { toast } from 'sonner';

interface Page {
    id: number;
    slug: string;
    title: string;
    content: string | null;
    meta_title: string | null;
    meta_description: string | null;
    is_active: boolean;
}

interface Props {
    page: Page;
}

export default function PageEdit({ page }: Props) {
    const { t } = useTranslation();
    const [processing, setProcessing] = useState(false);
    const [form, setForm] = useState({
        title: page.title || '',
        content: page.content || '',
        meta_title: page.meta_title || '',
        meta_description: page.meta_description || '',
        is_active: page.is_active,
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('common.dashboard'), href: '/dashboard' },
        { title: t('admin.pages.title'), href: '/admin/pages' },
        { title: page.title, href: `/admin/pages/${page.slug}` },
    ];

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setProcessing(true);

        router.put(`/admin/pages/${page.slug}`, form, {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(t('admin.pages.updated_successfully'));
                setProcessing(false);
            },
            onError: () => {
                toast.error(t('common.error_occurred'));
                setProcessing(false);
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${t('admin.pages.edit_page')} - ${page.title}`} />

            <div className="mx-auto max-w-5xl space-y-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title={`${t('admin.pages.edit_page')}: ${page.title}`}
                        description={t('admin.pages.edit_description')}
                    />
                    <Button variant="outline" asChild>
                        <a
                            href={`/page/${page.slug}`}
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <ExternalLink className="mr-2 h-4 w-4" />
                            {t('admin.pages.preview')}
                        </a>
                    </Button>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('admin.pages.page_content')}</CardTitle>
                            <CardDescription>{t('admin.pages.content_description')}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="title">{t('admin.pages.page_title')}</Label>
                                <Input
                                    id="title"
                                    value={form.title}
                                    onChange={(e) => setForm({ ...form, title: e.target.value })}
                                    required
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label>{t('admin.pages.content_label')}</Label>
                                <RichTextEditor
                                    content={form.content}
                                    onChange={(html) => setForm({ ...form, content: html })}
                                    placeholder={t('admin.pages.content_placeholder')}
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{t('admin.pages.seo_settings')}</CardTitle>
                            <CardDescription>{t('admin.pages.seo_description')}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="meta_title">{t('admin.pages.meta_title')}</Label>
                                <Input
                                    id="meta_title"
                                    value={form.meta_title}
                                    onChange={(e) => setForm({ ...form, meta_title: e.target.value })}
                                    placeholder={t('admin.pages.meta_title_placeholder')}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="meta_description">{t('admin.pages.meta_description')}</Label>
                                <Textarea
                                    id="meta_description"
                                    value={form.meta_description}
                                    onChange={(e) => setForm({ ...form, meta_description: e.target.value })}
                                    placeholder={t('admin.pages.meta_description_placeholder')}
                                    className="min-h-20"
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{t('admin.pages.visibility')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    {form.is_active ? (
                                        <Eye className="h-5 w-5 text-green-600" />
                                    ) : (
                                        <EyeOff className="h-5 w-5 text-muted-foreground" />
                                    )}
                                    <div>
                                        <p className="font-medium">
                                            {form.is_active ? t('admin.pages.page_visible') : t('admin.pages.page_hidden')}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {form.is_active
                                                ? t('admin.pages.visible_description')
                                                : t('admin.pages.hidden_description')}
                                        </p>
                                    </div>
                                </div>
                                <Switch
                                    checked={form.is_active}
                                    onCheckedChange={(checked) => setForm({ ...form, is_active: checked })}
                                />
                            </div>
                        </CardContent>
                    </Card>

                    <div className="flex justify-end">
                        <Button type="submit" disabled={processing} size="lg">
                            <Save className="mr-2 h-4 w-4" />
                            {processing ? t('common.saving') : t('admin.pages.save_changes')}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
