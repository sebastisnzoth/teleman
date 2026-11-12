import HeadingSmall from '@/components/heading-small';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import {
    BreadcrumbItem,
    KycChecklistKey,
    KycChecklistState,
    UserKycVerification,
} from '@/types';
import { Head, Link } from '@inertiajs/react';
import {
    AlertCircle,
    Calendar,
    CheckCircle,
    ChevronRight,
    Circle,
    Clock,
    FileText,
    Shield,
    Upload,
    XCircle,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface Props {
    kyc: UserKycVerification | null;
}

const statusColors = {
    pending:
        'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
    approved:
        'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    rejected: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    expired: 'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400',
};

const statusIcons = {
    pending: Clock,
    approved: CheckCircle,
    rejected: XCircle,
    expired: AlertCircle,
};

/**
 * Only items the platform can actually collect. "Proof of address" used to be
 * advertised here with a green tick, but no column, upload or review step for
 * it has ever existed.
 */
const checklistLabel: Record<KycChecklistKey, string> = {
    phone: 'Phone number',
    id_document: 'Government-issued ID',
    selfie_with_id: 'Selfie with ID',
    business_document: 'Business documents',
};

/** Green is reserved for checks that genuinely passed review. */
const checklistIcon: Record<
    KycChecklistState,
    { Icon: typeof CheckCircle; className: string }
> = {
    verified: {
        Icon: CheckCircle,
        className: 'text-green-600 dark:text-green-400',
    },
    pending: { Icon: Clock, className: 'text-yellow-600 dark:text-yellow-400' },
    rejected: { Icon: XCircle, className: 'text-red-600 dark:text-red-400' },
    missing: { Icon: Circle, className: 'text-muted-foreground' },
};

const checklistStateLabel: Record<KycChecklistState, string> = {
    verified: 'Verified',
    pending: 'Under review',
    rejected: 'Rejected',
    missing: 'Not submitted',
};

export default function KycIndex({ kyc }: Props) {
    const { t } = useTranslation();
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: t('settings.title'),
            href: '/settings/profile',
        },
        {
            title: t('settings.kyc_verification'),
            href: '/settings/kyc',
        },
    ];
    const currentStatus = kyc?.status || null;
    const StatusIcon = currentStatus ? statusIcons[currentStatus] : null;
    const isUnverified = !kyc || !currentStatus;

    const getDaysRemaining = () => {
        if (!kyc?.expires_at) return null;
        const expiryDate = new Date(kyc.expires_at);
        const today = new Date();
        const diffTime = expiryDate.getTime() - today.getTime();
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        return diffDays;
    };

    const daysRemaining = getDaysRemaining();
    // Server-stated. Empty until a KYC record exists, which is honest for an
    // account that has started nothing.
    const checklist = kyc?.checklist ?? [];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('settings.kyc_verification')} />

            <SettingsLayout>
                <div className="space-y-6 p-6">
                    {/* Header */}
                    <div className="flex items-start justify-between">
                        <HeadingSmall
                            title={t('settings.kyc_verification')}
                            description={t(
                                'settings.verify_your_identity_to_unlock_platform_features',
                            )}
                        />
                    </div>

                    {/* Status Alerts */}
                    {isUnverified && (
                        <Alert
                            variant="destructive"
                            className="border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900/50 dark:bg-amber-900/20 dark:text-amber-400"
                        >
                            <AlertCircle className="h-4 w-4" />
                            <AlertTitle>Verification Required</AlertTitle>
                            <AlertDescription>
                                Complete KYC verification to access phone
                                numbers, campaigns, and calling features. Your
                                account will be suspended after the grace period
                                expires.
                            </AlertDescription>
                        </Alert>
                    )}

                    {kyc?.status === 'pending' && (
                        <Alert className="border-yellow-200 bg-yellow-50 text-yellow-900 dark:border-yellow-900/50 dark:bg-yellow-900/20 dark:text-yellow-400">
                            <Clock className="h-4 w-4" />
                            <AlertTitle>Under Review</AlertTitle>
                            <AlertDescription>
                                Your verification is being reviewed by our team.
                                This typically takes 24-48 hours.
                            </AlertDescription>
                        </Alert>
                    )}

                    {kyc?.status === 'rejected' && (
                        <Alert
                            variant="destructive"
                            className="border-red-200 dark:border-red-900/50"
                        >
                            <XCircle className="h-4 w-4" />
                            <AlertTitle>Verification Rejected</AlertTitle>
                            <AlertDescription>
                                {kyc.rejection_reason ||
                                    'Please review the feedback and resubmit your documents.'}
                            </AlertDescription>
                        </Alert>
                    )}

                    {kyc?.status === 'approved' && kyc?.needs_renewal && (
                        <Alert className="border-yellow-200 bg-yellow-50 text-yellow-900 dark:border-yellow-900/50 dark:bg-yellow-900/20 dark:text-yellow-400">
                            <Calendar className="h-4 w-4" />
                            <AlertTitle>Renewal Required</AlertTitle>
                            <AlertDescription>
                                Your KYC verification expires in {daysRemaining}{' '}
                                days. Please renew to maintain access.
                            </AlertDescription>
                        </Alert>
                    )}

                    {/* Current Status Card */}
                    <Card>
                        <CardHeader className="pb-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle className="text-lg">
                                        {t('settings.verification_status')}
                                    </CardTitle>
                                    <CardDescription className="mt-1.5">
                                        Your current verification status and
                                        details
                                    </CardDescription>
                                </div>
                                <Shield className="h-8 w-8 text-muted-foreground" />
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {kyc && currentStatus && StatusIcon ? (
                                <>
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Badge
                                            className={
                                                statusColors[currentStatus]
                                            }
                                            variant="secondary"
                                        >
                                            <StatusIcon className="mr-1.5 h-3 w-3" />
                                            {currentStatus
                                                .charAt(0)
                                                .toUpperCase() +
                                                currentStatus.slice(1)}
                                        </Badge>
                                        {/* A bare "Approved" is what let a phone check read as a
                                            full identity check. The tier travels with it. */}
                                        {currentStatus === 'approved' && (
                                            <Badge variant="outline">
                                                {kyc.kyc_tier === 'business'
                                                    ? 'Business — documents verified'
                                                    : 'Basic — phone verified'}
                                            </Badge>
                                        )}
                                    </div>

                                    {currentStatus === 'approved' &&
                                        kyc.kyc_tier !== 'business' && (
                                            <p className="text-sm text-muted-foreground">
                                                This covers phone verification
                                                only. No identity documents have
                                                been submitted or reviewed.
                                            </p>
                                        )}

                                    {kyc.approved_at && (
                                        <div className="text-sm text-muted-foreground">
                                            Verified on{' '}
                                            {new Date(
                                                kyc.approved_at,
                                            ).toLocaleDateString('en-US', {
                                                year: 'numeric',
                                                month: 'long',
                                                day: 'numeric',
                                            })}
                                        </div>
                                    )}

                                    {kyc.expires_at &&
                                        currentStatus === 'approved' && (
                                            <div className="text-sm text-muted-foreground">
                                                Expires on{' '}
                                                {new Date(
                                                    kyc.expires_at,
                                                ).toLocaleDateString('en-US', {
                                                    year: 'numeric',
                                                    month: 'long',
                                                    day: 'numeric',
                                                })}
                                            </div>
                                        )}
                                </>
                            ) : (
                                <div className="text-sm text-muted-foreground">
                                    You have not submitted your KYC verification
                                    yet.
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Verification Action Card */}
                    <Card>
                        <CardHeader className="pb-4">
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30">
                                    <Upload className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                                </div>
                                <div>
                                    <CardTitle className="text-base">
                                        {t('settings.kyc_verification')}
                                    </CardTitle>
                                    <CardDescription className="mt-1">
                                        Submit your documents for verification
                                    </CardDescription>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {/* Rendered from the server's checklist, never from a fixed
                                list. Previously four requirements were painted with green
                                ticks regardless of state, so an account that had only
                                entered an SMS code was told its ID, address and selfie
                                were verified. "Proof of address" was listed too, which
                                the system has never had a field for. */}
                            <ul className="space-y-2 text-sm">
                                {checklist.map((item) => {
                                    const { Icon, className } =
                                        checklistIcon[item.state];

                                    return (
                                        <li
                                            key={item.key}
                                            className="flex items-center gap-2"
                                        >
                                            <Icon
                                                className={`h-4 w-4 shrink-0 ${className}`}
                                            />
                                            <span
                                                className={
                                                    item.state === 'missing'
                                                        ? 'text-muted-foreground'
                                                        : undefined
                                                }
                                            >
                                                {checklistLabel[item.key]}
                                            </span>
                                            <span className="ml-auto text-xs text-muted-foreground">
                                                {
                                                    checklistStateLabel[
                                                        item.state
                                                    ]
                                                }
                                            </span>
                                        </li>
                                    );
                                })}
                            </ul>

                            {isUnverified && (
                                <Button asChild className="w-full">
                                    <Link href="/settings/kyc/basic">
                                        Start Verification
                                        <ChevronRight className="ml-2 h-4 w-4" />
                                    </Link>
                                </Button>
                            )}

                            {kyc?.status === 'rejected' && (
                                <Button asChild className="w-full">
                                    <Link href="/settings/kyc/basic">
                                        Resubmit Documents
                                        <ChevronRight className="ml-2 h-4 w-4" />
                                    </Link>
                                </Button>
                            )}

                            {kyc?.status === 'approved' &&
                                kyc?.business_verified && (
                                    <div className="flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-800 dark:border-green-900/50 dark:bg-green-900/20 dark:text-green-400">
                                        <CheckCircle className="h-4 w-4" />
                                        Verification Complete
                                    </div>
                                )}

                            {/* Approved at basic tier is a real state, but it is not a
                                completed document check — say which one it is. */}
                            {kyc?.status === 'approved' &&
                                !kyc?.business_verified && (
                                    <div className="space-y-3 rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-900 dark:border-blue-900/50 dark:bg-blue-900/20 dark:text-blue-300">
                                        <div className="flex items-center gap-2">
                                            <CheckCircle className="h-4 w-4 shrink-0" />
                                            Phone verified — Basic verification
                                            active
                                        </div>
                                        <p className="text-xs opacity-90">
                                            Submit your identity documents to
                                            unlock Business features such as AI
                                            voice agents.
                                        </p>
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                            className="w-full"
                                        >
                                            <Link href="/settings/kyc/business">
                                                Start Business verification
                                                <ChevronRight className="ml-2 h-4 w-4" />
                                            </Link>
                                        </Button>
                                    </div>
                                )}

                            {kyc?.status === 'pending' && (
                                <div className="flex items-center gap-2 rounded-lg border border-yellow-200 bg-yellow-50 p-3 text-sm text-yellow-800 dark:border-yellow-900/50 dark:bg-yellow-900/20 dark:text-yellow-400">
                                    <Clock className="h-4 w-4" />
                                    Under Review
                                </div>
                            )}

                            {kyc?.needs_renewal && (
                                <Button
                                    asChild
                                    variant="outline"
                                    className="w-full"
                                >
                                    <Link href="/settings/kyc/basic">
                                        Renew Verification
                                        <ChevronRight className="ml-2 h-4 w-4" />
                                    </Link>
                                </Button>
                            )}
                        </CardContent>
                    </Card>

                    {/* Documents Status - Only show when not approved */}
                    {kyc?.documents_status && kyc?.status !== 'approved' && (
                        <Card>
                            <CardHeader className="pb-4">
                                <CardTitle className="text-lg">
                                    {t('settings.submitted_documents')}
                                </CardTitle>
                                <CardDescription className="mt-1.5">
                                    Review status of your uploaded documents
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {Object.entries(kyc.documents_status).map(
                                    ([key, doc]) => (
                                        <div
                                            key={key}
                                            className="flex items-center justify-between rounded-lg border p-3"
                                        >
                                            <div className="flex items-center gap-3">
                                                <FileText className="h-5 w-5 text-muted-foreground" />
                                                <div>
                                                    <div className="text-sm font-medium">
                                                        {key
                                                            .replace(/_/g, ' ')
                                                            .replace(
                                                                /\b\w/g,
                                                                (l) =>
                                                                    l.toUpperCase(),
                                                            )}
                                                    </div>
                                                    <div className="text-xs text-muted-foreground">
                                                        {doc.uploaded
                                                            ? 'Uploaded'
                                                            : 'Not uploaded'}
                                                    </div>
                                                </div>
                                            </div>
                                            <Badge
                                                className={
                                                    statusColors[doc.status] ||
                                                    statusColors.pending
                                                }
                                                variant="secondary"
                                            >
                                                {doc.status
                                                    ? doc.status
                                                          .charAt(0)
                                                          .toUpperCase() +
                                                      doc.status.slice(1)
                                                    : 'Pending'}
                                            </Badge>
                                        </div>
                                    ),
                                )}
                            </CardContent>
                        </Card>
                    )}
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
