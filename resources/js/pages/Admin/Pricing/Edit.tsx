import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import AppLayout from '@/layouts/app-layout';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Badge } from '@/components/ui/badge';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Phone, MessageSquare, DollarSign, AlertCircle } from 'lucide-react';
import { providerLabel, useTelephony } from '@/lib/telephony';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';

interface PricingRule {
    id: number;
    service_type: 'call' | 'sms' | 'phone_number';
    base_cost: number;
    base_cost_unit: string;
    markup_type: 'percentage' | 'fixed' | 'hybrid';
    markup_value: number;
    markup_fixed: number | null;
    customer_charge: number | null;
    minimum_charge: number;
    notes: string | null;
    is_active: boolean;
    auto_update_base_cost: boolean;
}

interface PricingData {
    country_code: string;
    country_name: string;
    tier: string;
    services: {
        call: PricingRule | null;
        sms: PricingRule | null;
        phone_number: PricingRule | null;
    };
}

interface ServiceFormData {
    id: number;
    service_type: string;
    base_cost: string;
    base_cost_unit: string;
    markup_type: string;
    markup_value: string;
    markup_fixed: string;
    minimum_charge: string;
    notes: string;
    is_active: boolean;
    auto_update_base_cost: boolean;
}

interface Props {
    pricingData: PricingData;
}

const serviceConfig = {
    call: {
        icon: Phone,
        title: 'Voice Call Pricing',
        description: 'Configure pricing for voice calls per minute',
        color: 'text-gray-600',
        bgColor: 'bg-white',
    },
    sms: {
        icon: MessageSquare,
        title: 'SMS Pricing',
        description: 'Configure pricing for SMS messages',
        color: 'text-gray-600',
        bgColor: 'bg-white',
    },
    phone_number: {
        icon: DollarSign,
        title: 'Phone Number Pricing',
        description: 'Configure pricing for phone number rentals per month',
        color: 'text-gray-600',
        bgColor: 'bg-white',
    },
};

export default function EditPricingRule({ pricingData }: Props) {
  const { t } = useTranslation();
    const [calculatedCharges, setCalculatedCharges] = useState<Record<string, number>>({});

    // Initialize form data for available services
    const initialServices: ServiceFormData[] = [];
    
    Object.entries(pricingData.services).forEach(([serviceType, service]) => {
        if (service && service.base_cost > 0) {
            initialServices.push({
                id: service.id,
                service_type: serviceType,
                base_cost: service.base_cost.toString(),
                base_cost_unit: service.base_cost_unit,
                markup_type: service.markup_type,
                markup_value: service.markup_value.toString(),
                markup_fixed: (service.markup_fixed || 0).toString(),
                minimum_charge: service.minimum_charge.toString(),
                notes: service.notes || '',
                is_active: service.is_active,
                auto_update_base_cost: service.auto_update_base_cost,
            });
        }
    });

    const { data, setData, put, processing, errors } = useForm({
        services: initialServices,
    });

    // Calculate customer charge for a service
    const calculateCharge = (serviceData: ServiceFormData): number => {
        const baseCost = parseFloat(serviceData.base_cost) || 0;
        const markupValue = parseFloat(serviceData.markup_value) || 0;
        const markupFixed = parseFloat(serviceData.markup_fixed) || 0;
        const minimumCharge = parseFloat(serviceData.minimum_charge) || 0;

        let customerCharge = baseCost;

        if (serviceData.markup_type === 'percentage') {
            customerCharge = baseCost * (1 + markupValue / 100);
        } else if (serviceData.markup_type === 'fixed') {
            customerCharge = baseCost + markupValue;
        } else if (serviceData.markup_type === 'hybrid') {
            customerCharge = baseCost * (1 + markupValue / 100) + markupFixed;
        }

        return Math.max(customerCharge, minimumCharge);
    };

    // Update calculated charges when form data changes
    useEffect(() => {
        const charges: Record<string, number> = {};
        data.services.forEach((service) => {
            charges[service.service_type] = calculateCharge(service);
        });
        setCalculatedCharges(charges);
    }, [data.services]);

    const updateServiceData = (index: number, field: keyof ServiceFormData, value: any) => {
        const updatedServices = [...data.services];
        updatedServices[index] = { ...updatedServices[index], [field]: value };
        setData('services', updatedServices);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        // Use the first service's ID for the route (doesn't matter which one)
        const firstServiceId = data.services[0]?.id;
        if (firstServiceId) {
            put(`/admin/pricing/${firstServiceId}`);
        }
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('common.dashboard'), href: dashboard().url },
        { title: t('admin.pricing.pricing_rules'), href: '/admin/pricing' },
        { title: `Edit ${pricingData.country_name}`, href: '#' },
    ];

    // Whose price list these base costs come from. Named once a provider is
    // locked, since an admin chose it; neutral before that.
    const { provider, locked } = useTelephony();
    const costSource = locked ? providerLabel(provider) : 'your telephony provider';

    const getServiceIndex = (serviceType: string): number => {
        return data.services.findIndex(s => s.service_type === serviceType);
    };

    const isServiceAvailable = (serviceType: keyof typeof pricingData.services): boolean => {
        const service = pricingData.services[serviceType];
        return service !== null && service.base_cost > 0;
    };

    const renderServiceCard = (serviceType: 'call' | 'sms' | 'phone_number') => {
        const config = serviceConfig[serviceType];
        const Icon = config.icon;
        const available = isServiceAvailable(serviceType);
        const serviceIndex = getServiceIndex(serviceType);

        return (
            <Card key={serviceType}>
                <CardHeader className={available ? config.bgColor : 'bg-gray-50'}>
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <div className={`rounded-lg p-2 ${available ? 'bg-white' : 'bg-gray-200'}`}>
                                <Icon className={`h-6 w-6 ${available ? config.color : 'text-gray-400'}`} />
                            </div>
                            <div>
                                <CardTitle>{config.title}</CardTitle>
                                <CardDescription>{config.description}</CardDescription>
                            </div>
                        </div>
                        {available ? (
                            <Badge variant="default">Available</Badge>
                        ) : (
                            <Badge variant="secondary">Not Available</Badge>
                        )}
                    </div>
                </CardHeader>
                <CardContent className="pt-6">
                    {!available ? (
                        <div className="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 p-4 text-gray-600">
                            <AlertCircle className="h-5 w-5" />
                            <p className="text-sm">This service is not available for {pricingData.country_name}. Pricing data is not provided by {costSource}.</p>
                        </div>
                    ) : serviceIndex >= 0 ? (
                        <div className="space-y-6">
                            {/* Base Cost Section */}
                            <div className="space-y-4">
                                <h3 className="text-sm font-semibold text-gray-900">Base Cost ({costSource})</h3>
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor={`${serviceType}_base_cost`}>Base Cost ($)</Label>
                                        <Input
                                            id={`${serviceType}_base_cost`}
                                            type="number"
                                            step="0.0001"
                                            min="0"
                                            value={data.services[serviceIndex].base_cost}
                                            onChange={(e) => updateServiceData(serviceIndex, 'base_cost', e.target.value)}
                                            required
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor={`${serviceType}_base_cost_unit`}>Unit</Label>
                                        <Select
                                            value={data.services[serviceIndex].base_cost_unit}
                                            onValueChange={(value) => updateServiceData(serviceIndex, 'base_cost_unit', value)}
                                        >
                                            <SelectTrigger id={`${serviceType}_base_cost_unit`}>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="per_minute">Per Minute</SelectItem>
                                                <SelectItem value="per_sms">Per SMS</SelectItem>
                                                <SelectItem value="per_month">Per Month</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                            </div>

                            {/* Markup Section */}
                            <div className="space-y-4">
                                <h3 className="text-sm font-semibold text-gray-900">Markup Configuration</h3>
                                <div className="grid gap-4 md:grid-cols-3">
                                    <div className="space-y-2">
                                        <Label htmlFor={`${serviceType}_markup_type`}>Markup Type</Label>
                                        <Select
                                            value={data.services[serviceIndex].markup_type}
                                            onValueChange={(value) => updateServiceData(serviceIndex, 'markup_type', value)}
                                        >
                                            <SelectTrigger id={`${serviceType}_markup_type`}>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="percentage">Percentage</SelectItem>
                                                <SelectItem value="fixed">Fixed</SelectItem>
                                                <SelectItem value="hybrid">Hybrid</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor={`${serviceType}_markup_value`}>
                                            {data.services[serviceIndex].markup_type === 'percentage' ? 'Markup (%)' : 'Markup ($)'}
                                        </Label>
                                        <Input
                                            id={`${serviceType}_markup_value`}
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            value={data.services[serviceIndex].markup_value}
                                            onChange={(e) => updateServiceData(serviceIndex, 'markup_value', e.target.value)}
                                            required
                                        />
                                    </div>
                                    {data.services[serviceIndex].markup_type === 'hybrid' && (
                                        <div className="space-y-2">
                                            <Label htmlFor={`${serviceType}_markup_fixed`}>Fixed Amount ($)</Label>
                                            <Input
                                                id={`${serviceType}_markup_fixed`}
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                value={data.services[serviceIndex].markup_fixed}
                                                onChange={(e) => updateServiceData(serviceIndex, 'markup_fixed', e.target.value)}
                                            />
                                        </div>
                                    )}
                                </div>
                            </div>

                            {/* Calculated Customer Charge */}
                            <div className="rounded-lg border-2 border-green-200 bg-green-50 p-4">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="text-sm font-medium text-gray-700">Customer Charge</p>
                                        <p className="text-xs text-gray-500">Calculated based on base cost + markup</p>
                                    </div>
                                    <p className="text-2xl font-bold text-green-700">
                                        ${calculatedCharges[serviceType]?.toFixed(4) || '0.0000'}
                                    </p>
                                </div>
                            </div>

                            {/* Minimum Charge */}
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor={`${serviceType}_minimum_charge`}>Minimum Charge ($)</Label>
                                    <Input
                                        id={`${serviceType}_minimum_charge`}
                                        type="number"
                                        step="0.0001"
                                        min="0"
                                        value={data.services[serviceIndex].minimum_charge}
                                        onChange={(e) => updateServiceData(serviceIndex, 'minimum_charge', e.target.value)}
                                        required
                                    />
                                    <p className="text-xs text-gray-500">Final charge will be at least this amount</p>
                                </div>
                            </div>

                            {/* Settings */}
                            <div className="space-y-4">
                                <h3 className="text-sm font-semibold text-gray-900">Settings</h3>
                                <div className="space-y-4">
                                    <div className="flex items-center justify-between rounded-lg border p-4">
                                        <div className="space-y-0.5">
                                            <Label htmlFor={`${serviceType}_is_active`}>Active Status</Label>
                                            <p className="text-xs text-gray-500">Enable this pricing rule</p>
                                        </div>
                                        <Switch
                                            id={`${serviceType}_is_active`}
                                            checked={data.services[serviceIndex].is_active}
                                            onCheckedChange={(checked) => updateServiceData(serviceIndex, 'is_active', checked)}
                                        />
                                    </div>
                                    <div className="flex items-center justify-between rounded-lg border p-4">
                                        <div className="space-y-0.5">
                                            <Label htmlFor={`${serviceType}_auto_update`}>Auto Update Base Cost</Label>
                                            <p className="text-xs text-gray-500">Automatically update base cost from {costSource}</p>
                                        </div>
                                        <Switch
                                            id={`${serviceType}_auto_update`}
                                            checked={data.services[serviceIndex].auto_update_base_cost}
                                            onCheckedChange={(checked) => updateServiceData(serviceIndex, 'auto_update_base_cost', checked)}
                                        />
                                    </div>
                                </div>
                            </div>

                            {/* Notes */}
                            <div className="space-y-2">
                                <Label htmlFor={`${serviceType}_notes`}>Notes (Optional)</Label>
                                <Textarea
                                    id={`${serviceType}_notes`}
                                    placeholder="Add any notes about this pricing rule..."
                                    value={data.services[serviceIndex].notes}
                                    onChange={(e) => updateServiceData(serviceIndex, 'notes', e.target.value)}
                                    rows={3}
                                />
                            </div>
                        </div>
                    ) : null}
                </CardContent>
            </Card>
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit Pricing - ${pricingData.country_name}`} />

            <div className="mx-auto max-w-7xl space-y-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title={`Edit Pricing Rules`}
                        description={`${pricingData.country_name} (${pricingData.country_code}) - ${pricingData.tier.charAt(0).toUpperCase() + pricingData.tier.slice(1)} Tier`}
                    />
                    <Button variant="outline" asChild>
                        <Link href="/admin/pricing">
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back to Pricing
                        </Link>
                    </Button>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Render all three service cards */}
                    {renderServiceCard('call')}
                    {renderServiceCard('sms')}
                    {renderServiceCard('phone_number')}

                    {/* Submit Button */}
                    <div className="flex justify-end gap-4">
                        <Button type="button" variant="outline" asChild>
                            <Link href="/admin/pricing">Cancel</Link>
                        </Button>
                        <Button type="submit" disabled={processing || data.services.length === 0}>
                            {processing ? 'Saving...' : `Save ${data.services.length} Service${data.services.length !== 1 ? 's' : ''}`}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
