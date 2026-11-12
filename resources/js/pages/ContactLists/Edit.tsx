import { Head, useForm, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Card } from '@/components/ui/card';
import { BreadcrumbItem } from '@/types';
import { useTranslation } from 'react-i18next';

interface ContactList {
    id: number;
    name: string;
    description: string;
}

interface Props {
    list: ContactList;
}

export default function Edit({ list }: Props) {
    const { t } = useTranslation();
    const { data, setData, put, processing, errors } = useForm({
        name: list.name,
        description: list.description || '',
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: t('contact_lists.title'), href: '/contact-lists' },
        { title: list.name, href: `/contact-lists/${list.id}` },
        { title: t('common.edit'), href: `/contact-lists/${list.id}/edit` },
    ];

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/contact-lists/${list.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${t('contact_lists.edit')} ${list.name}`} />

            <div className="mx-auto max-w-7xl space-y-6">
                <div className="flex justify-between items-center">
                    <div>
                        <h1 className="text-3xl font-bold">{t('contact_lists.edit')}</h1>
                        <p className="text-muted-foreground">{t('contact_lists.edit_description')}</p>
                    </div>
                    <Button variant="outline" onClick={() => router.get(`/contact-lists/${list.id}`)}>
                        {t('common.cancel')}
                    </Button>
                </div>

                <Card className="p-6 max-w-2xl">
                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div className="space-y-2">
                            <Label htmlFor="name">{t('contact_lists.list_name_required')}</Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder="e.g., VIP Customers"
                                required
                            />
                            {errors.name && (
                                <p className="text-sm text-destructive">{errors.name}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="description">{t('common.description')}</Label>
                            <Textarea
                                id="description"
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                placeholder={t('contact_lists.description_placeholder')}
                                rows={4}
                            />
                            {errors.description && (
                                <p className="text-sm text-destructive">{errors.description}</p>
                            )}
                        </div>

                        <div className="flex justify-end gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => router.get(`/contact-lists/${list.id}`)}
                            >
                                {t('common.cancel')}
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing ? t('contact_lists.updating') : t('contact_lists.update')}
                            </Button>
                        </div>
                    </form>
                </Card>
            </div>
        </AppLayout>
    );
}
