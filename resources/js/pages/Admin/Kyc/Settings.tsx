import { Head, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import SettingsLayout from '@/layouts/settings/layout';
import AppLayout from '@/layouts/app-layout';
import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import InputError from '@/components/input-error';
import { type BreadcrumbItem } from '@/types';

interface SettingGroup {
    [key: string]: Array<{
        key: string;
        value: any;
        type: string;
        description: string;
    }>;
}

interface Props {
    settings: SettingGroup;
}

export default function KycSettings({ settings }: Props) {
    const { t } = useTranslation();
  const breadcrumbs: BreadcrumbItem[] = [
    {
        title: t('admin.kyc.settings'),
        href: '/admin/kyc/settings',
    },
];
    const flatSettings: Record<string, any> = {};

    // Flatten settings for form
    Object.values(settings).forEach(group => {
        group.forEach(setting => {
            flatSettings[setting.key] = setting.value;
        });
    });

    const { data, setData, post, processing, errors } = useForm(flatSettings);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const settingsArray = Object.entries(data).map(([key, value]) => ({
            key,
            value,
        }));

        post('/admin/kyc/settings', {
            data: { settings: settingsArray },
            preserveScroll: true,
        });
    };

    const renderField = (setting: any) => {
        const { key, type, description } = setting;

        if (type === 'boolean') {
            return (
                <div className="grid gap-2">
                    <div className="flex items-center justify-between">
                        <div className="flex-1">
                            <Label htmlFor={key} className="text-sm font-medium">
                                {formatLabel(key)}
                            </Label>
                            {description && (
                                <p className="text-sm text-muted-foreground mt-1">{description}</p>
                            )}
                        </div>
                        <Switch
                            id={key}
                            checked={!!data[key]}
                            onCheckedChange={(checked) => setData(key, checked)}
                        />
                    </div>
                    <InputError message={errors[key]} />
                </div>
            );
        }

        return (
            <div className="grid gap-2">
                <Label htmlFor={key}>{formatLabel(key)}</Label>
                {description && (
                    <p className="text-sm text-muted-foreground -mt-1">{description}</p>
                )}
                <Input
                    id={key}
                    type={type === 'integer' ? 'number' : 'text'}
                    value={data[key] ?? ''}
                    onChange={(e) => setData(key, type === 'integer' ? parseInt(e.target.value) || null : e.target.value)}
                    placeholder={data[key] === null ? t('admin.kyc.unlimited') : ''}
                    className="w-full"
                />
                <InputError message={errors[key]} />
            </div>
        );
    };

    const formatLabel = (key: string) => {
        return key
            .replace(/^kyc_/, '')
            .replace(/_/g, ' ')
            .replace(/\b\w/g, l => l.toUpperCase());
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('admin.kyc.settings_title')} />

            <SettingsLayout>
                <div className="space-y-6">
                    <div>
                        <HeadingSmall
                            title={t('admin.kyc.settings_title')}
                            description={t('admin.kyc.settings_description')}
                        />
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-6">
                        {/* KYC Feature Toggle - Prominent at top */}
                        {settings.general && settings.general.find(s => s.key === 'kyc_enabled') && (
                            <div className="rounded-lg border bg-card p-6">
                                <div className="flex items-center justify-between">
                                    <div className="flex-1">
                                        <h3 className="text-lg font-semibold">{t('admin.kyc.verification_title')}</h3>
                                        <p className="text-sm text-muted-foreground mt-1">
                                            {t('admin.kyc.verification_toggle_description')}
                                        </p>
                                    </div>
                                    <Switch
                                        id="kyc_enabled"
                                        checked={!!data.kyc_enabled}
                                        onCheckedChange={(checked) => setData('kyc_enabled', checked)}
                                        className="ml-4"
                                    />
                                </div>
                            </div>
                        )}

                        {/* Other General Settings */}
                        {settings.general && settings.general.filter(s => s.key !== 'kyc_enabled').map((setting) => (
                            <div key={setting.key}>
                                {renderField(setting)}
                            </div>
                        ))}

                        {/* Document Settings */}
                        {settings.documents && settings.documents.map((setting) => (
                            <div key={setting.key}>
                                {renderField(setting)}
                            </div>
                        ))}

                        {/* Automation & Notifications */}
                        {settings.automation && settings.automation.map((setting) => (
                            <div key={setting.key}>
                                {renderField(setting)}
                            </div>
                        ))}
                        {settings.notifications && settings.notifications.map((setting) => (
                            <div key={setting.key}>
                                {renderField(setting)}
                            </div>
                        ))}

                        {/* Save Button */}
                        <div className="flex items-center gap-4">
                            <Button type="submit" disabled={processing}>
                                {processing ? t('common.saving') : t('common.save')}
                            </Button>
                        </div>
                    </form>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
