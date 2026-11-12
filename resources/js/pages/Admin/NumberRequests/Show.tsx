import AppLayout from '@/layouts/app-layout';
import { useTranslation } from 'react-i18next';
import Heading from '@/components/heading';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Phone, User, Calendar, CheckCircle, XCircle } from 'lucide-react';

interface NumberRequest {
    id: number;
    user_id: number;
    user_name: string;
    user_email: string;
    phone_number: string;
    area_code: string;
    status: 'pending' | 'approved' | 'rejected';
    reason: string;
    requested_at: string;
    processed_at: string | null;
}

interface Props {
    request: NumberRequest;
}

export default function Show({ request }: Props) {
  const { t } = useTranslation();
    const requestData = request || {
        id: 0,
        user_id: 0,
        user_name: '',
        user_email: '',
        phone_number: '',
        area_code: '',
        status: 'pending' as const,
        reason: '',
        requested_at: '',
        processed_at: null
    };

    const handleApprove = () => {
        if (confirm('Are you sure you want to approve this number request?')) {
            router.post(`/admin/number-requests/${requestData.id}/approve`);
        }
    };

    const handleReject = () => {
        const reason = prompt('Please provide a reason for rejection:');
        if (reason) {
            router.post(`/admin/number-requests/${requestData.id}/reject`, { reason });
        }
    };

    return (
        <AppLayout>
            <Head title={`Number Request #${requestData.id}`} />

            <div className="space-y-6">
                <div className="flex items-center gap-4">
                    <Link href="/admin/number-requests">
                        <Button variant="ghost" size="icon">
                            <ArrowLeft className="h-4 w-4" />
                        </Button>
                    </Link>
                    <Heading
                        title={`Number Request #${requestData.id}`}
                        description={t('admin.number_requests.detailed_information_about_this_number_request')}
                    />
                </div>

                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <CardTitle>{t('admin.number_requests.request_status')}</CardTitle>
                            <Badge 
                                variant={
                                    requestData.status === 'approved' ? 'default' :
                                    requestData.status === 'rejected' ? 'destructive' : 
                                    'secondary'
                                }
                            >
                                {requestData.status}
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-6 md:grid-cols-2">
                            <div className="space-y-4">
                                <div>
                                    <div className="flex items-center gap-2 text-sm text-muted-foreground mb-1">
                                        <Phone className="h-4 w-4" />
                                        <span>Phone Number</span>
                                    </div>
                                    <p className="font-medium text-lg">{requestData.phone_number}</p>
                                </div>

                                <div>
                                    <p className="text-sm text-muted-foreground mb-1">Area Code</p>
                                    <p className="font-medium">{requestData.area_code}</p>
                                </div>

                                <div>
                                    <div className="flex items-center gap-2 text-sm text-muted-foreground mb-1">
                                        <Calendar className="h-4 w-4" />
                                        <span>Requested At</span>
                                    </div>
                                    <p className="font-medium">
                                        {new Date(requestData.requested_at).toLocaleString()}
                                    </p>
                                </div>

                                {requestData.processed_at && (
                                    <div>
                                        <p className="text-sm text-muted-foreground mb-1">Processed At</p>
                                        <p className="font-medium">
                                            {new Date(requestData.processed_at).toLocaleString()}
                                        </p>
                                    </div>
                                )}
                            </div>

                            <div className="space-y-4">
                                <div>
                                    <div className="flex items-center gap-2 text-sm text-muted-foreground mb-1">
                                        <User className="h-4 w-4" />
                                        <span>Requested By</span>
                                    </div>
                                    <p className="font-medium">{requestData.user_name}</p>
                                    <p className="text-sm text-muted-foreground">{requestData.user_email}</p>
                                </div>

                                {requestData.reason && (
                                    <div>
                                        <p className="text-sm text-muted-foreground mb-1">Reason</p>
                                        <p className="font-medium">{requestData.reason}</p>
                                    </div>
                                )}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {requestData.status === 'pending' && (
                    <div className="flex gap-4">
                        <Button 
                            onClick={handleApprove}
                            className="flex items-center gap-2"
                        >
                            <CheckCircle className="h-4 w-4" />
                            Approve Request
                        </Button>
                        <Button 
                            onClick={handleReject}
                            variant="destructive"
                            className="flex items-center gap-2"
                        >
                            <XCircle className="h-4 w-4" />
                            Reject Request
                        </Button>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
