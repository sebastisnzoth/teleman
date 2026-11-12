import { Head, useForm, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Card } from '@/components/ui/card';
import { BreadcrumbItem } from '@/types';
import { useTranslation } from 'react-i18next';

export default function Create() {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        description: '',
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: t('contact_lists.title'), href: '/contact-lists' },
        { title: t('contact_lists.create'), href: '/contact-lists/create' },
    ];

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/contact-lists');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('contact_lists.create')} />

            <div className="mx-auto max-w-7xl space-y-6">
                <div className="flex justify-between items-center">
                    <div>
                        <h1 className="text-3xl font-bold">{t('contact_lists.create')}</h1>
                        <p className="text-muted-foreground">{t('contact_lists.create_description')}</p>
                    </div>
                    <Button variant="outline" onClick={() => router.get('/contact-lists')}>
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
                                onClick={() => router.get('/contact-lists')}
                            >
                                {t('common.cancel')}
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing ? t('contact_lists.creating') : t('contact_lists.add')}
                            </Button>
                        </div>
                    </form>
                </Card>
            </div>
        </AppLayout>
    );
}
