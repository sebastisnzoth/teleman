import AppLayout from '@/layouts/app-layout';
import { useTranslation } from 'react-i18next';
import Heading from '@/components/heading';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Head, useForm, router } from '@inertiajs/react';
import { Plus, Trash2, GripVertical } from 'lucide-react';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { useState } from 'react';

interface Stat {
    id: number;
    number: string;
    label: string;
    icon: string;
    order: number;
    is_active: boolean;
}

interface StatsProps {
    stats: Stat[];
}

export default function Stats({ stats = [] }: StatsProps) {
  const { t } = useTranslation();
    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('common.dashboard'), href: dashboard().url },
        { title: t('admin.theme.theme'), href: '/admin/theme' },
        { title: t('admin.theme.stats'), href: '/admin/theme/stats' },
    ];

    const [editingId, setEditingId] = useState<number | null>(null);
    const [showNewForm, setShowNewForm] = useState(false);

    const newForm = useForm({
        number: '',
        label: '',
        icon: '',
        order: stats.length + 1,
        is_active: true,
    });

    const handleCreate = (e: React.FormEvent) => {
        e.preventDefault();
        newForm.post('/admin/theme/stats', {
            onSuccess: () => {
                newForm.reset();
                setShowNewForm(false);
            },
        });
    };

    const handleDelete = (id: number) => {
        if (confirm('Are you sure you want to delete this stat?')) {
            router.delete(`/admin/theme/stats/${id}`);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('admin.theme.stats_section')} />

            <div className="mx-auto max-w-7xl space-y-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title={t('admin.theme.stats_section')}
                        description={t('admin.theme.manage_statistics_displayed_on_your_site')}
                    />
                    <Button onClick={() => setShowNewForm(!showNewForm)}>
                        <Plus className="mr-2 h-4 w-4" />
                        Add Stat
                    </Button>
                </div>

                {showNewForm && (
                    <Card>
                        <CardContent className="pt-6">
                            <form onSubmit={handleCreate} className="space-y-4">
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="number">Number *</Label>
                                        <Input
                                            id="number"
                                            value={newForm.data.number}
                                            onChange={(e) => newForm.setData('number', e.target.value)}
                                            placeholder="10,000+"
                                        />
                                        {newForm.errors.number && (
                                            <p className="text-sm text-red-600">{newForm.errors.number}</p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="label">Label *</Label>
                                        <Input
                                            id="label"
                                            value={newForm.data.label}
                                            onChange={(e) => newForm.setData('label', e.target.value)}
                                            placeholder="Happy Customers"
                                        />
                                        {newForm.errors.label && (
                                            <p className="text-sm text-red-600">{newForm.errors.label}</p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="icon">Icon *</Label>
                                        <Input
                                            id="icon"
                                            value={newForm.data.icon}
                                            onChange={(e) => newForm.setData('icon', e.target.value)}
                                            placeholder="👥"
                                        />
                                        {newForm.errors.icon && (
                                            <p className="text-sm text-red-600">{newForm.errors.icon}</p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="order">Order *</Label>
                                        <Input
                                            id="order"
                                            type="number"
                                            value={newForm.data.order}
                                            onChange={(e) => newForm.setData('order', parseInt(e.target.value))}
                                        />
                                    </div>
                                </div>
                                <div className="flex justify-end gap-2">
                                    <Button type="button" variant="outline" onClick={() => setShowNewForm(false)}>
                                        Cancel
                                    </Button>
                                    <Button type="submit" disabled={newForm.processing}>
                                        {newForm.processing ? 'Creating...' : 'Create Stat'}
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
                                    <TableHead className="w-12"></TableHead>
                                    <TableHead>Icon</TableHead>
                                    <TableHead>Number</TableHead>
                                    <TableHead>Label</TableHead>
                                    <TableHead>Order</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {stats.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={7} className="text-center text-muted-foreground">
                                            No stats yet. Click "Add Stat" to create one.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    stats.map((stat) => (
                                        <TableRow key={stat.id}>
                                            <TableCell>
                                                <GripVertical className="h-4 w-4 text-muted-foreground" />
                                            </TableCell>
                                            <TableCell className="text-2xl">{stat.icon}</TableCell>
                                            <TableCell className="font-semibold">{stat.number}</TableCell>
                                            <TableCell>{stat.label}</TableCell>
                                            <TableCell>{stat.order}</TableCell>
                                            <TableCell>
                                                <Badge variant={stat.is_active ? 'default' : 'secondary'}>
                                                    {stat.is_active ? 'Active' : 'Inactive'}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => handleDelete(stat.id)}
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </TableCell>
                                        </TableRow>
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
