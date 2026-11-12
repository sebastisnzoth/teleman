import AppLayout from '@/layouts/app-layout';
import Heading from '@/components/heading';
import { useTranslation } from 'react-i18next';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Head, useForm, router } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { useState } from 'react';

interface UseCase {
    id: number;
    title: string;
    description: string;
    icon: string;
    color: string | null;
    items: string[] | null;
    order: number;
    is_active: boolean;
}

interface UseCasesProps {
    useCases: UseCase[];
}

export default function UseCases({ useCases = [] }: UseCasesProps) {
  const { t } = useTranslation();
    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('common.dashboard'), href: dashboard().url },
        { title: t('admin.theme.theme'), href: '/admin/theme' },
        { title: t('admin.theme.use_cases'), href: '/admin/theme/use-cases' },
    ];

    const [showNewForm, setShowNewForm] = useState(false);

    const newForm = useForm({
        title: '',
        description: '',
        icon: '',
        color: '',
        order: useCases.length + 1,
        is_active: true,
    });

    const handleCreate = (e: React.FormEvent) => {
        e.preventDefault();
        newForm.post('/admin/theme/use-cases', {
            onSuccess: () => {
                newForm.reset();
                setShowNewForm(false);
            },
        });
    };

    const handleDelete = (id: number) => {
        if (confirm('Are you sure you want to delete this use case?')) {
            router.delete(`/admin/theme/use-cases/${id}`);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('admin.theme.use_cases_section')} />

            <div className="mx-auto max-w-7xl space-y-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title={t('admin.theme.use_cases_section')}
                        description={t('admin.theme.manage_use_cases_displayed_on_your_site')}
                    />
                    <Button onClick={() => setShowNewForm(!showNewForm)}>
                        <Plus className="mr-2 h-4 w-4" />
                        Add Use Case
                    </Button>
                </div>

                {showNewForm && (
                    <Card>
                        <CardContent className="pt-6">
                            <form onSubmit={handleCreate} className="space-y-4">
                                <div className="grid gap-4 md:grid-cols-3">
                                    <div className="space-y-2">
                                        <Label htmlFor="title">Title *</Label>
                                        <Input
                                            id="title"
                                            value={newForm.data.title}
                                            onChange={(e) => newForm.setData('title', e.target.value)}
                                            placeholder="Sales Teams"
                                        />
                                        {newForm.errors.title && (
                                            <p className="text-sm text-red-600">{newForm.errors.title}</p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="icon">Icon *</Label>
                                        <Input
                                            id="icon"
                                            value={newForm.data.icon}
                                            onChange={(e) => newForm.setData('icon', e.target.value)}
                                            placeholder=""
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="color">Color</Label>
                                        <Input
                                            id="color"
                                            value={newForm.data.color}
                                            onChange={(e) => newForm.setData('color', e.target.value)}
                                            placeholder="blue"
                                        />
                                    </div>
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="description">Description *</Label>
                                    <Textarea
                                        id="description"
                                        value={newForm.data.description}
                                        onChange={(e) => newForm.setData('description', e.target.value)}
                                        placeholder="Perfect for sales teams making outbound calls..."
                                        rows={3}
                                    />
                                </div>
                                <div className="flex justify-end gap-2">
                                    <Button type="button" variant="outline" onClick={() => setShowNewForm(false)}>
                                        Cancel
                                    </Button>
                                    <Button type="submit" disabled={newForm.processing}>
                                        {newForm.processing ? 'Creating...' : 'Create Use Case'}
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
                                    <TableHead>Title</TableHead>
                                    <TableHead>Description</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {useCases.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={5} className="text-center text-muted-foreground">
                                            No use cases yet. Click "Add Use Case" to create one.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    useCases.map((useCase) => (
                                        <TableRow key={useCase.id}>
                                            <TableCell className="text-2xl">{useCase.icon}</TableCell>
                                            <TableCell className="font-semibold">{useCase.title}</TableCell>
                                            <TableCell className="max-w-md truncate">{useCase.description}</TableCell>
                                            <TableCell>
                                                <Badge variant={useCase.is_active ? 'default' : 'secondary'}>
                                                    {useCase.is_active ? 'Active' : 'Inactive'}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => handleDelete(useCase.id)}
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
