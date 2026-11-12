import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import AppLayout from '@/layouts/app-layout';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Head, Link, router } from '@inertiajs/react';
import { DollarSign, Globe, Phone, MessageSquare, Plus, RefreshCw, Pencil, Trash2, Pin, PinOff } from 'lucide-react';
import { toast } from 'sonner';
import { PageHelp } from '@/components/page-help';
import { providerLabel, useTelephony } from '@/lib/telephony';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';

interface PricingRule {
    id: number;
    country_code: string;
    country_name: string;
    service_type: 'call' | 'sms' | 'phone_number';
    base_cost: string | number;
    base_cost_unit: string;
    markup_type: string;
    markup_value: string | number;
    markup_fixed?: string | number;
    minimum_charge: string | number;
    customer_charge: string | number;
    tier: string;
    is_active: boolean;
    notes?: string;
}

interface ServicePricing {
    id: number;
    base_cost: string | number;
    base_cost_unit: string;
    markup_type: string;
    markup_value: string | number;
    markup_fixed?: string | number;
    customer_charge: string | number;
    minimum_charge: string | number;
    is_active: boolean;
}

interface GroupedPricingRule {
    country_code: string;
    country_name: string;
    tier: string;
    is_pinned: boolean;
    services: {
        call?: ServicePricing;
        sms?: ServicePricing;
        phone_number?: ServicePricing;
    };
}

interface PaginatedPricingRules {
    data: GroupedPricingRule[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

interface Stats {
    total_rules: number;
    active_rules: number;
    by_service: {
        call: number;
        sms: number;
        phone_number: number;
    };
    by_tier: {
        standard: number;
        premium: number;
        enterprise: number;
    };
}

interface Props {
    pricingRules: PaginatedPricingRules;
    stats: Stats;
}

export default function PricingIndex({ pricingRules, stats }: Props) {
    const { t } = useTranslation();
    // Get the data array from paginated response
    const rulesArray = pricingRules?.data || [];
    
    const [showBulkUpdate, setShowBulkUpdate] = useState(false);
    const [bulkUpdateData, setBulkUpdateData] = useState({
        service_type: 'call' as 'call' | 'sms' | 'phone_number' | 'all',
        markup_type: 'percentage' as 'percentage' | 'fixed' | 'hybrid',
        markup_value: '',
        markup_fixed: '',
    });
    const [filters, setFilters] = useState({
        service_type: 'all',
        active: 'all',
        search: '',
    });
    const [perPage, setPerPage] = useState(20);
    const [jumpToPage, setJumpToPage] = useState('');
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [alertDialog, setAlertDialog] = useState<{ show: boolean; title: string; description: string; variant?: 'default' | 'destructive' }>({
        show: false,
        title: '',
        description: '',
        variant: 'default',
    });
    const [confirmDialog, setConfirmDialog] = useState<{ show: boolean; title: string; description: string; onConfirm: () => void }>({
        show: false,
        title: '',
        description: '',
        onConfirm: () => {},
    });

    const handleFilterChange = (key: string, value: string) => {
        const newFilters = { ...filters, [key]: value };
        setFilters(newFilters);
        
        // Build query params
        const params = new URLSearchParams();
        if (newFilters.service_type !== 'all') params.append('service_type', newFilters.service_type);
        if (newFilters.active !== 'all') params.append('active', newFilters.active === 'active' ? 'true' : 'false');
        if (newFilters.search) params.append('search', newFilters.search);
        
        router.get(`/admin/pricing?${params.toString()}`, {}, { preserveState: true });
    };

    const handleSelectAll = (checked: boolean) => {
        if (checked) {
            // Collect all service IDs from all countries
            const allIds: number[] = [];
            rulesArray.forEach(country => {
                if (country.services.call?.id) allIds.push(country.services.call.id);
                if (country.services.sms?.id) allIds.push(country.services.sms.id);
                if (country.services.phone_number?.id) allIds.push(country.services.phone_number.id);
            });
            setSelectedIds(allIds);
        } else {
            setSelectedIds([]);
        }
    };

    const handleSelectOne = (id: number, checked: boolean) => {
        if (checked) {
            setSelectedIds([...selectedIds, id]);
        } else {
            setSelectedIds(selectedIds.filter(selectedId => selectedId !== id));
        }
    };

    const handleDelete = (id: number) => {
        setConfirmDialog({
            show: true,
            title: t('admin.pricing.delete_rule'),
            description: t('admin.pricing.delete_rule_confirm'),
            onConfirm: () => {
                router.delete(`/admin/pricing/${id}`, {
                    preserveScroll: true,
                    onSuccess: () => {
                        setAlertDialog({
                            show: true,
                            title: t('common.success'),
                            description: t('admin.pricing.rule_deleted'),
                            variant: 'default',
                        });
                    },
                });
            },
        });
    };

    const handleTogglePin = (countryCode: string, isPinned: boolean) => {
        router.post('/admin/pricing/toggle-pin', {
            country_code: countryCode,
            is_pinned: !isPinned,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                // Optional: show success message
            },
        });
    };

    const handleBulkDelete = () => {
        if (selectedIds.length === 0) {
            setAlertDialog({
                show: true,
                title: 'No Selection',
                description: 'Please select at least one pricing rule to delete',
                variant: 'destructive',
            });
            return;
        }

        setConfirmDialog({
            show: true,
            title: 'Bulk Delete',
            description: `Are you sure you want to delete ${selectedIds.length} pricing rule(s)? This action cannot be undone.`,
            onConfirm: () => {
                router.post('/admin/pricing/bulk-delete', { ids: selectedIds }, {
                    preserveScroll: true,
                    onSuccess: () => {
                        setSelectedIds([]);
                        setAlertDialog({
                            show: true,
                            title: 'Success',
                            description: 'Pricing rules deleted successfully',
                            variant: 'default',
                        });
                    },
                });
            },
        });
    };

    const handleBulkUpdate = () => {
        if (!bulkUpdateData.markup_value) {
            setAlertDialog({
                show: true,
                title: 'Validation Error',
                description: 'Please enter a markup value',
                variant: 'destructive',
            });
            return;
        }

        if (bulkUpdateData.markup_type === 'hybrid' && !bulkUpdateData.markup_fixed) {
            setAlertDialog({
                show: true,
                title: 'Validation Error',
                description: 'Please enter a fixed amount for hybrid markup',
                variant: 'destructive',
            });
            return;
        }

        // Count how many services will be affected
        let affectedCount = 0;
        rulesArray.forEach(country => {
            if (bulkUpdateData.service_type === 'all') {
                // Count all available services for this country
                if (country.services.call) affectedCount++;
                if (country.services.sms) affectedCount++;
                if (country.services.phone_number) affectedCount++;
            } else {
                // Count only if the specific service exists
                if (country.services[bulkUpdateData.service_type]) affectedCount++;
            }
        });

        if (affectedCount === 0) {
            setAlertDialog({
                show: true,
                title: 'No Rules Found',
                description: `No pricing rules found for ${bulkUpdateData.service_type === 'all' ? 'all services' : bulkUpdateData.service_type}`,
                variant: 'destructive',
            });
            return;
        }

        const serviceLabel = bulkUpdateData.service_type === 'all' ? 'all services' : bulkUpdateData.service_type;
        setConfirmDialog({
            show: true,
            title: 'Confirm Bulk Update',
            description: `This will update ${affectedCount} pricing rule(s) for ${serviceLabel}. This action cannot be undone. Are you sure you want to continue?`,
            onConfirm: () => performBulkUpdate(),
        });
    };

    const performBulkUpdate = () => {
        setIsSubmitting(true);

        router.post('/admin/pricing/bulk-update-markup', bulkUpdateData, {
            preserveScroll: true,
            onSuccess: (page) => {
                setShowBulkUpdate(false);
                setBulkUpdateData({
                    service_type: 'call',
                    markup_type: 'percentage',
                    markup_value: '',
                    markup_fixed: '',
                });
                toast.success('Pricing rules updated successfully');
                setAlertDialog({
                    show: true,
                    title: 'Success',
                    description: 'Markup updated successfully for all matching countries',
                    variant: 'default',
                });
            },
            onError: (errors) => {
                setAlertDialog({
                    show: true,
                    title: 'Error',
                    description: 'Error updating markup: ' + Object.values(errors).join(', '),
                    variant: 'destructive',
                });
            },
            onFinish: () => {
                setIsSubmitting(false);
            },
        });
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: dashboard().url },
        { title: 'Pricing Rules', href: '/admin/pricing' },
    ];

    // Whose price list the base cost comes from. Named, because an admin
    // picked this provider and is looking at its rates; neutral before a
    // provider is locked, when there is no name to give honestly.
    const { provider, locked } = useTelephony();
    const costSource = locked ? providerLabel(provider) : 'your telephony provider';

    const helpSections = [
        {
            title: 'What are Pricing Rules?',
            content: `Pricing Rules control how much you charge customers for voice calls, SMS messages, and phone number rentals. You set the base cost (what ${costSource} charges you) and add a markup percentage to earn profit.`,
        },
        {
            title: 'Service Types Explained',
            content: '• Voice Call: Charged per minute (rounded up). Example: 90-second call = 2 minutes.\n• SMS: Charged per segment (160 characters each). Example: 200-character message = 2 segments.\n• Phone Number: Monthly rental fee charged at the start of each month.',
        },
        {
            title: 'Base Cost vs Customer Charge',
            content: `Base Cost = What ${costSource} charges you (your expense).\nMarkup % = Your profit margin (e.g., 25%).\nCustomer Charge = Base Cost + (Base Cost × Markup %).\n\nExample: $0.10 base + 25% markup = $0.125 customer charge. Your profit = $0.025 per transaction.`,
        },
        {
            title: 'Markup Types',
            content: 'Percentage: Most common. Scales with base cost changes (e.g., 20% markup).\nFixed: Add fixed amount (e.g., +$0.02).\nHybrid: Combine both (e.g., 15% + $0.01).',
        },
        {
            title: 'Bulk Update Feature',
            content: 'Instead of editing each country one-by-one, use "Bulk Update Markup" to set the same markup for ALL countries in a service type (calls/SMS/phone numbers) at once. This saves time when you want consistent margins across all regions.',
        },
        {
            title: 'Importing Pricing',
            content: `To import pricing from ${costSource} for all countries, use the Artisan command:\n\nphp artisan pricing:fetch {service_type} --markup-type={type} --markup-value={value}\n\nExample: php artisan pricing:fetch call --markup-type=percentage --markup-value=25\n\nThis fetches all 200+ countries automatically.`,
        },
        {
            title: 'Customer Tiers',
            content: 'Standard: Regular customers (default pricing).\nPremium: VIP customers with higher margins.\nEnterprise: Large-volume customers with custom pricing.\n\nYou can set different markups for the same country based on customer tier.',
        },
        {
            title: 'Active vs Inactive Rules',
            content: 'Active rules are used for billing calculations. Inactive rules are ignored but not deleted - useful for temporarily disabling pricing without losing the configuration.',
        },
        {
            title: 'Real Example',
            content: 'US Voice Call: Base $0.0085/min, 20% markup → Customer pays $0.0102/min.\n3-minute call: Base $0.0255, Charged $0.0306, Profit $0.0051.\n\n1,000 calls × 3 min average = $30.60 revenue, $25.50 cost = $5.10 profit (20% margin).',
        },
    ];

    const getServiceIcon = (type: string) => {
        switch (type) {
            case 'call':
                return <Phone className="h-4 w-4" />;
            case 'sms':
                return <MessageSquare className="h-4 w-4" />;
            case 'phone_number':
                return <Globe className="h-4 w-4" />;
            default:
                return <DollarSign className="h-4 w-4" />;
        }
    };

    const getServiceLabel = (type: string) => {
        switch (type) {
            case 'call':
                return 'Voice Call';
            case 'sms':
                return 'SMS';
            case 'phone_number':
                return 'Phone Number';
            default:
                return type;
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Pricing Rules" />

            <div className="mx-auto max-w-7xl space-y-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Pricing Rules"
                        description="Manage pricing and profit margins for services"
                    />
                    <div className="flex items-center gap-2">
                        <PageHelp title="Pricing Rules Help" sections={helpSections} />
                        {selectedIds.length > 0 && (
                            <Button 
                                variant="destructive" 
                                onClick={handleBulkDelete}
                            >
                                Delete Selected ({selectedIds.length})
                            </Button>
                        )}
                        <Button 
                            variant="outline" 
                            onClick={() => setShowBulkUpdate(true)}
                        >
                            <RefreshCw className="mr-2 h-4 w-4" />
                            Bulk Update Markup
                        </Button>
                        <Button asChild>
                            <Link href="/admin/pricing/create">
                                <Plus className="mr-2 h-4 w-4" />
                                Add Pricing Rule
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* Filters */}
                <Card>
                    <CardContent className="pt-6">
                        <div className="grid gap-4 md:grid-cols-4">
                            <div className="space-y-2">
                                <Label>Service Type</Label>
                                <Select
                                    value={filters.service_type}
                                    onValueChange={(value) => handleFilterChange('service_type', value)}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Services</SelectItem>
                                        <SelectItem value="call">Voice Call</SelectItem>
                                        <SelectItem value="sms">SMS</SelectItem>
                                        <SelectItem value="phone_number">Phone Number</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-2">
                                <Label>Status</Label>
                                <Select
                                    value={filters.active}
                                    onValueChange={(value) => handleFilterChange('active', value)}
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Status</SelectItem>
                                        <SelectItem value="active">Active</SelectItem>
                                        <SelectItem value="inactive">Inactive</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-2 md:col-span-2">
                                <Label>Search Country</Label>
                                <Input
                                    placeholder="Search by country name or code..."
                                    value={filters.search}
                                    onChange={(e) => handleFilterChange('search', e.target.value)}
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Statistics Cards */}
                {stats && (
                    <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">
                                    Total Rules
                                </CardTitle>
                                <DollarSign className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{stats.total_rules}</div>
                                <p className="text-xs text-muted-foreground">
                                    {stats.active_rules} active
                                </p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">
                                    Call Pricing
                                </CardTitle>
                                <Phone className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{stats.by_service.call}</div>
                                <p className="text-xs text-muted-foreground">
                                    Active call rules
                                </p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">
                                    SMS Pricing
                                </CardTitle>
                                <MessageSquare className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{stats.by_service.sms}</div>
                                <p className="text-xs text-muted-foreground">
                                    Active SMS rules
                                </p>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">
                                    Phone Numbers
                                </CardTitle>
                                <Globe className="h-4 w-4 text-muted-foreground" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{stats.by_service.phone_number}</div>
                                <p className="text-xs text-muted-foreground">
                                    Active phone rules
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                )}

                <Card className="overflow-x-auto">
                    <CardHeader>
                        <CardTitle>Active Pricing Rules</CardTitle>
                        <CardDescription>
                            Configure base costs, markup percentages, and selling prices
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="overflow-x-auto">
                        {rulesArray.length === 0 ? (
                            <div className="flex items-center justify-center py-12 text-center">
                                <div>
                                    <DollarSign className="mx-auto h-12 w-12 text-muted-foreground" />
                                    <p className="mt-4 text-lg font-medium">
                                        No pricing rules configured
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        Add your first pricing rule to start managing costs
                                    </p>
                                    <Button asChild className="mt-4">
                                        <Link href="/admin/pricing/create">
                                            <Plus className="mr-2 h-4 w-4" />
                                            Add Pricing Rule
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        ) : (
                            <div className="min-w-full">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-[50px]">
                                                <Checkbox
                                                    checked={selectedIds.length > 0 && rulesArray.every(country => 
                                                        (!country.services.call?.id || selectedIds.includes(country.services.call.id)) &&
                                                        (!country.services.sms?.id || selectedIds.includes(country.services.sms.id)) &&
                                                        (!country.services.phone_number?.id || selectedIds.includes(country.services.phone_number.id))
                                                    )}
                                                    onCheckedChange={handleSelectAll}
                                                />
                                            </TableHead>
                                            <TableHead className="w-[200px]">Country</TableHead>
                                            <TableHead className="w-[250px]">Voice Call</TableHead>
                                            <TableHead className="w-[250px]">SMS</TableHead>
                                            <TableHead className="w-[250px]">Phone Number</TableHead>
                                            <TableHead className="w-[150px] text-right">Actions</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                <TableBody>
                                    {rulesArray.map((country) => {
                                        const renderServicePricing = (service?: ServicePricing) => {
                                            if (!service) {
                                                return <div className="text-sm text-muted-foreground">-</div>;
                                            }

                                            const baseCost = typeof service.base_cost === 'string' 
                                                ? parseFloat(service.base_cost) 
                                                : service.base_cost;
                                            const markupValue = typeof service.markup_value === 'string' 
                                                ? parseFloat(service.markup_value) 
                                                : service.markup_value;
                                            const markupFixed = service.markup_fixed 
                                                ? (typeof service.markup_fixed === 'string' 
                                                    ? parseFloat(service.markup_fixed) 
                                                    : service.markup_fixed)
                                                : 0;
                                            const minimumCharge = typeof service.minimum_charge === 'string' 
                                                ? parseFloat(service.minimum_charge) 
                                                : service.minimum_charge;
                                            
                                            // Check if price is $0 - show "Not Available"
                                            if (baseCost === 0) {
                                                return (
                                                    <div className="text-sm text-muted-foreground italic">
                                                        Not Available
                                                    </div>
                                                );
                                            }
                                            
                                            // Calculate customer charge
                                            let customerCharge = baseCost;
                                            if (service.markup_type === 'percentage') {
                                                customerCharge = baseCost * (1 + markupValue / 100);
                                            } else if (service.markup_type === 'fixed') {
                                                customerCharge = baseCost + markupValue;
                                            } else if (service.markup_type === 'hybrid') {
                                                customerCharge = baseCost * (1 + markupValue / 100) + markupFixed;
                                            }
                                            
                                            customerCharge = Math.max(customerCharge, minimumCharge);
                                            const profit = customerCharge - baseCost;
                                            const profitMargin = baseCost > 0 ? ((profit / baseCost) * 100).toFixed(1) : '0';

                                            return (
                                                <div className="space-y-1">
                                                    <div className="flex items-center gap-2">
                                                        <span className="text-sm font-medium">${customerCharge.toFixed(4)}</span>
                                                        {service.is_active ? (
                                                            <Badge variant="default" className="h-5 text-xs">Active</Badge>
                                                        ) : (
                                                            <Badge variant="secondary" className="h-5 text-xs">Inactive</Badge>
                                                        )}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        Base: ${baseCost.toFixed(4)}
                                                    </div>
                                                    <div className="text-xs text-green-600">
                                                        ${profit.toFixed(4)} ({profitMargin}%)
                                                    </div>
                                                </div>
                                            );
                                        };

                                        return (
                                            <TableRow key={country.country_code}>
                                                <TableCell>
                                                    <Checkbox
                                                        checked={[
                                                            country.services.call?.id,
                                                            country.services.sms?.id,
                                                            country.services.phone_number?.id
                                                        ].filter(Boolean).every(id => selectedIds.includes(id as number))}
                                                        onCheckedChange={(checked) => {
                                                            const countryServiceIds = [
                                                                country.services.call?.id,
                                                                country.services.sms?.id,
                                                                country.services.phone_number?.id
                                                            ].filter(Boolean) as number[];
                                                            
                                                            if (checked) {
                                                                setSelectedIds([...selectedIds, ...countryServiceIds.filter(id => !selectedIds.includes(id))]);
                                                            } else {
                                                                setSelectedIds(selectedIds.filter(id => !countryServiceIds.includes(id)));
                                                            }
                                                        }}
                                                    />
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center gap-2">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            className="h-8 w-8 p-0"
                                                            onClick={() => handleTogglePin(country.country_code, country.is_pinned)}
                                                            title={country.is_pinned ? "Unpin country" : "Pin country to top"}
                                                        >
                                                            {country.is_pinned ? (
                                                                <Pin className="h-4 w-4 fill-current text-blue-600" />
                                                            ) : (
                                                                <PinOff className="h-4 w-4 text-muted-foreground" />
                                                            )}
                                                        </Button>
                                                        <Globe className="h-4 w-4 text-muted-foreground" />
                                                        <div>
                                                            <div className="flex items-center gap-2">
                                                                <span className="font-medium">{country.country_name}</span>
                                                                {country.is_pinned && (
                                                                    <Badge variant="secondary" className="h-5 text-xs">Pinned</Badge>
                                                                )}
                                                            </div>
                                                            <div className="text-xs text-muted-foreground">{country.country_code}</div>
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    {renderServicePricing(country.services.call)}
                                                </TableCell>
                                                <TableCell>
                                                    {renderServicePricing(country.services.sms)}
                                                </TableCell>
                                                <TableCell>
                                                    {renderServicePricing(country.services.phone_number)}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <div className="flex items-center justify-end gap-2">
                                                        {(() => {
                                                            // Get the first available service ID
                                                            const firstServiceId = country.services.call?.id || 
                                                                                 country.services.sms?.id || 
                                                                                 country.services.phone_number?.id;
                                                            
                                                            return firstServiceId ? (
                                                                <>
                                                                    <Button variant="outline" size="sm" asChild>
                                                                        <Link href={`/admin/pricing/${firstServiceId}/edit`}>
                                                                            <Pencil className="h-4 w-4" />
                                                                        </Link>
                                                                    </Button>
                                                                    <Button 
                                                                        variant="destructive" 
                                                                        size="sm"
                                                                        onClick={() => handleDelete(firstServiceId)}
                                                                    >
                                                                        <Trash2 className="h-4 w-4" />
                                                                    </Button>
                                                                </>
                                                            ) : null;
                                                        })()}
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        );
                                    })}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </CardContent>
                    <CardContent className="pt-0">
                        <div className="flex flex-col gap-4">
                            <div className="flex items-center justify-between">
                                <div className="text-sm text-muted-foreground">
                                    Showing {((pricingRules.current_page - 1) * pricingRules.per_page) + 1} to {Math.min(pricingRules.current_page * pricingRules.per_page, pricingRules.total)} of {pricingRules.total} results
                                </div>
                                {pricingRules.last_page > 1 && (
                                    <div className="flex items-center gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            disabled={pricingRules.current_page === 1}
                                            onClick={() => {
                                                const params = new URLSearchParams(window.location.search);
                                                params.set('page', String(pricingRules.current_page - 1));
                                                router.get(`/admin/pricing?${params.toString()}`);
                                            }}
                                        >
                                            Previous
                                        </Button>
                                        <div className="text-sm">
                                            Page {pricingRules.current_page} of {pricingRules.last_page}
                                        </div>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            disabled={pricingRules.current_page === pricingRules.last_page}
                                            onClick={() => {
                                                const params = new URLSearchParams(window.location.search);
                                                params.set('page', String(pricingRules.current_page + 1));
                                                router.get(`/admin/pricing?${params.toString()}`);
                                            }}
                                        >
                                            Next
                                        </Button>
                                    </div>
                                )}
                            </div>
                            
                            <div className="flex items-center gap-4 justify-between">
                                <div className="flex items-center gap-2">
                                    <Label htmlFor="per-page" className="text-sm whitespace-nowrap">Per page:</Label>
                                    <Select
                                        value={String(pricingRules.per_page)}
                                        onValueChange={(value) => {
                                            const params = new URLSearchParams(window.location.search);
                                            params.set('per_page', value);
                                            params.set('page', '1'); // Reset to first page
                                            router.get(`/admin/pricing?${params.toString()}`);
                                        }}
                                    >
                                        <SelectTrigger className="w-[100px]">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="10">10</SelectItem>
                                            <SelectItem value="20">20</SelectItem>
                                            <SelectItem value="50">50</SelectItem>
                                            <SelectItem value="100">100</SelectItem>
                                            <SelectItem value="9999">Show All</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                {pricingRules.last_page > 1 && (
                                    <div className="flex items-center gap-2">
                                        <Label htmlFor="jump-to-page" className="text-sm whitespace-nowrap">Go to page:</Label>
                                        <Input
                                            id="jump-to-page"
                                            type="number"
                                            min="1"
                                            max={pricingRules.last_page}
                                            placeholder="Page #"
                                            className="w-[100px]"
                                            value={jumpToPage}
                                            onChange={(e) => setJumpToPage(e.target.value)}
                                            onKeyDown={(e) => {
                                                if (e.key === 'Enter') {
                                                    const page = parseInt(jumpToPage);
                                                    if (page >= 1 && page <= pricingRules.last_page) {
                                                        const params = new URLSearchParams(window.location.search);
                                                        params.set('page', String(page));
                                                        router.get(`/admin/pricing?${params.toString()}`);
                                                        setJumpToPage('');
                                                    }
                                                }
                                            }}
                                        />
                                        <Button
                                            size="sm"
                                            onClick={() => {
                                                const page = parseInt(jumpToPage);
                                                if (page >= 1 && page <= pricingRules.last_page) {
                                                    const params = new URLSearchParams(window.location.search);
                                                    params.set('page', String(page));
                                                    router.get(`/admin/pricing?${params.toString()}`);
                                                    setJumpToPage('');
                                                }
                                            }}
                                            disabled={!jumpToPage || parseInt(jumpToPage) < 1 || parseInt(jumpToPage) > pricingRules.last_page}
                                        >
                                            Go
                                        </Button>
                                    </div>
                                )}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Bulk Update Modal */}
                <Dialog open={showBulkUpdate} onOpenChange={setShowBulkUpdate}>
                    <DialogContent className="sm:max-w-[500px]">
                        <DialogHeader>
                            <DialogTitle>Bulk Update Markup</DialogTitle>
                            <DialogDescription>
                                Set the same markup value for ALL countries in a specific service type.
                                This will update multiple pricing rules at once.
                            </DialogDescription>
                        </DialogHeader>
                        
                        <div className="space-y-4 py-4">
                            <div className="space-y-2">
                                <Label htmlFor="service_type">Service Type</Label>
                                <Select
                                    value={bulkUpdateData.service_type}
                                    onValueChange={(value: 'call' | 'sms' | 'phone_number' | 'all') =>
                                        setBulkUpdateData({ ...bulkUpdateData, service_type: value })
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Services (Voice + SMS + Phone)</SelectItem>
                                        <SelectItem value="call">Voice Call</SelectItem>
                                        <SelectItem value="sms">SMS</SelectItem>
                                        <SelectItem value="phone_number">Phone Number</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="markup_type">Markup Type</Label>
                                <Select
                                    value={bulkUpdateData.markup_type}
                                    onValueChange={(value: 'percentage' | 'fixed' | 'hybrid') =>
                                        setBulkUpdateData({ ...bulkUpdateData, markup_type: value })
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="percentage">Percentage (%)</SelectItem>
                                        <SelectItem value="fixed">Fixed Amount ($)</SelectItem>
                                        <SelectItem value="hybrid">Hybrid (% + $)</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="markup_value">
                                    {bulkUpdateData.markup_type === 'percentage' ? 'Markup Percentage' : 'Markup Value'}
                                </Label>
                                <Input
                                    id="markup_value"
                                    type="number"
                                    step="0.01"
                                    placeholder={bulkUpdateData.markup_type === 'percentage' ? '25' : '0.02'}
                                    value={bulkUpdateData.markup_value}
                                    onChange={(e) =>
                                        setBulkUpdateData({ ...bulkUpdateData, markup_value: e.target.value })
                                    }
                                />
                                <p className="text-xs text-muted-foreground">
                                    {bulkUpdateData.markup_type === 'percentage' 
                                        ? 'Example: 25 = 25% markup'
                                        : 'Example: 0.02 = $0.02 markup'}
                                </p>
                            </div>

                            {bulkUpdateData.markup_type === 'hybrid' && (
                                <div className="space-y-2">
                                    <Label htmlFor="markup_fixed">Fixed Amount ($)</Label>
                                    <Input
                                        id="markup_fixed"
                                        type="number"
                                        step="0.01"
                                        placeholder="0.01"
                                        value={bulkUpdateData.markup_fixed}
                                        onChange={(e) =>
                                            setBulkUpdateData({ ...bulkUpdateData, markup_fixed: e.target.value })
                                        }
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Additional fixed amount for hybrid markup
                                    </p>
                                </div>
                            )}

                            <div className="rounded-lg bg-muted p-4">
                                <p className="text-sm font-medium">Preview:</p>
                                <p className="text-sm text-muted-foreground mt-1">
                                    This will update{' '}
                                    <span className="font-semibold text-foreground">
                                        {(() => {
                                            let count = 0;
                                            rulesArray.forEach(country => {
                                                if (bulkUpdateData.service_type === 'all') {
                                                    if (country.services.call) count++;
                                                    if (country.services.sms) count++;
                                                    if (country.services.phone_number) count++;
                                                } else {
                                                    if (country.services[bulkUpdateData.service_type]) count++;
                                                }
                                            });
                                            return count;
                                        })()}
                                    </span>{' '}
                                    pricing rule(s) for <span className="font-semibold text-foreground">
                                        {bulkUpdateData.service_type === 'all' ? 'all services' : bulkUpdateData.service_type}
                                    </span>.
                                </p>
                            </div>
                        </div>

                        <DialogFooter>
                            <Button variant="outline" onClick={() => setShowBulkUpdate(false)} disabled={isSubmitting}>
                                Cancel
                            </Button>
                            <Button onClick={handleBulkUpdate} disabled={isSubmitting}>
                                {isSubmitting ? 'Updating...' : 'Update All Countries'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                {/* Alert Dialog */}
                <AlertDialog open={alertDialog.show} onOpenChange={(open) => setAlertDialog({ ...alertDialog, show: open })}>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>{alertDialog.title}</AlertDialogTitle>
                            <AlertDialogDescription>
                                {alertDialog.description}
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogAction onClick={() => setAlertDialog({ ...alertDialog, show: false })}>
                                OK
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>

                {/* Confirmation Dialog */}
                <AlertDialog open={confirmDialog.show} onOpenChange={(open) => setConfirmDialog({ ...confirmDialog, show: open })}>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>{confirmDialog.title}</AlertDialogTitle>
                            <AlertDialogDescription>
                                {confirmDialog.description}
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel onClick={() => setConfirmDialog({ ...confirmDialog, show: false })}>
                                Cancel
                            </AlertDialogCancel>
                            <AlertDialogAction onClick={() => {
                                confirmDialog.onConfirm();
                                setConfirmDialog({ ...confirmDialog, show: false });
                            }}>
                                Continue
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </div>
        </AppLayout>
    );
}
