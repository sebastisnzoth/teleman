import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';

interface Props {
    language?: {
        id: number;
        code: string;
        name: string;
        native_name: string | null;
        flag: string | null;
        is_rtl: boolean;
        is_active: boolean;
        is_default: boolean;
    };
}

export default function LanguageCreate({ language }: Props) {
    const { t } = useTranslation();
    const isEditing = !!language;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: t('admin.languages.title'), href: '/admin/languages' },
        { title: isEditing ? t('admin.languages.edit') : t('admin.languages.create'), href: '#' },
    ];

    const { data, setData, post, put, processing, errors } = useForm({
        code: language?.code || '',
        name: language?.name || '',
        native_name: language?.native_name || '',
        flag: language?.flag || '',
        is_rtl: language?.is_rtl || false,
        is_active: language?.is_active ?? true,
        is_default: language?.is_default || false,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (isEditing) {
            put(`/admin/languages/${language!.id}`);
        } else {
            post('/admin/languages');
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={isEditing ? t('admin.languages.edit') : t('admin.languages.create')} />

            <div className="mx-auto max-w-2xl space-y-6 p-6">
                <div>
                    <h2 className="text-2xl font-bold tracking-tight">
                        {isEditing ? t('admin.languages.edit') : t('admin.languages.add_new')}
                    </h2>
                    <p className="text-muted-foreground">
                        {isEditing ? t('admin.languages.edit_subtitle') : t('admin.languages.create_subtitle')}
                    </p>
                </div>

                <form onSubmit={handleSubmit}>
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('admin.languages.language_details')}</CardTitle>
                            <CardDescription>{t('admin.languages.language_details_description')}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="code">{t('admin.languages.code')}</Label>
                                    <Input
                                        id="code"
                                        value={data.code}
                                        onChange={(e) => setData('code', e.target.value)}
                                        placeholder="e.g., en, fr, ar"
                                        maxLength={10}
                                    />
                                    {errors.code && <p className="text-sm text-red-600">{errors.code}</p>}
                                    <p className="text-muted-foreground text-xs">{t('admin.languages.code_hint')}</p>
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="flag">{t('admin.languages.flag')}</Label>
                                    <Input
                                        id="flag"
                                        value={data.flag}
                                        onChange={(e) => setData('flag', e.target.value)}
                                        placeholder="e.g., 🇺🇸"
                                        maxLength={10}
                                    />
                                    {errors.flag && <p className="text-sm text-red-600">{errors.flag}</p>}
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="name">{t('admin.languages.name_english')}</Label>
                                    <Input
                                        id="name"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        placeholder="e.g., English, French"
                                    />
                                    {errors.name && <p className="text-sm text-red-600">{errors.name}</p>}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="native_name">{t('admin.languages.native_name')}</Label>
                                    <Input
                                        id="native_name"
                                        value={data.native_name}
                                        onChange={(e) => setData('native_name', e.target.value)}
                                        placeholder="e.g., Français, العربية"
                                    />
                                    {errors.native_name && <p className="text-sm text-red-600">{errors.native_name}</p>}
                                </div>
                            </div>

                            <div className="space-y-4 pt-4">
                                <div className="flex items-center justify-between rounded-lg border p-4">
                                    <div>
                                        <Label htmlFor="is_rtl" className="font-medium">{t('admin.languages.rtl')}</Label>
                                        <p className="text-muted-foreground text-sm">{t('admin.languages.rtl_description')}</p>
                                    </div>
                                    <Switch
                                        id="is_rtl"
                                        checked={data.is_rtl}
                                        onCheckedChange={(checked) => setData('is_rtl', checked)}
                                    />
                                </div>

                                <div className="flex items-center justify-between rounded-lg border p-4">
                                    <div>
                                        <Label htmlFor="is_active" className="font-medium">{t('admin.languages.active')}</Label>
                                        <p className="text-muted-foreground text-sm">{t('admin.languages.active_description')}</p>
                                    </div>
                                    <Switch
                                        id="is_active"
                                        checked={data.is_active}
                                        onCheckedChange={(checked) => setData('is_active', checked)}
                                    />
                                </div>

                                <div className="flex items-center justify-between rounded-lg border p-4">
                                    <div>
                                        <Label htmlFor="is_default" className="font-medium">{t('admin.languages.default_language')}</Label>
                                        <p className="text-muted-foreground text-sm">{t('admin.languages.default_description')}</p>
                                    </div>
                                    <Switch
                                        id="is_default"
                                        checked={data.is_default}
                                        onCheckedChange={(checked) => setData('is_default', checked)}
                                    />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <div className="mt-6 flex items-center justify-end gap-3">
                        <Link href="/admin/languages">
                            <Button type="button" variant="outline">{t('common.cancel')}</Button>
                        </Link>
                        <Button type="submit" disabled={processing}>
                            {processing ? t('common.saving') : isEditing ? t('admin.languages.update') : t('admin.languages.create')}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
