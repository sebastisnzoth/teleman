import { Head, Link, router, useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AppLayout from '@/layouts/app-layout';
import Heading from '@/components/heading';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
    ChevronLeft,
    User,
    Mail,
    Calendar,
    Phone,
    Building,
    MapPin,
    FileText,
    CheckCircle,
    XCircle,
    Clock,
    Download,
    ExternalLink,
    AlertCircle
} from 'lucide-react';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { useState } from 'react';
import InputError from '@/components/input-error';

interface User {
    id: number;
    name: string;
    email: string;
    created_at: string;
}

interface Reviewer {
    name: string;
    email: string;
}

interface KycData {
    id: number;
    kyc_tier: 'basic' | 'business';
    status: 'pending' | 'approved' | 'rejected' | 'expired';
    user: User;
    // Phone verification
    phone_number?: string;
    phone_verified_at?: string;
    // Business information
    business_name?: string;
    business_registration_number?: string;
    business_type?: string;
    business_address?: string;
    // Documents
    id_document_type?: string;
    id_document_url?: string;
    id_document_status?: string;
    business_document_url?: string;
    business_document_status?: string;
    selfie_with_id_url?: string;
    selfie_with_id_status?: string;
    // Review info
    submitted_at?: string;
    reviewed_by?: Reviewer;
    reviewed_at?: string;
    rejection_reason?: string;
    admin_notes?: string;
}

interface Props {
    kyc: KycData;
}

const tierColors = {
    basic: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
    business: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400',
};

const statusColors = {
    pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
    approved: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    rejected: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    expired: 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400',
};

const docStatusColors = {
    pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
    approved: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    rejected: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    under_review: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
};

export default function KycShow({ kyc }: Props) {
    const { t } = useTranslation();
  const breadcrumbs: BreadcrumbItem[] = [
    {
        title: t('common.dashboard'),
        href: dashboard().url,
    },
    {
        title: t('admin.kyc.review'),
        href: '/admin/kyc',
    },
    {
        title: 'Review Details',
        href: '#',
    },
];
    const [showApproveForm, setShowApproveForm] = useState(false);
    const [showRejectForm, setShowRejectForm] = useState(false);

    const approveForm = useForm({
        admin_notes: '',
    });

    const rejectForm = useForm({
        rejection_reason: '',
        admin_notes: '',
        rejected_documents: [] as string[],
    });

    const handleApprove = (e: React.FormEvent) => {
        e.preventDefault();
        approveForm.post(`/admin/kyc/${kyc.id}/approve`, {
            preserveScroll: true,
            onSuccess: () => {
                setShowApproveForm(false);
            },
        });
    };

    const handleReject = (e: React.FormEvent) => {
        e.preventDefault();
        rejectForm.post(`/admin/kyc/${kyc.id}/reject`, {
            preserveScroll: true,
            onSuccess: () => {
                setShowRejectForm(false);
            },
        });
    };

    const isPending = kyc.status === 'pending';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${t('admin.kyc.title')} - ${kyc.user.name}`} />

            <div className="space-y-6 p-6">
                {/* Header */}
                <div className="flex items-start justify-between">
                    <div>
                        <Button asChild variant="ghost" size="sm" className="mb-3 -ml-2">
                            <Link href="/admin/kyc">
                                <ChevronLeft className="mr-1 h-4 w-4" />
                                {t('admin.kyc.back_to_queue')}
                            </Link>
                        </Button>
                        <Heading
                            title={`${t('admin.kyc.title')} - ${kyc.user.name}`}
                            description={t('admin.kyc.show_description')}
                        />
                    </div>
                    <div className="flex items-center gap-3">
                        <Badge className={tierColors[kyc.kyc_tier]} variant="secondary">
                            {kyc.kyc_tier === 'basic' && t('admin.kyc.basic_tier')}
                            {kyc.kyc_tier === 'business' && t('admin.kyc.business_tier')}
                        </Badge>
                        <Badge className={statusColors[kyc.status]} variant="secondary">
                            {kyc.status.charAt(0).toUpperCase() + kyc.status.slice(1)}
                        </Badge>
                    </div>
                </div>

                {/* Alert for reviewed status */}
                {!isPending && (
                    <Alert className={kyc.status === 'approved' ? 'border-green-200 dark:border-green-900/50' : 'border-red-200 dark:border-red-900/50'}>
                        {kyc.status === 'approved' ? (
                            <CheckCircle className="h-4 w-4 text-green-600 dark:text-green-400" />
                        ) : (
                            <XCircle className="h-4 w-4 text-red-600 dark:text-red-400" />
                        )}
                        <AlertTitle>
                            {kyc.status === 'approved' ? t('common.approved') : t('common.rejected')} {t('admin.kyc.reviewed_by')} {kyc.reviewed_by?.name}
                        </AlertTitle>
                        <AlertDescription>
                            {kyc.reviewed_at && `${t('admin.kyc.reviewed_on')} ${new Date(kyc.reviewed_at).toLocaleDateString('en-US', {
                                year: 'numeric',
                                month: 'long',
                                day: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit',
                            })}`}
                            {kyc.rejection_reason && (
                                <div className="mt-2">
                                    <strong>{t('admin.kyc.reason')}:</strong> {kyc.rejection_reason}
                                </div>
                            )}
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Main Content */}
                    <div className="space-y-6 lg:col-span-2">
                        {/* User Information */}
                        <Card>
                            <CardHeader className="pb-4">
                                <CardTitle className="flex items-center gap-2">
                                    <User className="h-5 w-5" />
                                    {t('admin.kyc.user_information')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <Label className="text-muted-foreground">{t('common.name')}</Label>
                                        <div className="mt-1 font-medium">{kyc.user.name}</div>
                                    </div>
                                    <div>
                                        <Label className="text-muted-foreground">{t('common.email')}</Label>
                                        <div className="mt-1 font-medium">{kyc.user.email}</div>
                                    </div>
                                    <div>
                                        <Label className="text-muted-foreground">{t('admin.kyc.user_since')}</Label>
                                        <div className="mt-1 font-medium">
                                            {new Date(kyc.user.created_at).toLocaleDateString('en-US', {
                                                year: 'numeric',
                                                month: 'long',
                                                day: 'numeric',
                                            })}
                                        </div>
                                    </div>
                                    <div>
                                        <Label className="text-muted-foreground">{t('admin.kyc.submitted')}</Label>
                                        <div className="mt-1 font-medium">
                                            {kyc.submitted_at
                                                ? new Date(kyc.submitted_at).toLocaleDateString('en-US', {
                                                    year: 'numeric',
                                                    month: 'long',
                                                    day: 'numeric',
                                                })
                                                : <span className="text-muted-foreground">{t('admin.kyc.not_submitted')}</span>
                                            }
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Phone Verification (Basic Tier) */}
                        <Card>
                            <CardHeader className="pb-4">
                                <CardTitle className="flex items-center gap-2">
                                    <Phone className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                    {t('admin.kyc.phone_verification')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <Label className="text-muted-foreground">{t('common.phone')}</Label>
                                        <div className="mt-1 font-medium">{kyc.phone_number || '—'}</div>
                                    </div>
                                    <div>
                                        <Label className="text-muted-foreground">{t('admin.kyc.verified_at')}</Label>
                                        <div className="mt-1 font-medium">
                                            {kyc.phone_verified_at
                                                ? new Date(kyc.phone_verified_at).toLocaleDateString('en-US', {
                                                    year: 'numeric',
                                                    month: 'short',
                                                    day: 'numeric',
                                                })
                                                : <span className="text-yellow-600 dark:text-yellow-400">{t('admin.kyc.not_verified')}</span>
                                            }
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Business Information (Business Tier) */}
                        {kyc.kyc_tier === 'business' && (
                            <>
                                <Card>
                                    <CardHeader className="pb-4">
                                        <CardTitle className="flex items-center gap-2">
                                            <Building className="h-5 w-5 text-purple-600 dark:text-purple-400" />
                                            {t('admin.kyc.business_information')}
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div>
                                                <Label className="text-muted-foreground">{t('admin.kyc.business_name')}</Label>
                                                <div className="mt-1 font-medium">{kyc.business_name || '—'}</div>
                                            </div>
                                            <div>
                                                <Label className="text-muted-foreground">{t('admin.kyc.registration_number')}</Label>
                                                <div className="mt-1 font-medium">{kyc.business_registration_number || '—'}</div>
                                            </div>
                                            <div>
                                                <Label className="text-muted-foreground">{t('admin.kyc.business_type')}</Label>
                                                <div className="mt-1 font-medium">
                                                    {kyc.business_type
                                                        ? kyc.business_type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())
                                                        : '—'
                                                    }
                                                </div>
                                            </div>
                                            <div className="sm:col-span-2">
                                                <Label className="text-muted-foreground">{t('admin.kyc.business_address')}</Label>
                                                <div className="mt-1 font-medium">{kyc.business_address || '—'}</div>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>

                                {/* Documents */}
                                <Card>
                                    <CardHeader className="pb-4">
                                        <CardTitle className="flex items-center gap-2">
                                            <FileText className="h-5 w-5" />
                                            {t('admin.kyc.submitted_documents')}
                                        </CardTitle>
                                        <CardDescription>{t('admin.kyc.documents_description')}</CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        {/* ID Document */}
                                        <div className="flex items-center justify-between rounded-lg border p-4">
                                            <div className="flex items-center gap-3">
                                                <FileText className="h-5 w-5 text-muted-foreground" />
                                                <div>
                                                    <div className="font-medium">{t('admin.kyc.id_document')}</div>
                                                    <div className="text-sm text-muted-foreground">
                                                        {kyc.id_document_type
                                                            ? kyc.id_document_type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())
                                                            : t('admin.kyc.not_specified')
                                                        }
                                                    </div>
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                {kyc.id_document_status && (
                                                    <Badge className={docStatusColors[kyc.id_document_status as keyof typeof docStatusColors]} variant="secondary">
                                                        {kyc.id_document_status.replace(/_/g, ' ')}
                                                    </Badge>
                                                )}
                                                {kyc.id_document_url && (
                                                    <Button asChild variant="ghost" size="sm">
                                                        <a href={kyc.id_document_url} target="_blank" rel="noopener noreferrer">
                                                            <Download className="h-4 w-4" />
                                                        </a>
                                                    </Button>
                                                )}
                                            </div>
                                        </div>

                                        {/* Business Document */}
                                        <div className="flex items-center justify-between rounded-lg border p-4">
                                            <div className="flex items-center gap-3">
                                                <FileText className="h-5 w-5 text-muted-foreground" />
                                                <div>
                                                    <div className="font-medium">{t('admin.kyc.business_registration')}</div>
                                                    <div className="text-sm text-muted-foreground">{t('admin.kyc.certificate_or_license')}</div>
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                {kyc.business_document_status && (
                                                    <Badge className={docStatusColors[kyc.business_document_status as keyof typeof docStatusColors]} variant="secondary">
                                                        {kyc.business_document_status.replace(/_/g, ' ')}
                                                    </Badge>
                                                )}
                                                {kyc.business_document_url && (
                                                    <Button asChild variant="ghost" size="sm">
                                                        <a href={kyc.business_document_url} target="_blank" rel="noopener noreferrer">
                                                            <Download className="h-4 w-4" />
                                                        </a>
                                                    </Button>
                                                )}
                                            </div>
                                        </div>

                                        {/* Selfie with ID */}
                                        <div className="flex items-center justify-between rounded-lg border p-4">
                                            <div className="flex items-center gap-3">
                                                <FileText className="h-5 w-5 text-muted-foreground" />
                                                <div>
                                                    <div className="font-medium">{t('admin.kyc.selfie_with_id')}</div>
                                                    <div className="text-sm text-muted-foreground">{t('admin.kyc.selfie_description')}</div>
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                {kyc.selfie_with_id_status && (
                                                    <Badge className={docStatusColors[kyc.selfie_with_id_status as keyof typeof docStatusColors]} variant="secondary">
                                                        {kyc.selfie_with_id_status.replace(/_/g, ' ')}
                                                    </Badge>
                                                )}
                                                {kyc.selfie_with_id_url && (
                                                    <Button asChild variant="ghost" size="sm">
                                                        <a href={kyc.selfie_with_id_url} target="_blank" rel="noopener noreferrer">
                                                            <Download className="h-4 w-4" />
                                                        </a>
                                                    </Button>
                                                )}
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </>
                        )}

                        {/* Admin Notes (if any) */}
                        {kyc.admin_notes && (
                            <Card>
                                <CardHeader className="pb-4">
                                    <CardTitle>{t('admin.kyc.admin_notes')}</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-2">
                                    <p className="whitespace-pre-wrap text-sm">{kyc.admin_notes}</p>
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    {/* Actions Sidebar */}
                    <div className="space-y-6">
                        {isPending && (
                            <Card>
                                <CardHeader className="pb-4">
                                    <CardTitle>{t('common.actions')}</CardTitle>
                                    <CardDescription>{t('admin.kyc.review_decision')}</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <Button
                                        className="w-full"
                                        onClick={() => {
                                            setShowApproveForm(true);
                                            setShowRejectForm(false);
                                        }}
                                    >
                                        <CheckCircle className="mr-2 h-4 w-4" />
                                        {t('common.approved')}
                                    </Button>
                                    <Button
                                        variant="destructive"
                                        className="w-full"
                                        onClick={() => {
                                            setShowRejectForm(true);
                                            setShowApproveForm(false);
                                        }}
                                    >
                                        <XCircle className="mr-2 h-4 w-4" />
                                        {t('common.rejected')}
                                    </Button>
                                </CardContent>
                            </Card>
                        )}

                        {/* Approve Form */}
                        {showApproveForm && (
                            <Card>
                                <CardHeader className="pb-4">
                                    <CardTitle className="text-green-600 dark:text-green-400">{t('admin.kyc.approve_kyc')}</CardTitle>
                                    <CardDescription>{t('admin.kyc.approve_description')}</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <form onSubmit={handleApprove} className="space-y-4">
                                        <div>
                                            <Label htmlFor="admin_notes">{t('admin.kyc.admin_notes_optional')}</Label>
                                            <Textarea
                                                id="admin_notes"
                                                value={approveForm.data.admin_notes}
                                                onChange={(e) => approveForm.setData('admin_notes', e.target.value)}
                                                placeholder={t('admin.kyc.internal_notes_placeholder')}
                                                rows={3}
                                                disabled={approveForm.processing}
                                            />
                                            <InputError message={approveForm.errors.admin_notes} />
                                        </div>

                                        <div className="flex gap-2">
                                            <Button type="submit" disabled={approveForm.processing} className="flex-1">
                                                {approveForm.processing ? t('admin.kyc.approving') : t('admin.kyc.confirm_approval')}
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() => setShowApproveForm(false)}
                                                disabled={approveForm.processing}
                                            >
                                                {t('common.cancel')}
                                            </Button>
                                        </div>
                                    </form>
                                </CardContent>
                            </Card>
                        )}

                        {/* Reject Form */}
                        {showRejectForm && (
                            <Card>
                                <CardHeader className="pb-4">
                                    <CardTitle className="text-red-600 dark:text-red-400">{t('admin.kyc.reject_kyc')}</CardTitle>
                                    <CardDescription>{t('admin.kyc.reject_description')}</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <form onSubmit={handleReject} className="space-y-4">
                                        <div>
                                            <Label htmlFor="rejection_reason">
                                                {t('admin.kyc.rejection_reason')} <span className="text-destructive">*</span>
                                            </Label>
                                            <Textarea
                                                id="rejection_reason"
                                                value={rejectForm.data.rejection_reason}
                                                onChange={(e) => rejectForm.setData('rejection_reason', e.target.value)}
                                                placeholder={t('admin.kyc.rejection_placeholder')}
                                                rows={3}
                                                disabled={rejectForm.processing}
                                                required
                                            />
                                            <InputError message={rejectForm.errors.rejection_reason} />
                                        </div>

                                        <div>
                                            <Label htmlFor="reject_admin_notes">{t('admin.kyc.admin_notes_optional')}</Label>
                                            <Textarea
                                                id="reject_admin_notes"
                                                value={rejectForm.data.admin_notes}
                                                onChange={(e) => rejectForm.setData('admin_notes', e.target.value)}
                                                placeholder={t('admin.kyc.internal_notes_placeholder')}
                                                rows={2}
                                                disabled={rejectForm.processing}
                                            />
                                            <InputError message={rejectForm.errors.admin_notes} />
                                        </div>

                                        <div className="flex gap-2">
                                            <Button type="submit" variant="destructive" disabled={rejectForm.processing} className="flex-1">
                                                {rejectForm.processing ? t('admin.kyc.rejecting') : t('admin.kyc.confirm_rejection')}
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() => setShowRejectForm(false)}
                                                disabled={rejectForm.processing}
                                            >
                                                {t('common.cancel')}
                                            </Button>
                                        </div>
                                    </form>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
