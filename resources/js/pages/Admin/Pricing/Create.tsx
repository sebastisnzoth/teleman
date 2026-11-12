import AppLayout from '@/layouts/app-layout';
import { useTranslation } from 'react-i18next';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';

export default function CreatePricingRule() {
  const { t } = useTranslation();
    const { data, setData, post, processing, errors } = useForm({
        service_type: 'call' as 'call' | 'sms' | 'phone_number',
        country_code: '',
        country_name: '',
        base_cost: '',
        base_cost_unit: 'per_minute',
        markup_type: 'percentage' as 'percentage' | 'fixed' | 'hybrid',
        markup_value: '',
        markup_fixed: '0',
        minimum_charge: '0.0100',
        tier: 'standard',
        notes: '',
        is_active: true,
        auto_update_base_cost: false,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/admin/pricing');
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('common.dashboard'), href: dashboard().url },
        { title: t('admin.pricing.pricing_rules'), href: '/admin/pricing' },
        { title: t('admin.pricing.create_pricing_rule'), href: '/admin/pricing/create' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('admin.pricing.create_pricing_rule')} />

            <div className="mx-auto max-w-7xl space-y-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title={t('admin.pricing.create_pricing_rule')}
                        description={t('admin.pricing.add_a_new_pricing_rule_with_profit_margins')}
                    />
                    <Button variant="outline" asChild>
                        <Link href="/admin/pricing">
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back to Pricing
                        </Link>
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('admin.pricing.pricing_rule_details')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-6">
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="country_code">Country Code</Label>
                                    <Input
                                        id="country_code"
                                        placeholder="US"
                                        maxLength={2}
                                        value={data.country_code}
                                        onChange={(e) => setData('country_code', e.target.value.toUpperCase())}
                                        required
                                    />
                                    {errors.country_code && (
                                        <p className="text-sm text-red-600">{errors.country_code}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="country_name">Country Name</Label>
                                    <Input
                                        id="country_name"
                                        placeholder="United States"
                                        value={data.country_name}
                                        onChange={(e) => setData('country_name', e.target.value)}
                                        required
                                    />
                                    {errors.country_name && (
                                        <p className="text-sm text-red-600">{errors.country_name}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="service_type">Service Type</Label>
                                    <Select
                                        value={data.service_type}
                                        onValueChange={(value) => setData('service_type', value as 'call' | 'sms' | 'phone_number')}
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="call">Voice Call</SelectItem>
                                            <SelectItem value="sms">SMS</SelectItem>
                                            <SelectItem value="phone_number">Phone Number</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {errors.service_type && (
                                        <p className="text-sm text-red-600">{errors.service_type}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="base_cost_unit">Base Cost Unit</Label>
                                    <Select
                                        value={data.base_cost_unit}
                                        onValueChange={(value) => setData('base_cost_unit', value)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="per_minute">Per Minute</SelectItem>
                                            <SelectItem value="per_sms">Per SMS</SelectItem>
                                            <SelectItem value="per_month">Per Month</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {errors.base_cost_unit && (
                                        <p className="text-sm text-red-600">{errors.base_cost_unit}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="base_cost">Base Cost ($)</Label>
                                    <Input
                                        id="base_cost"
                                        type="number"
                                        step="0.0001"
                                        placeholder="0.0050"
                                        value={data.base_cost}
                                        onChange={(e) => setData('base_cost', e.target.value)}
                                        required
                                    />
                                    {errors.base_cost && (
                                        <p className="text-sm text-red-600">{errors.base_cost}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="markup_type">Markup Type</Label>
                                    <Select
                                        value={data.markup_type}
                                        onValueChange={(value) => setData('markup_type', value as 'percentage' | 'fixed' | 'hybrid')}
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="percentage">Percentage</SelectItem>
                                            <SelectItem value="fixed">Fixed Amount</SelectItem>
                                            <SelectItem value="hybrid">Hybrid (Percentage + Fixed)</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {errors.markup_type && (
                                        <p className="text-sm text-red-600">{errors.markup_type}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="markup_value">
                                        {data.markup_type === 'percentage' ? 'Markup Percentage (%)' : 
                                         data.markup_type === 'fixed' ? 'Fixed Markup ($)' :
                                         'Markup Percentage (%)'}
                                    </Label>
                                    <Input
                                        id="markup_value"
                                        type="number"
                                        step="0.01"
                                        placeholder={data.markup_type === 'percentage' ? '25.00' : '0.01'}
                                        value={data.markup_value}
                                        onChange={(e) => setData('markup_value', e.target.value)}
                                        required
                                    />
                                    {errors.markup_value && (
                                        <p className="text-sm text-red-600">{errors.markup_value}</p>
                                    )}
                                </div>

                                {data.markup_type === 'hybrid' && (
                                    <div className="space-y-2">
                                        <Label htmlFor="markup_fixed">Additional Fixed Amount ($)</Label>
                                        <Input
                                            id="markup_fixed"
                                            type="number"
                                            step="0.0001"
                                            placeholder="0.0050"
                                            value={data.markup_fixed}
                                            onChange={(e) => setData('markup_fixed', e.target.value)}
                                        />
                                        {errors.markup_fixed && (
                                            <p className="text-sm text-red-600">{errors.markup_fixed}</p>
                                        )}
                                    </div>
                                )}

                                <div className="space-y-2">
                                    <Label htmlFor="minimum_charge">Minimum Charge ($)</Label>
                                    <Input
                                        id="minimum_charge"
                                        type="number"
                                        step="0.0001"
                                        placeholder="0.0100"
                                        value={data.minimum_charge}
                                        onChange={(e) => setData('minimum_charge', e.target.value)}
                                        required
                                    />
                                    {errors.minimum_charge && (
                                        <p className="text-sm text-red-600">{errors.minimum_charge}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="tier">Pricing Tier</Label>
                                    <Select
                                        value={data.tier}
                                        onValueChange={(value) => setData('tier', value)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="standard">Standard</SelectItem>
                                            <SelectItem value="premium">Premium</SelectItem>
                                            <SelectItem value="enterprise">Enterprise</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {errors.tier && (
                                        <p className="text-sm text-red-600">{errors.tier}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="is_active">Status</Label>
                                    <Select
                                        value={data.is_active ? 'true' : 'false'}
                                        onValueChange={(value) => setData('is_active', value === 'true')}
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="true">Active</SelectItem>
                                            <SelectItem value="false">Inactive</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="auto_update_base_cost">Auto-Update Base Cost</Label>
                                    <Select
                                        value={data.auto_update_base_cost ? 'true' : 'false'}
                                        onValueChange={(value) => setData('auto_update_base_cost', value === 'true')}
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="true">Enabled</SelectItem>
                                            <SelectItem value="false">Disabled</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="notes">Notes (Optional)</Label>
                                <Textarea
                                    id="notes"
                                    placeholder="Additional information about this pricing rule"
                                    value={data.notes}
                                    onChange={(e) => setData('notes', e.target.value)}
                                    rows={3}
                                />
                                {errors.notes && (
                                    <p className="text-sm text-red-600">{errors.notes}</p>
                                )}
                            </div>

                            <div className="flex items-center justify-end space-x-4">
                                <Button type="button" variant="outline" asChild>
                                    <Link href="/admin/pricing">Cancel</Link>
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Creating...' : 'Create Pricing Rule'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
