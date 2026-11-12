import AppLayout from '@/layouts/app-layout';
import { useTranslation } from 'react-i18next';
import { useFormatCurrency } from '@/hooks/use-currency';
import Heading from '@/components/heading';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, useForm, router } from '@inertiajs/react';
import { Plus, Trash2, Pencil } from 'lucide-react';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { useState } from 'react';

interface PricingTier {
    id: number;
    credits: number;
    price: number;
    per_credit: number;
    popular: boolean;
    icon: string | null;
    savings: string | null;
    order: number;
    is_active: boolean;
}

interface PricingProps {
    pricing: PricingTier[];
}

export default function Pricing({ pricing = [] }: PricingProps) {
  const { t } = useTranslation();
  const { formatCurrency } = useFormatCurrency();
    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('common.dashboard'), href: dashboard().url },
        { title: t('admin.theme.theme'), href: '/admin/theme' },
        { title: t('admin.theme.pricing'), href: '/admin/theme/pricing' },
    ];

    const [showNewForm, setShowNewForm] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);

    const newForm = useForm({
        credits: '',
        price: '',
        per_credit: '',
        popular: false,
        icon: '',
        savings: '',
        order: pricing.length + 1,
        is_active: true,
    });

    const editForm = useForm({
        credits: '',
        price: '',
        per_credit: '',
        popular: false,
        icon: '',
        savings: '',
        order: 0,
        is_active: true,
    });

    const handleCreate = (e: React.FormEvent) => {
        e.preventDefault();
        newForm.post('/admin/theme/pricing', {
            onSuccess: () => {
                newForm.reset();
                setShowNewForm(false);
            },
        });
    };

    const handleEdit = (tier: PricingTier) => {
        setEditingId(tier.id);
        editForm.setData({
            credits: tier.credits.toString(),
            price: tier.price.toString(),
            per_credit: tier.per_credit.toString(),
            popular: tier.popular,
            icon: tier.icon || '',
            savings: tier.savings || '',
            order: tier.order,
            is_active: tier.is_active,
        });
    };

    const handleUpdate = (e: React.FormEvent) => {
        e.preventDefault();
        if (editingId) {
            editForm.put(`/admin/theme/pricing/${editingId}`, {
                onSuccess: () => {
                    setEditingId(null);
                    editForm.reset();
                },
            });
        }
    };

    const handleCancelEdit = () => {
        setEditingId(null);
        editForm.reset();
    };

    const handleDelete = (id: number) => {
        if (confirm('Are you sure you want to delete this pricing tier?')) {
            router.delete(`/admin/theme/pricing/${id}`);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('admin.theme.pricing_section')} />

            <div className="mx-auto max-w-7xl space-y-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title={t('admin.theme.pricing_section')}
                        description={t('admin.theme.manage_pricing_tiers_displayed_on_your_site')}
                    />
                    <Button onClick={() => setShowNewForm(!showNewForm)}>
                        <Plus className="mr-2 h-4 w-4" />
                        Add Pricing Tier
                    </Button>
                </div>

                {showNewForm && (
                    <Card>
                        <CardContent className="pt-6">
                            <form onSubmit={handleCreate} className="space-y-4">
                                <div className="grid gap-4 md:grid-cols-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="credits">Credits *</Label>
                                        <Input
                                            id="credits"
                                            type="text"
                                            value={newForm.data.credits}
                                            onChange={(e) => newForm.setData('credits', e.target.value)}
                                            placeholder="100"
                                        />
                                        {newForm.errors.credits && (
                                            <p className="text-sm text-red-600">{newForm.errors.credits}</p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="price">Price *</Label>
                                        <Input
                                            id="price"
                                            type="text"
                                            value={newForm.data.price}
                                            onChange={(e) => newForm.setData('price', e.target.value)}
                                            placeholder="$10"
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="per_credit">Per Credit *</Label>
                                        <Input
                                            id="per_credit"
                                            type="text"
                                            value={newForm.data.per_credit}
                                            onChange={(e) => newForm.setData('per_credit', e.target.value)}
                                            placeholder="$0.10"
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="icon">Icon</Label>
                                        <Input
                                            id="icon"
                                            value={newForm.data.icon}
                                            onChange={(e) => newForm.setData('icon', e.target.value)}
                                            placeholder="💎"
                                        />
                                    </div>
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="savings">Savings Text</Label>
                                    <Input
                                        id="savings"
                                        value={newForm.data.savings}
                                        onChange={(e) => newForm.setData('savings', e.target.value)}
                                        placeholder="Save 20%"
                                    />
                                </div>
                                <div className="flex gap-4">
                                    <div className="flex items-center space-x-2">
                                        <Checkbox
                                            id="popular"
                                            checked={newForm.data.popular}
                                            onCheckedChange={(checked) => newForm.setData('popular', checked as boolean)}
                                        />
                                        <Label htmlFor="popular">Mark as Popular</Label>
                                    </div>
                                    <div className="flex items-center space-x-2">
                                        <Checkbox
                                            id="is_active"
                                            checked={newForm.data.is_active}
                                            onCheckedChange={(checked) => newForm.setData('is_active', checked as boolean)}
                                        />
                                        <Label htmlFor="is_active">Active</Label>
                                    </div>
                                </div>
                                <div className="flex justify-end gap-2">
                                    <Button type="button" variant="outline" onClick={() => setShowNewForm(false)}>
                                        Cancel
                                    </Button>
                                    <Button type="submit" disabled={newForm.processing}>
                                        {newForm.processing ? 'Creating...' : 'Create Pricing Tier'}
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardContent className="pt-6">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Icon</TableHead>
                                    <TableHead>Credits</TableHead>
                                    <TableHead>Price</TableHead>
                                    <TableHead>Per Credit</TableHead>
                                    <TableHead>Savings</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {pricing.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={7} className="text-center text-muted-foreground">
                                            No pricing tiers yet. Click "Add Pricing Tier" to create one.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    pricing.map((tier) => (
                                        editingId === tier.id ? (
                                            // Edit form row
                                            <TableRow key={tier.id} className="bg-blue-50/50">
                                                <TableCell colSpan={7}>
                                                    <form onSubmit={handleUpdate} className="space-y-4 py-2">
                                                        <div className="grid gap-4 md:grid-cols-4">
                                                            <div className="space-y-2">
                                                                <Label htmlFor={`edit-credits-${tier.id}`}>Credits *</Label>
                                                                <Input
                                                                    id={`edit-credits-${tier.id}`}
                                                                    type="text"
                                                                    value={editForm.data.credits}
                                                                    onChange={(e) => editForm.setData('credits', e.target.value)}
                                                                    placeholder="100"
                                                                />
                                                                {editForm.errors.credits && (
                                                                    <p className="text-sm text-red-600">{editForm.errors.credits}</p>
                                                                )}
                                                            </div>
                                                            <div className="space-y-2">
                                                                <Label htmlFor={`edit-price-${tier.id}`}>Price *</Label>
                                                                <Input
                                                                    id={`edit-price-${tier.id}`}
                                                                    type="text"
                                                                    value={editForm.data.price}
                                                                    onChange={(e) => editForm.setData('price', e.target.value)}
                                                                    placeholder="$10"
                                                                />
                                                            </div>
                                                            <div className="space-y-2">
                                                                <Label htmlFor={`edit-per-credit-${tier.id}`}>Per Credit *</Label>
                                                                <Input
                                                                    id={`edit-per-credit-${tier.id}`}
                                                                    type="text"
                                                                    value={editForm.data.per_credit}
                                                                    onChange={(e) => editForm.setData('per_credit', e.target.value)}
                                                                    placeholder="$0.10"
                                                                />
                                                            </div>
                                                            <div className="space-y-2">
                                                                <Label htmlFor={`edit-icon-${tier.id}`}>Icon</Label>
                                                                <Input
                                                                    id={`edit-icon-${tier.id}`}
                                                                    value={editForm.data.icon}
                                                                    onChange={(e) => editForm.setData('icon', e.target.value)}
                                                                    placeholder="💎"
                                                                />
                                                            </div>
                                                        </div>
                                                        <div className="grid gap-4 md:grid-cols-2">
                                                            <div className="space-y-2">
                                                                <Label htmlFor={`edit-savings-${tier.id}`}>Savings Text</Label>
                                                                <Input
                                                                    id={`edit-savings-${tier.id}`}
                                                                    value={editForm.data.savings}
                                                                    onChange={(e) => editForm.setData('savings', e.target.value)}
                                                                    placeholder="Save 20%"
                                                                />
                                                            </div>
                                                            <div className="space-y-2">
                                                                <Label htmlFor={`edit-order-${tier.id}`}>Order</Label>
                                                                <Input
                                                                    id={`edit-order-${tier.id}`}
                                                                    type="number"
                                                                    value={editForm.data.order}
                                                                    onChange={(e) => editForm.setData('order', parseInt(e.target.value))}
                                                                />
                                                            </div>
                                                        </div>
                                                        <div className="flex gap-4">
                                                            <div className="flex items-center space-x-2">
                                                                <Checkbox
                                                                    id={`edit-popular-${tier.id}`}
                                                                    checked={editForm.data.popular}
                                                                    onCheckedChange={(checked) => editForm.setData('popular', checked as boolean)}
                                                                />
                                                                <Label htmlFor={`edit-popular-${tier.id}`}>Mark as Popular</Label>
                                                            </div>
                                                            <div className="flex items-center space-x-2">
                                                                <Checkbox
                                                                    id={`edit-active-${tier.id}`}
                                                                    checked={editForm.data.is_active}
                                                                    onCheckedChange={(checked) => editForm.setData('is_active', checked as boolean)}
                                                                />
                                                                <Label htmlFor={`edit-active-${tier.id}`}>Active</Label>
                                                            </div>
                                                        </div>
                                                        <div className="flex justify-end gap-2">
                                                            <Button type="button" variant="outline" onClick={handleCancelEdit}>
                                                                Cancel
                                                            </Button>
                                                            <Button type="submit" disabled={editForm.processing}>
                                                                {editForm.processing ? 'Updating...' : 'Update Pricing Tier'}
                                                            </Button>
                                                        </div>
                                                    </form>
                                                </TableCell>
                                            </TableRow>
                                        ) : (
                                            // Normal row display
                                            <TableRow key={tier.id}>
                                                <TableCell className="text-2xl">{tier.icon}</TableCell>
                                                <TableCell className="font-semibold">{tier.credits}</TableCell>
                                                <TableCell>{formatCurrency(tier.price)}</TableCell>
                                                <TableCell>{formatCurrency(tier.per_credit)}</TableCell>
                                                <TableCell>{tier.savings || '-'}</TableCell>
                                                <TableCell>
                                                    <div className="flex gap-2">
                                                        <Badge variant={tier.is_active ? 'default' : 'secondary'}>
                                                            {tier.is_active ? 'Active' : 'Inactive'}
                                                        </Badge>
                                                        {tier.popular && <Badge variant="outline">Popular</Badge>}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <div className="flex justify-end gap-2">
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => handleEdit(tier)}
                                                        >
                                                            <Pencil className="h-4 w-4" />
                                                        </Button>
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => handleDelete(tier.id)}
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        )
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
