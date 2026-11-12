import AppLayout from '@/layouts/app-layout';
import { useTranslation } from 'react-i18next';
import Heading from '@/components/heading';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Head } from '@inertiajs/react';
import { useForm } from '@inertiajs/react';
import { Settings, Plus, Trash2, Share2, Scale, Upload, ImageIcon } from 'lucide-react';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { router } from '@inertiajs/react';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { useState } from 'react';

interface SocialLink {
    platform: string;
    url: string;
    icon: string;
}

interface LegalLink {
    name: string;
    url: string;
}

const SOCIAL_PLATFORMS = [
    { value: 'facebook', label: 'Facebook', icon: 'Facebook', placeholder: 'https://facebook.com/yourpage' },
    { value: 'instagram', label: 'Instagram', icon: 'Instagram', placeholder: 'https://instagram.com/yourusername' },
    { value: 'linkedin', label: 'LinkedIn', icon: 'Linkedin', placeholder: 'https://linkedin.com/company/yourcompany' },
    { value: 'twitter', label: 'X (Twitter)', icon: 'Twitter', placeholder: 'https://x.com/yourusername' },
    { value: 'youtube', label: 'YouTube', icon: 'Youtube', placeholder: 'https://youtube.com/@yourchannel' },
    { value: 'tiktok', label: 'TikTok', icon: 'Music2', placeholder: 'https://tiktok.com/@yourusername' },
    { value: 'github', label: 'GitHub', icon: 'Github', placeholder: 'https://github.com/yourusername' },
    { value: 'email', label: 'Email', icon: 'Mail', placeholder: 'mailto:hello@example.com' },
];

interface ThemeSettings {
    id: number;
    site_name: string;
    site_tagline: string | null;
    meta_title: string | null;
    meta_description: string | null;
    meta_keywords: string | null;
    logo_path: string | null;
    favicon_path: string | null;
    og_image_path: string | null;
    twitter_card: string | null;
    twitter_site: string | null;
    google_analytics_id: string | null;
    google_tag_manager_id: string | null;
    facebook_pixel_id: string | null;
    custom_head_scripts: string | null;
    custom_body_scripts: string | null;
    primary_color: string;
    secondary_color: string;
    can_register: boolean;
    copyright_text: string | null;
    social_links: SocialLink[] | null;
    legal_links: LegalLink[] | null;
}

interface SettingsProps {
    settings: ThemeSettings;
}

export default function ThemeSettings({ settings }: SettingsProps) {
    const { t } = useTranslation();
    const [ogImageUploading, setOgImageUploading] = useState(false);
    const [ogImagePreview, setOgImagePreview] = useState<string | null>(settings.og_image_path ? (settings as any).og_image_url : null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('common.dashboard'), href: dashboard().url },
        { title: t('admin.theme.theme'), href: '/admin/theme' },
        { title: t('settings.title'), href: '/admin/theme/settings' },
    ];

    const { data, setData, put, processing, errors } = useForm({
        site_name: settings.site_name || '',
        site_tagline: settings.site_tagline || '',
        meta_title: settings.meta_title || '',
        meta_description: settings.meta_description || '',
        meta_keywords: settings.meta_keywords || '',
        logo_path: settings.logo_path || '',
        favicon_path: settings.favicon_path || '',
        og_image_path: settings.og_image_path || '',
        twitter_card: settings.twitter_card || 'summary_large_image',
        twitter_site: settings.twitter_site || '',
        google_analytics_id: settings.google_analytics_id || '',
        google_tag_manager_id: settings.google_tag_manager_id || '',
        facebook_pixel_id: settings.facebook_pixel_id || '',
        custom_head_scripts: settings.custom_head_scripts || '',
        custom_body_scripts: settings.custom_body_scripts || '',
        primary_color: settings.primary_color || '#3b82f6',
        secondary_color: settings.secondary_color || '#10b981',
        can_register: settings.can_register ?? true,
        copyright_text: settings.copyright_text || '© 2025 Teleman AI. All rights reserved.',
        social_links: (settings.social_links || []) as SocialLink[],
        legal_links: (settings.legal_links || []) as LegalLink[],
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put('/admin/theme/settings');
    };

    // Social Links helpers
    const addSocialLink = () => {
        setData('social_links', [...data.social_links, { platform: '', url: '', icon: '' }]);
    };

    const removeSocialLink = (index: number) => {
        setData('social_links', data.social_links.filter((_, i) => i !== index));
    };

    const updateSocialLink = (index: number, field: keyof SocialLink, value: string) => {
        const updated = [...data.social_links];
        updated[index] = { ...updated[index], [field]: value };

        // Auto-set icon when platform changes
        if (field === 'platform') {
            const platform = SOCIAL_PLATFORMS.find(p => p.value === value);
            if (platform) {
                updated[index].icon = platform.icon;
            }
        }

        setData('social_links', updated);
    };

    // Legal Links helpers
    const addLegalLink = () => {
        setData('legal_links', [...data.legal_links, { name: '', url: '' }]);
    };

    const removeLegalLink = (index: number) => {
        setData('legal_links', data.legal_links.filter((_, i) => i !== index));
    };

    const updateLegalLink = (index: number, field: keyof LegalLink, value: string) => {
        const updated = [...data.legal_links];
        updated[index] = { ...updated[index], [field]: value };
        setData('legal_links', updated);
    };

    // OG Image upload handler
    const handleOgImageUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;

        // Client-side validation
        const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
        if (!validTypes.includes(file.type)) {
            alert('Please select a valid image file (JPG, PNG, or WebP)');
            return;
        }
        if (file.size > 4 * 1024 * 1024) {
            alert('Image must be less than 4MB');
            return;
        }

        // Show preview immediately
        const reader = new FileReader();
        reader.onload = (ev) => setOgImagePreview(ev.target?.result as string);
        reader.readAsDataURL(file);

        // Upload to server
        setOgImageUploading(true);
        const formData = new FormData();
        formData.append('og_image', file);

        router.post('/admin/theme/og-image/upload', formData, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setOgImageUploading(false);
            },
            onError: () => {
                setOgImageUploading(false);
                setOgImagePreview(settings.og_image_path ? (settings as any).og_image_url : null);
            },
        });
    };

    const handleOgImageDelete = () => {
        if (!confirm('Are you sure you want to delete the social share image?')) return;

        router.delete('/admin/theme/og-image', {
            preserveScroll: true,
            onSuccess: () => {
                setOgImagePreview(null);
                setData('og_image_path', '');
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('admin.theme.theme_settings')} />

            <div className="mx-auto max-w-7xl space-y-6">
                <Heading
                    title={t('admin.theme.theme_settings')}
                    description={t('admin.theme.customize_your_sites_basic_settings_and_branding')}
                />

                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <Settings className="h-6 w-6" />
                            <CardTitle>{t('admin.theme.theme_settings')}</CardTitle>
                        </div>
                        <CardDescription>
                            Customize your site's basic settings and branding
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-6">
                            <div className="space-y-2">
                                <Label htmlFor="site_name">Site Name</Label>
                                <Input
                                    id="site_name"
                                    value={data.site_name}
                                    onChange={(e) => setData('site_name', e.target.value)}
                                    placeholder="My Awesome Site"
                                />
                                {errors.site_name && (
                                    <p className="text-sm text-red-600">{errors.site_name}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="site_tagline">Site Tagline</Label>
                                <Textarea
                                    id="site_tagline"
                                    value={data.site_tagline}
                                    onChange={(e) => setData('site_tagline', e.target.value)}
                                    placeholder="A brief tagline for your site..."
                                    rows={2}
                                />
                                {errors.site_tagline && (
                                    <p className="text-sm text-red-600">{errors.site_tagline}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="copyright_text">Copyright Text</Label>
                                <Textarea
                                    id="copyright_text"
                                    value={data.copyright_text}
                                    onChange={(e) => setData('copyright_text', e.target.value)}
                                    placeholder="© 2025 Your Company. All rights reserved."
                                    rows={2}
                                    maxLength={500}
                                />
                                <p className="text-xs text-muted-foreground">
                                    {data.copyright_text?.length || 0}/500 characters
                                </p>
                                {errors.copyright_text && (
                                    <p className="text-sm text-red-600">{errors.copyright_text}</p>
                                )}
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="primary_color">Primary Color</Label>
                                    <div className="flex gap-2">
                                        <Input
                                            id="primary_color"
                                            type="color"
                                            value={data.primary_color}
                                            onChange={(e) => setData('primary_color', e.target.value)}
                                            className="w-20 h-10"
                                        />
                                        <Input
                                            value={data.primary_color}
                                            onChange={(e) => setData('primary_color', e.target.value)}
                                            placeholder="#3b82f6"
                                        />
                                    </div>
                                    {errors.primary_color && (
                                        <p className="text-sm text-red-600">{errors.primary_color}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="secondary_color">Secondary Color</Label>
                                    <div className="flex gap-2">
                                        <Input
                                            id="secondary_color"
                                            type="color"
                                            value={data.secondary_color}
                                            onChange={(e) => setData('secondary_color', e.target.value)}
                                            className="w-20 h-10"
                                        />
                                        <Input
                                            value={data.secondary_color}
                                            onChange={(e) => setData('secondary_color', e.target.value)}
                                            placeholder="#10b981"
                                        />
                                    </div>
                                    {errors.secondary_color && (
                                        <p className="text-sm text-red-600">{errors.secondary_color}</p>
                                    )}
                                </div>
                            </div>

                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Saving...' : 'Save Settings'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                {/* SEO Settings Card */}
                <Card>
                    <CardHeader>
                        <CardTitle>{t('admin.theme.seo_meta_tags')}</CardTitle>
                        <CardDescription>
                            Configure search engine optimization and social media sharing
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-6">
                            <div className="space-y-2">
                                <Label htmlFor="meta_title">Meta Title</Label>
                                <Input
                                    id="meta_title"
                                    value={data.meta_title}
                                    onChange={(e) => setData('meta_title', e.target.value)}
                                    placeholder="Your Site Name - Tagline"
                                    maxLength={255}
                                />
                                <p className="text-xs text-muted-foreground">
                                    Recommended: 50-60 characters. This appears in search results and browser tabs.
                                </p>
                                {errors.meta_title && (
                                    <p className="text-sm text-red-600">{errors.meta_title}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="meta_description">Meta Description</Label>
                                <Textarea
                                    id="meta_description"
                                    value={data.meta_description}
                                    onChange={(e) => setData('meta_description', e.target.value)}
                                    placeholder="A compelling description of your site..."
                                    rows={3}
                                    maxLength={500}
                                />
                                <p className="text-xs text-muted-foreground">
                                    Recommended: 150-160 characters. This appears in search results.
                                </p>
                                {errors.meta_description && (
                                    <p className="text-sm text-red-600">{errors.meta_description}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="meta_keywords">Meta Keywords</Label>
                                <Input
                                    id="meta_keywords"
                                    value={data.meta_keywords}
                                    onChange={(e) => setData('meta_keywords', e.target.value)}
                                    placeholder="keyword1, keyword2, keyword3"
                                    maxLength={500}
                                />
                                <p className="text-xs text-muted-foreground">
                                    Comma-separated keywords relevant to your site.
                                </p>
                                {errors.meta_keywords && (
                                    <p className="text-sm text-red-600">{errors.meta_keywords}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label>Social Share Image (OG Image)</Label>
                                {ogImagePreview ? (
                                    <div className="relative group">
                                        <div className="border rounded-lg overflow-hidden bg-muted">
                                            <img
                                                src={ogImagePreview}
                                                alt="OG Image Preview"
                                                className="w-full max-h-[200px] object-cover"
                                            />
                                        </div>
                                        <div className="flex gap-2 mt-2">
                                            <label className="cursor-pointer">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    disabled={ogImageUploading}
                                                    asChild
                                                >
                                                    <span>
                                                        <Upload className="mr-2 h-3 w-3" />
                                                        {ogImageUploading ? 'Uploading...' : 'Replace Image'}
                                                    </span>
                                                </Button>
                                                <input
                                                    type="file"
                                                    accept="image/jpeg,image/jpg,image/png,image/webp"
                                                    onChange={handleOgImageUpload}
                                                    className="hidden"
                                                />
                                            </label>
                                            <Button
                                                type="button"
                                                variant="destructive"
                                                size="sm"
                                                onClick={handleOgImageDelete}
                                            >
                                                <Trash2 className="mr-2 h-3 w-3" />
                                                Delete
                                            </Button>
                                        </div>
                                    </div>
                                ) : (
                                    <label className="cursor-pointer">
                                        <div className="border-2 border-dashed rounded-lg p-8 text-center hover:border-primary/50 hover:bg-muted/50 transition-colors">
                                            <ImageIcon className="mx-auto h-10 w-10 text-muted-foreground/50 mb-3" />
                                            <p className="text-sm font-medium text-muted-foreground">
                                                {ogImageUploading ? 'Uploading...' : 'Click to upload image'}
                                            </p>
                                            <p className="text-xs text-muted-foreground mt-1">
                                                JPG, PNG, or WebP. Max 4MB.
                                            </p>
                                        </div>
                                        <input
                                            type="file"
                                            accept="image/jpeg,image/jpg,image/png,image/webp"
                                            onChange={handleOgImageUpload}
                                            disabled={ogImageUploading}
                                            className="hidden"
                                        />
                                    </label>
                                )}
                                <p className="text-xs text-muted-foreground">
                                    Image displayed when sharing on social media. Recommended: 1200x630px.
                                </p>
                                {errors.og_image_path && (
                                    <p className="text-sm text-red-600">{errors.og_image_path}</p>
                                )}
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="twitter_card">Twitter Card Type</Label>
                                    <Input
                                        id="twitter_card"
                                        value={data.twitter_card}
                                        onChange={(e) => setData('twitter_card', e.target.value)}
                                        placeholder="summary_large_image"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Usually: summary or summary_large_image
                                    </p>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="twitter_site">Twitter Handle</Label>
                                    <Input
                                        id="twitter_site"
                                        value={data.twitter_site}
                                        onChange={(e) => setData('twitter_site', e.target.value)}
                                        placeholder="@yourusername"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Your Twitter/X account handle
                                    </p>
                                </div>
                            </div>

                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Saving...' : 'Save SEO Settings'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                {/* Analytics & Tracking Card */}
                <Card>
                    <CardHeader>
                        <CardTitle>{t('admin.theme.analytics_tracking')}</CardTitle>
                        <CardDescription>
                            Configure tracking pixels and analytics tools
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-6">
                            <div className="space-y-2">
                                <Label htmlFor="google_analytics_id">Google Analytics ID</Label>
                                <Input
                                    id="google_analytics_id"
                                    value={data.google_analytics_id}
                                    onChange={(e) => setData('google_analytics_id', e.target.value)}
                                    placeholder="G-XXXXXXXXXX"
                                />
                                <p className="text-xs text-muted-foreground">
                                    Your Google Analytics 4 measurement ID
                                </p>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="google_tag_manager_id">Google Tag Manager ID</Label>
                                <Input
                                    id="google_tag_manager_id"
                                    value={data.google_tag_manager_id}
                                    onChange={(e) => setData('google_tag_manager_id', e.target.value)}
                                    placeholder="GTM-XXXXXXX"
                                />
                                <p className="text-xs text-muted-foreground">
                                    Your Google Tag Manager container ID
                                </p>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="facebook_pixel_id">Facebook Pixel ID</Label>
                                <Input
                                    id="facebook_pixel_id"
                                    value={data.facebook_pixel_id}
                                    onChange={(e) => setData('facebook_pixel_id', e.target.value)}
                                    placeholder="123456789012345"
                                />
                                <p className="text-xs text-muted-foreground">
                                    Your Facebook/Meta Pixel ID for ad tracking
                                </p>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="custom_head_scripts">Custom Head Scripts</Label>
                                <Textarea
                                    id="custom_head_scripts"
                                    value={data.custom_head_scripts}
                                    onChange={(e) => setData('custom_head_scripts', e.target.value)}
                                    placeholder="<script>/* Your custom scripts */</script>"
                                    rows={5}
                                    className="font-mono text-sm"
                                />
                                <p className="text-xs text-muted-foreground">
                                    Scripts to be added in the {"<head>"} section. Use for verification codes, etc.
                                </p>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="custom_body_scripts">Custom Body Scripts</Label>
                                <Textarea
                                    id="custom_body_scripts"
                                    value={data.custom_body_scripts}
                                    onChange={(e) => setData('custom_body_scripts', e.target.value)}
                                    placeholder="<script>/* Your custom scripts */</script>"
                                    rows={5}
                                    className="font-mono text-sm"
                                />
                                <p className="text-xs text-muted-foreground">
                                    Scripts to be added before {"</body>"}. Use for chat widgets, etc.
                                </p>
                            </div>

                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Saving...' : 'Save Analytics Settings'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                {/* Social Media Links Card */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <Share2 className="h-6 w-6" />
                            <CardTitle>{t('admin.theme.social_media_links')}</CardTitle>
                        </div>
                        <CardDescription>
                            {t('admin.theme.social_media_links_description')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-6">
                            <div className="space-y-4">
                                {data.social_links.map((link, index) => {
                                    const platformConfig = SOCIAL_PLATFORMS.find(p => p.value === link.platform);
                                    return (
                                        <div key={index} className="flex items-start gap-3 p-4 border rounded-lg">
                                            <div className="flex-1 grid grid-cols-2 gap-3">
                                                <div className="space-y-1">
                                                    <Label className="text-xs text-muted-foreground">{t('admin.theme.platform')}</Label>
                                                    <Select
                                                        value={link.platform}
                                                        onValueChange={(value) => updateSocialLink(index, 'platform', value)}
                                                    >
                                                        <SelectTrigger>
                                                            <SelectValue placeholder={t('admin.theme.select_platform')} />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {SOCIAL_PLATFORMS.map((platform) => (
                                                                <SelectItem key={platform.value} value={platform.value}>
                                                                    {platform.label}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                <div className="space-y-1">
                                                    <Label className="text-xs text-muted-foreground">URL</Label>
                                                    <Input
                                                        value={link.url}
                                                        onChange={(e) => updateSocialLink(index, 'url', e.target.value)}
                                                        placeholder={platformConfig?.placeholder || 'https://...'}
                                                    />
                                                </div>
                                            </div>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="mt-6 text-destructive hover:text-destructive"
                                                onClick={() => removeSocialLink(index)}
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    );
                                })}

                                {data.social_links.length === 0 && (
                                    <p className="text-sm text-muted-foreground text-center py-4">
                                        {t('admin.theme.no_social_links')}
                                    </p>
                                )}

                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={addSocialLink}
                                    className="w-full"
                                >
                                    <Plus className="mr-2 h-4 w-4" />
                                    {t('admin.theme.add_social_link')}
                                </Button>
                            </div>

                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Saving...' : t('admin.theme.save_social_links')}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                {/* Legal Links Card */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-2">
                            <Scale className="h-6 w-6" />
                            <CardTitle>{t('admin.theme.legal_links')}</CardTitle>
                        </div>
                        <CardDescription>
                            {t('admin.theme.legal_links_description')}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-6">
                            <div className="space-y-4">
                                {data.legal_links.map((link, index) => (
                                    <div key={index} className="flex items-start gap-3 p-4 border rounded-lg">
                                        <div className="flex-1 grid grid-cols-2 gap-3">
                                            <div className="space-y-1">
                                                <Label className="text-xs text-muted-foreground">{t('admin.theme.link_name')}</Label>
                                                <Input
                                                    value={link.name}
                                                    onChange={(e) => updateLegalLink(index, 'name', e.target.value)}
                                                    placeholder="Privacy Policy"
                                                />
                                            </div>
                                            <div className="space-y-1">
                                                <Label className="text-xs text-muted-foreground">URL</Label>
                                                <Input
                                                    value={link.url}
                                                    onChange={(e) => updateLegalLink(index, 'url', e.target.value)}
                                                    placeholder="/privacy-policy"
                                                />
                                            </div>
                                        </div>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="mt-6 text-destructive hover:text-destructive"
                                            onClick={() => removeLegalLink(index)}
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </div>
                                ))}

                                {data.legal_links.length === 0 && (
                                    <p className="text-sm text-muted-foreground text-center py-4">
                                        {t('admin.theme.no_legal_links')}
                                    </p>
                                )}

                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={addLegalLink}
                                    className="w-full"
                                >
                                    <Plus className="mr-2 h-4 w-4" />
                                    {t('admin.theme.add_legal_link')}
                                </Button>
                            </div>

                            <div className="flex justify-end">
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Saving...' : t('admin.theme.save_legal_links')}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
