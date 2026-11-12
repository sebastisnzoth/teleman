import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Alert, AlertDescription } from '@/components/ui/alert';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Link, Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
  Phone,
  PhoneCall,
  MessageSquare,
  Search,
  Clock,
  CheckCircle,
  XCircle,
  Ban,
  AlertCircle
} from 'lucide-react';

interface NumberRequest {
  id: number;
  phone_number: {
    number: string;
    formatted_number: string;
    friendly_name: string;
    country_code: string;
    area_code: string;
    capabilities: {
      voice: boolean;
      sms: boolean;
      mms: boolean;
    };
    monthly_cost: number;
  };
  status: 'pending' | 'approved' | 'rejected' | 'cancelled';
  customer_notes: string | null;
  admin_notes: string | null;
  requested_at: string;
  processed_at: string | null;
}

interface PaginatedRequests {
  data: NumberRequest[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

interface Props {
  requests: PaginatedRequests;
}

export default function MyRequests({ requests }: Props) {
  const { t } = useTranslation();
  const [selectedRequest, setSelectedRequest] = useState<NumberRequest | null>(null);
  const [isCancelModalOpen, setIsCancelModalOpen] = useState(false);
  const [isProcessing, setIsProcessing] = useState(false);

  const handleCancelRequest = (request: NumberRequest) => {
    setSelectedRequest(request);
    setIsCancelModalOpen(true);
  };

  const confirmCancel = () => {
    if (!selectedRequest) return;

    setIsProcessing(true);
    router.post(
      `/numbers/requests/${selectedRequest.id}/cancel`,
      {},
      {
        onSuccess: () => {
          setIsCancelModalOpen(false);
          setSelectedRequest(null);
        },
        onFinish: () => setIsProcessing(false),
      }
    );
  };

  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'pending': return <Clock className="h-4 w-4" />;
      case 'approved': return <CheckCircle className="h-4 w-4" />;
      case 'rejected': return <XCircle className="h-4 w-4" />;
      case 'cancelled': return <Ban className="h-4 w-4" />;
      default: return <AlertCircle className="h-4 w-4" />;
    }
  };

  const getStatusVariant = (status: string): "default" | "secondary" | "destructive" | "outline" => {
    switch (status) {
      case 'pending': return 'secondary';
      case 'approved': return 'default';
      case 'rejected': return 'destructive';
      case 'cancelled': return 'outline';
      default: return 'outline';
    }
  };

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
    }).format(amount);
  };

  const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  const pendingCount = requests.data.filter(r => r.status === 'pending').length;
  const approvedCount = requests.data.filter(r => r.status === 'approved').length;
  const rejectedCount = requests.data.filter(r => r.status === 'rejected').length;

  return (
    <AppLayout>
      <Head title={t('numbers.requests_title')} />

      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold tracking-tight">{t('numbers.requests_title')}</h1>
            <p className="text-muted-foreground">
              {t('numbers.requests_description')}
            </p>
          </div>
          <div className="flex gap-2">
            <Button variant="outline" asChild>
              <Link href="/numbers/my-numbers">
                <Phone className="mr-2 h-4 w-4" />
                {t('numbers.my_numbers_title')}
              </Link>
            </Button>
            <Button asChild>
              <Link href="/numbers/available">
                <Search className="mr-2 h-4 w-4" />
                {t('numbers.browse_available')}
              </Link>
            </Button>
          </div>
        </div>

        {/* Stats Cards */}
        {requests.data.length > 0 && (
          <div className="grid gap-4 md:grid-cols-4">
            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">{t('numbers.total_purchases')}</CardTitle>
                <Phone className="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{requests.total}</div>
                <p className="text-xs text-muted-foreground">
                  {t('numbers.all_time')}
                </p>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">{t('common.pending')}</CardTitle>
                <Clock className="h-4 w-4 text-yellow-600" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{pendingCount}</div>
                <p className="text-xs text-muted-foreground">
                  {t('numbers.awaiting_review')}
                </p>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">{t('common.approved')}</CardTitle>
                <CheckCircle className="h-4 w-4 text-green-600" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{approvedCount}</div>
                <p className="text-xs text-muted-foreground">
                  {t('numbers.numbers_assigned')}
                </p>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">{t('common.rejected')}</CardTitle>
                <XCircle className="h-4 w-4 text-red-600" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{rejectedCount}</div>
                <p className="text-xs text-muted-foreground">
                  {t('numbers.not_approved')}
                </p>
              </CardContent>
            </Card>
          </div>
        )}

        {/* Requests List */}
        <Card>
          <CardHeader>
            <CardTitle>{t('numbers.requests_title')}</CardTitle>
            <CardDescription>
              {t('numbers.requests_description')}
            </CardDescription>
          </CardHeader>
          <CardContent>
            {requests.data.length === 0 ? (
              <div className="text-center py-12">
                <Clock className="mx-auto h-12 w-12 text-muted-foreground mb-4" />
                <h3 className="text-lg font-semibold mb-2">{t('numbers.no_purchases')}</h3>
                <p className="text-muted-foreground mb-6">
                  {t('numbers.no_purchases_description')}
                </p>
                <Button asChild>
                  <Link href="/numbers/available">
                    <Search className="mr-2 h-4 w-4" />
                    {t('numbers.browse_available')}
                  </Link>
                </Button>
              </div>
            ) : (
              <>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>{t('common.phone')}</TableHead>
                      <TableHead>{t('common.capabilities')}</TableHead>
                      <TableHead>{t('numbers.monthly_cost')}</TableHead>
                      <TableHead>{t('numbers.requested')}</TableHead>
                      <TableHead>{t('common.status')}</TableHead>
                      <TableHead>{t('common.notes')}</TableHead>
                      <TableHead className="text-right">{t('common.actions')}</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {requests.data.map((request) => (
                      <TableRow key={request.id}>
                        <TableCell>
                          <div className="flex flex-col">
                            <span className="font-medium">{request.phone_number.formatted_number}</span>
                            <span className="text-sm text-muted-foreground">
                              {request.phone_number.country_code} • {request.phone_number.area_code}
                            </span>
                          </div>
                        </TableCell>
                        <TableCell>
                          <div className="flex gap-1">
                            {request.phone_number.capabilities.voice && (
                              <Badge variant="secondary" className="text-xs">
                                <PhoneCall className="mr-1 h-3 w-3" />
                                {t('common.voice')}
                              </Badge>
                            )}
                            {request.phone_number.capabilities.sms && (
                              <Badge variant="secondary" className="text-xs">
                                <MessageSquare className="mr-1 h-3 w-3" />
                                {t('common.sms')}
                              </Badge>
                            )}
                          </div>
                        </TableCell>
                        <TableCell className="font-medium">
                          {formatCurrency(request.phone_number.monthly_cost)}
                        </TableCell>
                        <TableCell className="text-sm text-muted-foreground">
                          {formatDate(request.requested_at)}
                        </TableCell>
                        <TableCell>
                          <Badge variant={getStatusVariant(request.status)}>
                            <span className="flex items-center gap-1">
                              {getStatusIcon(request.status)}
                              {request.status.charAt(0).toUpperCase() + request.status.slice(1)}
                            </span>
                          </Badge>
                        </TableCell>
                        <TableCell>
                          {request.status === 'rejected' && request.admin_notes ? (
                            <div className="max-w-xs">
                              <p className="text-sm text-red-600 font-medium">{t('numbers.rejection_reason')}</p>
                              <p className="text-sm text-muted-foreground truncate">{request.admin_notes}</p>
                            </div>
                          ) : request.status === 'approved' && request.admin_notes ? (
                            <div className="max-w-xs">
                              <p className="text-sm text-muted-foreground truncate">{request.admin_notes}</p>
                            </div>
                          ) : request.customer_notes ? (
                            <p className="text-sm text-muted-foreground truncate max-w-xs">{request.customer_notes}</p>
                          ) : (
                            <span className="text-sm text-muted-foreground">—</span>
                          )}
                        </TableCell>
                        <TableCell className="text-right">
                          {request.status === 'pending' ? (
                            <Button
                              variant="outline"
                              size="sm"
                              onClick={() => handleCancelRequest(request)}
                            >
                              {t('common.cancel')}
                            </Button>
                          ) : request.status === 'approved' ? (
                            <Button variant="ghost" size="sm" asChild>
                              <Link href="/numbers/my-numbers">{t('numbers.view_number')}</Link>
                            </Button>
                          ) : (
                            <span className="text-sm text-muted-foreground">—</span>
                          )}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>

                {/* Pagination */}
                {requests.last_page > 1 && (
                  <div className="flex items-center justify-between pt-4">
                    <div className="text-sm text-muted-foreground">
                      {t('common.showing')} {requests.data.length} {t('common.of')} {requests.total} {t('common.numbers')}
                    </div>
                    <div className="flex gap-2">
                      <Button
                        variant="outline"
                        size="sm"
                        disabled={requests.current_page === 1}
                      >
                        {t('common.previous')}
                      </Button>
                      <Button
                        variant="outline"
                        size="sm"
                        disabled={requests.current_page === requests.last_page}
                      >
                        {t('common.next')}
                      </Button>
                    </div>
                  </div>
                )}

                {/* Help Alert */}
                {pendingCount > 0 && (
                  <Alert className="mt-4">
                    <Clock className="h-4 w-4" />
                    <AlertDescription>
                      {t('numbers.you_have')} {pendingCount} {t('numbers.pending_review')} {pendingCount === 1 ? t('numbers.request_singular') : t('numbers.request_plural')}. {t('numbers.admin_review')}
                    </AlertDescription>
                  </Alert>
                )}
              </>
            )}
          </CardContent>
        </Card>
      </div>

      {/* Cancel Confirmation Modal */}
      <Dialog open={isCancelModalOpen} onOpenChange={setIsCancelModalOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t('numbers.cancel_request')}</DialogTitle>
            <DialogDescription>
              {t('numbers.cancel_confirm')}
            </DialogDescription>
          </DialogHeader>

          {selectedRequest && (
            <div className="py-4">
              <div className="rounded-lg border p-4 space-y-2">
                <div className="flex items-center justify-between">
                  <span className="text-sm font-medium">{t('numbers.phone_number_label')}</span>
                  <span className="text-lg font-bold">{selectedRequest.phone_number.formatted_number}</span>
                </div>
                <div className="flex items-center justify-between">
                  <span className="text-sm font-medium">{t('numbers.requested')}:</span>
                  <span className="text-sm text-muted-foreground">{formatDate(selectedRequest.requested_at)}</span>
                </div>
              </div>

              <Alert className="mt-4" variant="destructive">
                <AlertCircle className="h-4 w-4" />
                <AlertDescription>
                  {t('numbers.cancel_description')}
                </AlertDescription>
              </Alert>
            </div>
          )}

          <DialogFooter>
            <Button
              variant="outline"
              onClick={() => setIsCancelModalOpen(false)}
              disabled={isProcessing}
            >
              {t('numbers.keep_request')}
            </Button>
            <Button
              variant="destructive"
              onClick={confirmCancel}
              disabled={isProcessing}
            >
              {isProcessing ? t('numbers.cancelling') : t('numbers.cancel_request')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </AppLayout>
  );
}
