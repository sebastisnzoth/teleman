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

interface Faq {
    id: number;
    question: string;
    answer: string;
    icon: string | null;
    order: number;
    is_active: boolean;
}

interface FaqsProps {
    faqs: Faq[];
}

export default function Faqs({ faqs = [] }: FaqsProps) {
  const { t } = useTranslation();
    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('common.dashboard'), href: dashboard().url },
        { title: t('admin.theme.theme'), href: '/admin/theme' },
        { title: t('admin.theme.faqs'), href: '/admin/theme/faqs' },
    ];

    const [showNewForm, setShowNewForm] = useState(false);

    const newForm = useForm({
        question: '',
        answer: '',
        icon: '',
        order: faqs.length + 1,
        is_active: true,
    });

    const handleCreate = (e: React.FormEvent) => {
        e.preventDefault();
        newForm.post('/admin/theme/faqs', {
            onSuccess: () => {
                newForm.reset();
                setShowNewForm(false);
            },
        });
    };

    const handleDelete = (id: number) => {
        if (confirm('Are you sure you want to delete this FAQ?')) {
            router.delete(`/admin/theme/faqs/${id}`);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('admin.theme.faqs_section')} />

            <div className="mx-auto max-w-7xl space-y-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title={t('admin.theme.faqs_section')}
                        description={t('admin.theme.manage_frequently_asked_questions')}
                    />
                    <Button onClick={() => setShowNewForm(!showNewForm)}>
                        <Plus className="mr-2 h-4 w-4" />
                        Add FAQ
                    </Button>
                </div>

                                {showNewForm && (
                    <Card>
                        <CardContent className="pt-6">
                            <form onSubmit={handleCreate} className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="question">Question *</Label>
                                    <Input
                                        id="question"
                                        value={newForm.data.question}
                                        onChange={(e) => newForm.setData('question', e.target.value)}
                                        placeholder="How does it work?"
                                    />
                                    {newForm.errors.question && (
                                        <p className="text-sm text-red-600">{newForm.errors.question}</p>
                                    )}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="answer">Answer *</Label>
                                    <Textarea
                                        id="answer"
                                        value={newForm.data.answer}
                                        onChange={(e) => newForm.setData('answer', e.target.value)}
                                        placeholder="Here's how it works..."
                                        rows={4}
                                    />
                                    {newForm.errors.answer && (
                                        <p className="text-sm text-red-600">{newForm.errors.answer}</p>
                                    )}
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="icon">Icon</Label>
                                    <Input
                                        id="icon"
                                        value={newForm.data.icon}
                                        onChange={(e) => newForm.setData('icon', e.target.value)}
                                        placeholder="❓"
                                    />
                                </div>
                                <div className="flex justify-end gap-2">
                                    <Button type="button" variant="outline" onClick={() => setShowNewForm(false)}>
                                        Cancel
                                    </Button>
                                    <Button type="submit" disabled={newForm.processing}>
                                        {newForm.processing ? 'Creating...' : 'Create FAQ'}
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
                                    <TableHead>Question</TableHead>
                                    <TableHead>Answer</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {faqs.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={5} className="text-center text-muted-foreground">
                                            No FAQs yet. Click "Add FAQ" to create one.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    faqs.map((faq) => (
                                        <TableRow key={faq.id}>
                                            <TableCell className="text-2xl">{faq.icon || '❓'}</TableCell>
                                            <TableCell className="font-semibold max-w-xs truncate">{faq.question}</TableCell>
                                            <TableCell className="max-w-md truncate">{faq.answer}</TableCell>
                                            <TableCell>
                                                <Badge variant={faq.is_active ? 'default' : 'secondary'}>
                                                    {faq.is_active ? 'Active' : 'Inactive'}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => handleDelete(faq.id)}
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
