import AppLayout from '@/layouts/app-layout';
import { useTranslation } from 'react-i18next';
import Heading from '@/components/heading';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Head } from '@inertiajs/react';
import { useForm } from '@inertiajs/react';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';

interface ThemeFooter {
    id: number;
    cta_badge_text: string;
    cta_badge_icon: string | null;
    cta_headline: string;
    cta_description: string;
    cta_primary_text: string;
    cta_primary_icon: string | null;
    cta_secondary_text: string;
    background_gradient: string | null;
}

interface FooterProps {
    footer: ThemeFooter;
}

export default function ThemeFooter({ footer }: FooterProps) {
  const { t } = useTranslation();
    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('common.dashboard'), href: dashboard().url },
        { title: t('admin.theme.theme'), href: '/admin/theme' },
        { title: t('admin.theme.footer'), href: '/admin/theme/footer' },
    ];

    const { data, setData, put, processing } = useForm({
        cta_badge_text: footer.cta_badge_text || '',
        cta_badge_icon: footer.cta_badge_icon || '',
        cta_headline: footer.cta_headline || '',
        cta_description: footer.cta_description || '',
        cta_primary_text: footer.cta_primary_text || '',
        cta_primary_icon: footer.cta_primary_icon || '',
        cta_secondary_text: footer.cta_secondary_text || '',
        background_gradient: footer.background_gradient || '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put('/admin/theme/footer');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('admin.theme.footer_section')} />

            <div className="mx-auto max-w-7xl space-y-6">
                <Heading
                    title={t('admin.theme.footer_section')}
                    description={t('admin.theme.manage_footer_cta_content_and_links')}
                />

                <Card>
                    <CardContent className="pt-6">
                        <form onSubmit={handleSubmit} className="space-y-6">
                            <div className="space-y-2">
                                <Label htmlFor="cta_badge_text">CTA Badge Text</Label>
                                <Input
                                    id="cta_badge_text"
                                    value={data.cta_badge_text}
                                    onChange={(e) => setData('cta_badge_text', e.target.value)}
                                    placeholder="Ready to get started?"
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="cta_badge_icon">CTA Badge Icon</Label>
                                <Input
                                    id="cta_badge_icon"
                                    value={data.cta_badge_icon}
                                    onChange={(e) => setData('cta_badge_icon', e.target.value)}
                                    placeholder="🚀"
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="cta_headline">CTA Headline</Label>
                                <Input
                                    id="cta_headline"
                                    value={data.cta_headline}
                                    onChange={(e) => setData('cta_headline', e.target.value)}
                                    placeholder="Start making AI calls today"
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="cta_description">CTA Description</Label>
                                <Textarea
                                    id="cta_description"
                                    value={data.cta_description}
                                    onChange={(e) => setData('cta_description', e.target.value)}
                                    placeholder="Join thousands of businesses..."
                                    rows={3}
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="cta_primary_text">Primary Button Text</Label>
                                <Input
                                    id="cta_primary_text"
                                    value={data.cta_primary_text}
                                    onChange={(e) => setData('cta_primary_text', e.target.value)}
                                    placeholder="Get Started Free"
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="cta_primary_icon">Primary Button Icon</Label>
                                <Input
                                    id="cta_primary_icon"
                                    value={data.cta_primary_icon}
                                    onChange={(e) => setData('cta_primary_icon', e.target.value)}
                                    placeholder="→"
                                />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="cta_secondary_text">Secondary Button Text</Label>
                                <Input
                                    id="cta_secondary_text"
                                    value={data.cta_secondary_text}
                                    onChange={(e) => setData('cta_secondary_text', e.target.value)}
                                    placeholder="Contact Sales"
                                />
                            </div>

                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Saving...' : 'Save Changes'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
