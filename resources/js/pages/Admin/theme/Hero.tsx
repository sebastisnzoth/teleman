import AppLayout from '@/layouts/app-layout';
import { useTranslation } from 'react-i18next';
import Heading from '@/components/heading';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Head, useForm } from '@inertiajs/react';
import { Sparkles } from 'lucide-react';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';

interface Hero {
    id?: number;
    badge_text: string;
    badge_icon_left: string | null;
    badge_icon_right: string | null;
    typewriter_text: string;
    subtitle: string;
    subtitle_emoji: string | null;
    primary_cta_text: string;
    primary_cta_icon: string | null;
    secondary_cta_text: string;
    secondary_cta_icon: string | null;
    background_gradient: string | null;
}

interface HeroProps {
    hero: Hero;
}

export default function HeroSection({ hero }: HeroProps) {
  const { t } = useTranslation();
    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('common.dashboard'), href: dashboard().url },
        { title: t('admin.theme.theme'), href: '/admin/theme' },
        { title: t('admin.theme.hero_section'), href: '/admin/theme/hero' },
    ];

    const { data, setData, put, processing, errors } = useForm({
        badge_text: hero.badge_text || '',
        badge_icon_left: hero.badge_icon_left || '',
        badge_icon_right: hero.badge_icon_right || '',
        typewriter_text: hero.typewriter_text || '',
        subtitle: hero.subtitle || '',
        subtitle_emoji: hero.subtitle_emoji || '',
        primary_cta_text: hero.primary_cta_text || '',
        primary_cta_icon: hero.primary_cta_icon || '',
        secondary_cta_text: hero.secondary_cta_text || '',
        secondary_cta_icon: hero.secondary_cta_icon || '',
        background_gradient: hero.background_gradient || '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put('/admin/theme/hero');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('admin.theme.hero_section')} />

            <div className="mx-auto max-w-7xl space-y-6">
                <Heading
                    title={t('admin.theme.hero_section')}
                    description={t('admin.theme.manage_your_hero_section_content')}
                />

                <Card>
                    <CardContent className="pt-6">
                        <form onSubmit={handleSubmit} className="space-y-6">
                            {/* Badge */}
                            <div className="space-y-4">
                                <h3 className="text-lg font-semibold">Badge</h3>
                                <div className="grid gap-4 md:grid-cols-3">
                                    <div className="space-y-2">
                                        <Label htmlFor="badge_icon_left">Left Icon</Label>
                                        <Input
                                            id="badge_icon_left"
                                            value={data.badge_icon_left}
                                            onChange={(e) => setData('badge_icon_left', e.target.value)}
                                            placeholder="✨"
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="badge_text">Badge Text *</Label>
                                        <Input
                                            id="badge_text"
                                            value={data.badge_text}
                                            onChange={(e) => setData('badge_text', e.target.value)}
                                            placeholder="New Feature"
                                        />
                                        {errors.badge_text && (
                                            <p className="text-sm text-red-600">{errors.badge_text}</p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="badge_icon_right">Right Icon</Label>
                                        <Input
                                            id="badge_icon_right"
                                            value={data.badge_icon_right}
                                            onChange={(e) => setData('badge_icon_right', e.target.value)}
                                            placeholder="🎉"
                                        />
                                    </div>
                                </div>
                            </div>

                            {/* Main Content */}
                            <div className="space-y-4">
                                <h3 className="text-lg font-semibold">Main Content</h3>
                                <div className="space-y-2">
                                    <Label htmlFor="typewriter_text">Typewriter Text *</Label>
                                    <Input
                                        id="typewriter_text"
                                        value={data.typewriter_text}
                                        onChange={(e) => setData('typewriter_text', e.target.value)}
                                        placeholder="AI-Powered Phone Calls"
                                    />
                                    {errors.typewriter_text && (
                                        <p className="text-sm text-red-600">{errors.typewriter_text}</p>
                                    )}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="subtitle">Subtitle *</Label>
                                    <Textarea
                                        id="subtitle"
                                        value={data.subtitle}
                                        onChange={(e) => setData('subtitle', e.target.value)}
                                        placeholder="Your campaign description..."
                                        rows={3}
                                    />
                                    {errors.subtitle && (
                                        <p className="text-sm text-red-600">{errors.subtitle}</p>
                                    )}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="subtitle_emoji">Subtitle Emoji</Label>
                                    <Input
                                        id="subtitle_emoji"
                                        value={data.subtitle_emoji}
                                        onChange={(e) => setData('subtitle_emoji', e.target.value)}
                                        placeholder="🚀"
                                    />
                                </div>
                            </div>

                            {/* CTAs */}
                            <div className="space-y-4">
                                <h3 className="text-lg font-semibold">Call-to-Action Buttons</h3>
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="primary_cta_text">Primary CTA Text *</Label>
                                        <Input
                                            id="primary_cta_text"
                                            value={data.primary_cta_text}
                                            onChange={(e) => setData('primary_cta_text', e.target.value)}
                                            placeholder="Get Started"
                                        />
                                        {errors.primary_cta_text && (
                                            <p className="text-sm text-red-600">{errors.primary_cta_text}</p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="primary_cta_icon">Primary CTA Icon</Label>
                                        <Input
                                            id="primary_cta_icon"
                                            value={data.primary_cta_icon}
                                            onChange={(e) => setData('primary_cta_icon', e.target.value)}
                                            placeholder="→"
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="secondary_cta_text">Secondary CTA Text *</Label>
                                        <Input
                                            id="secondary_cta_text"
                                            value={data.secondary_cta_text}
                                            onChange={(e) => setData('secondary_cta_text', e.target.value)}
                                            placeholder="Learn More"
                                        />
                                        {errors.secondary_cta_text && (
                                            <p className="text-sm text-red-600">{errors.secondary_cta_text}</p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="secondary_cta_icon">Secondary CTA Icon</Label>
                                        <Input
                                            id="secondary_cta_icon"
                                            value={data.secondary_cta_icon}
                                            onChange={(e) => setData('secondary_cta_icon', e.target.value)}
                                            placeholder="📖"
                                        />
                                    </div>
                                </div>
                            </div>

                            <div className="flex justify-end gap-4">
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
