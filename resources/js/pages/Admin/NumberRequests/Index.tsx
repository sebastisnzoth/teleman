import AppLayout from '@/layouts/app-layout';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from '@/components/ui/sheet';
import { Label } from '@/components/ui/label';
import { Link, router, Head, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { 
  Search, 
  Phone, 
  PhoneCall, 
  MessageSquare, 
  Filter,
  X,
  Clock,
  CheckCircle,
  XCircle,
  AlertCircle,
  Eye
} from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import Heading from '@/components/heading';
import { PageHelp } from '@/components/page-help';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';

interface NumberRequest {
  id: number;
  phone_number: {
    id: number;
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
  customer: {
    id: number;
    name: string;
    email: string;
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
  filters: {
    search?: string;
    status?: string;
  };
  stats: {
    total: number;
    pending: number;
    approved: number;
    rejected: number;
  };
}

export default function AdminNumberRequests({ 
  requests = { data: [], current_page: 1, last_page: 1, per_page: 15, total: 0 },
  filters = {},
  stats = { pending: 0, approved: 0, rejected: 0 }
}: Props) {
  const { t } = useTranslation();
  const { flash } = usePage().props as any;
  const [selectedRequest, setSelectedRequest] = useState<NumberRequest | null>(null);
  const [isApproveModalOpen, setIsApproveModalOpen] = useState(false);
  const [isRejectModalOpen, setIsRejectModalOpen] = useState(false);
  const [isViewModalOpen, setIsViewModalOpen] = useState(false);
  const [isFilterSheetOpen, setIsFilterSheetOpen] = useState(false);

  const [localFilters, setLocalFilters] = useState({
    search: filters.search || '',
    status: filters.status || 'all',
  });

  const approveForm = useForm({
    admin_notes: '',
  });

  const rejectForm = useForm({
    admin_notes: '',
  });

  const handleSearch = () => {
    // Filter out 'all' values before sending to backend
    const searchParams = {
      ...localFilters,
      status: localFilters.status === 'all' ? '' : localFilters.status,
    };
    
    router.get('/admin/number-requests', searchParams, {
      preserveState: true,
      preserveScroll: true,
    });
  };

  const handleClearFilters = () => {
    const clearedFilters = { search: '', status: 'all' };
    setLocalFilters(clearedFilters);
    router.get('/admin/number-requests', {}, { preserveState: true, preserveScroll: true });
  };

  const handleApprove = (request: NumberRequest) => {
    setSelectedRequest(request);
    setIsApproveModalOpen(true);
  };

  const handleReject = (request: NumberRequest) => {
    setSelectedRequest(request);
    setIsRejectModalOpen(true);
  };

  const handleView = (request: NumberRequest) => {
    setSelectedRequest(request);
    setIsViewModalOpen(true);
  };

  const submitApproval = (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedRequest) return;

    approveForm.post(`/admin/number-requests/${selectedRequest.id}/approve`, {
      onSuccess: () => {
        setIsApproveModalOpen(false);
        approveForm.reset();
        setSelectedRequest(null);
      },
    });
  };

  const submitRejection = (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedRequest) return;

    rejectForm.post(`/admin/number-requests/${selectedRequest.id}/reject`, {
      onSuccess: () => {
        setIsRejectModalOpen(false);
        rejectForm.reset();
        setSelectedRequest(null);
      },
    });
  };

  const getStatusIcon = (status: string) => {
    switch (status) {
      case 'pending': return <Clock className="h-4 w-4" />;
      case 'approved': return <CheckCircle className="h-4 w-4" />;
      case 'rejected': return <XCircle className="h-4 w-4" />;
      default: return <AlertCircle className="h-4 w-4" />;
    }
  };

  const getStatusVariant = (status: string): "default" | "secondary" | "destructive" | "outline" => {
    switch (status) {
      case 'pending': return 'secondary';
      case 'approved': return 'default';
      case 'rejected': return 'destructive';
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
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  const breadcrumbs: BreadcrumbItem[] = [
    { title: t('common.dashboard'), href: dashboard().url },
    { title: t('admin.number_requests.number_requests'), href: '/admin/number-requests' },
  ];

  const helpSections = [
    {
      title: 'Number Requests Overview',
      content: 'Customers request phone numbers from the available inventory. Review, approve, or reject requests based on availability and customer needs.',
    },
    {
      title: 'Request Status',
      content: 'Pending: Awaiting admin review. Approved: Number assigned to customer. Rejected: Request denied (with reason). Cancelled: Customer withdrew request.',
    },
    {
      title: 'Approving Requests',
      content: 'Click "Approve" to assign the number to the customer. The number status changes to "assigned" and the customer can use it immediately.',
    },
    {
      title: 'Rejecting Requests',
      content: 'If a number is unavailable or inappropriate, reject the request. Always provide a clear reason in admin notes to help the customer understand.',
    },
    {
      title: 'Customer Notes',
      content: 'Review customer notes to understand why they need a specific number (area code, capabilities, etc.). This helps make informed decisions.',
    },
    {
      title: 'Number Capabilities',
      content: 'Each number shows Voice, SMS, and MMS capabilities. Ensure the number meets the customer\'s requirements before approving.',
    },
  ];

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={t('admin.number_requests.number_requests_admin')} />
      
      <div className="mx-auto max-w-7xl space-y-6">
        {/* Flash Messages */}
        {flash?.success && (
          <Alert className="border-green-600 bg-green-50 dark:bg-green-950/20">
            <CheckCircle className="h-4 w-4 text-green-600" />
            <AlertDescription className="text-green-800 dark:text-green-200">{flash.success}</AlertDescription>
          </Alert>
        )}
        {flash?.error && (
          <Alert variant="destructive">
            <AlertCircle className="h-4 w-4" />
            <AlertDescription>{flash.error}</AlertDescription>
          </Alert>
        )}

        {/* Header */}
        <div className="flex items-center justify-between">
          <Heading
            title={t('admin.number_requests.number_requests')}
            description={t('admin.number_requests.review_and_process_customer_phone_number_requests')}
          />
          <div className="flex items-center gap-2">
            <PageHelp title={t('admin.number_requests.number_requests_help')} sections={helpSections} />
            <Button variant="outline" asChild>
              <Link href="/admin/numbers">
                <Phone className="mr-2 h-4 w-4" />
                View Inventory
              </Link>
            </Button>
          </div>
        </div>

        {/* Stats Cards */}
        <div className="grid gap-4 md:grid-cols-4">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">{t('admin.number_requests.total_requests')}</CardTitle>
              <Phone className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{stats.total}</div>
              <p className="text-xs text-muted-foreground">
                All time
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">{t('admin.number_requests.pending_review')}</CardTitle>
              <Clock className="h-4 w-4 text-yellow-600" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-yellow-600">{stats.pending}</div>
              <p className="text-xs text-muted-foreground">
                Awaiting action
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">{t('admin.number_requests.approved')}</CardTitle>
              <CheckCircle className="h-4 w-4 text-green-600" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-green-600">{stats.approved}</div>
              <p className="text-xs text-muted-foreground">
                Numbers assigned
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">{t('admin.number_requests.rejected')}</CardTitle>
              <XCircle className="h-4 w-4 text-red-600" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-red-600">{stats.rejected}</div>
              <p className="text-xs text-muted-foreground">
                Not approved
              </p>
            </CardContent>
          </Card>
        </div>

        {/* Filters */}
        <Card>
          <CardHeader>
            <div className="flex items-center justify-between">
              <div>
                <CardTitle>{t('admin.number_requests.search_filter')}</CardTitle>
                <CardDescription>Find requests by customer or phone number</CardDescription>
              </div>
              <Sheet open={isFilterSheetOpen} onOpenChange={setIsFilterSheetOpen}>
                <SheetTrigger asChild>
                  <Button variant="outline" size="sm">
                    <Filter className="mr-2 h-4 w-4" />
                    Filters
                  </Button>
                </SheetTrigger>
                <SheetContent>
                  <SheetHeader>
                    <SheetTitle>Filter Requests</SheetTitle>
                    <SheetDescription>Narrow down the request queue</SheetDescription>
                  </SheetHeader>
                  <div className="space-y-4 py-4">
                    <div className="space-y-2">
                      <Label htmlFor="filter-status">Status</Label>
                      <Select
                        value={localFilters.status}
                        onValueChange={(value) => setLocalFilters({ ...localFilters, status: value })}
                      >
                        <SelectTrigger id="filter-status">
                          <SelectValue placeholder="All Statuses" />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="all">All Statuses</SelectItem>
                          <SelectItem value="pending">Pending</SelectItem>
                          <SelectItem value="approved">Approved</SelectItem>
                          <SelectItem value="rejected">Rejected</SelectItem>
                          <SelectItem value="cancelled">Cancelled</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                  </div>
                  <div className="flex gap-2">
                    <Button onClick={() => { handleSearch(); setIsFilterSheetOpen(false); }} className="flex-1">
                      Apply Filters
                    </Button>
                    <Button variant="outline" onClick={handleClearFilters}>
                      Clear
                    </Button>
                  </div>
                </SheetContent>
              </Sheet>
            </div>
          </CardHeader>
          <CardContent>
            <div className="flex gap-2">
              <div className="relative flex-1">
                <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                <Input
                  placeholder="Search by customer name or phone number..."
                  className="pl-8"
                  value={localFilters.search}
                  onChange={(e) => setLocalFilters({ ...localFilters, search: e.target.value })}
                  onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
                />
              </div>
              <Button onClick={handleSearch}>Search</Button>
              {Object.values(localFilters).some(v => v !== '') && (
                <Button variant="ghost" onClick={handleClearFilters}>
                  <X className="mr-2 h-4 w-4" />
                  Clear
                </Button>
              )}
            </div>
          </CardContent>
        </Card>

        {/* Requests Table */}
        <Card>
          <CardHeader>
            <CardTitle>Requests ({requests.total})</CardTitle>
            <CardDescription>
              {stats.pending > 0 
                ? `${stats.pending} request${stats.pending === 1 ? '' : 's'} pending your review` 
                : 'No pending requests'}
            </CardDescription>
          </CardHeader>
          <CardContent>
            {requests.data.length === 0 ? (
              <Alert>
                <AlertCircle className="h-4 w-4" />
                <AlertDescription>
                  No requests found matching your filters.
                </AlertDescription>
              </Alert>
            ) : (
              <>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Customer</TableHead>
                      <TableHead>Phone Number</TableHead>
                      <TableHead>Capabilities</TableHead>
                      <TableHead>Monthly Cost</TableHead>
                      <TableHead>Requested</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead className="text-right">Actions</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {requests.data.map((request) => (
                      <TableRow key={request.id} className={request.status === 'pending' ? 'bg-yellow-50/50 dark:bg-yellow-950/10' : ''}>
                        <TableCell>
                          <div className="flex flex-col">
                            <span className="font-medium">{request.customer.name}</span>
                            <span className="text-sm text-muted-foreground">{request.customer.email}</span>
                          </div>
                        </TableCell>
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
                                Voice
                              </Badge>
                            )}
                            {request.phone_number.capabilities.sms && (
                              <Badge variant="secondary" className="text-xs">
                                <MessageSquare className="mr-1 h-3 w-3" />
                                SMS
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
                        <TableCell className="text-right">
                          <div className="flex gap-1 justify-end">
                            <Button
                              variant="ghost"
                              size="sm"
                              onClick={() => handleView(request)}
                            >
                              <Eye className="h-4 w-4" />
                            </Button>
                            {request.status === 'pending' && (
                              <>
                                <Button
                                  variant="default"
                                  size="sm"
                                  onClick={() => handleApprove(request)}
                                  className="bg-green-600 hover:bg-green-700"
                                >
                                  Approve
                                </Button>
                                <Button
                                  variant="destructive"
                                  size="sm"
                                  onClick={() => handleReject(request)}
                                >
                                  Reject
                                </Button>
                              </>
                            )}
                          </div>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>

                {/* Pagination */}
                {requests.last_page > 1 && (
                  <div className="flex items-center justify-between pt-4">
                    <div className="text-sm text-muted-foreground">
                      Page {requests.current_page} of {requests.last_page}
                    </div>
                    <div className="flex gap-2">
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => router.get(`/admin/number-requests?page=${requests.current_page - 1}`, localFilters)}
                        disabled={requests.current_page === 1}
                      >
                        Previous
                      </Button>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => router.get(`/admin/number-requests?page=${requests.current_page + 1}`, localFilters)}
                        disabled={requests.current_page === requests.last_page}
                      >
                        Next
                      </Button>
                    </div>
                  </div>
                )}
              </>
            )}
          </CardContent>
        </Card>
      </div>

      {/* View Request Modal */}
      <Dialog open={isViewModalOpen} onOpenChange={setIsViewModalOpen}>
        <DialogContent className="max-w-2xl">
          <DialogHeader>
            <DialogTitle>Request Details</DialogTitle>
            <DialogDescription>
              View complete information about this number request
            </DialogDescription>
          </DialogHeader>
          
          {selectedRequest && (
            <div className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-2">
                  <h4 className="font-semibold">Customer Information</h4>
                  <div className="rounded-lg border p-3 space-y-1">
                    <div>
                      <span className="text-sm text-muted-foreground">Name:</span>
                      <p className="font-medium">{selectedRequest.customer.name}</p>
                    </div>
                    <div>
                      <span className="text-sm text-muted-foreground">Email:</span>
                      <p className="font-medium">{selectedRequest.customer.email}</p>
                    </div>
                  </div>
                </div>

                <div className="space-y-2">
                  <h4 className="font-semibold">Phone Number</h4>
                  <div className="rounded-lg border p-3 space-y-1">
                    <div>
                      <span className="text-sm text-muted-foreground">Number:</span>
                      <p className="font-medium">{selectedRequest.phone_number.formatted_number}</p>
                    </div>
                    <div>
                      <span className="text-sm text-muted-foreground">Location:</span>
                      <p className="font-medium">{selectedRequest.phone_number.country_code} • {selectedRequest.phone_number.area_code}</p>
                    </div>
                    <div>
                      <span className="text-sm text-muted-foreground">Monthly Cost:</span>
                      <p className="font-medium">{formatCurrency(selectedRequest.phone_number.monthly_cost)}</p>
                    </div>
                  </div>
                </div>
              </div>

              {selectedRequest.customer_notes && (
                <div className="space-y-2">
                  <h4 className="font-semibold">Customer Notes</h4>
                  <div className="rounded-lg border p-3 bg-muted/50">
                    <p className="text-sm">{selectedRequest.customer_notes}</p>
                  </div>
                </div>
              )}

              {selectedRequest.admin_notes && (
                <div className="space-y-2">
                  <h4 className="font-semibold">Admin Notes</h4>
                  <div className="rounded-lg border p-3 bg-blue-50 dark:bg-blue-950/20">
                    <p className="text-sm">{selectedRequest.admin_notes}</p>
                  </div>
                </div>
              )}

              <div className="grid grid-cols-2 gap-4 pt-2">
                <div>
                  <span className="text-sm text-muted-foreground">Status:</span>
                  <div className="mt-1">
                    <Badge variant={getStatusVariant(selectedRequest.status)}>
                      {selectedRequest.status.charAt(0).toUpperCase() + selectedRequest.status.slice(1)}
                    </Badge>
                  </div>
                </div>
                <div>
                  <span className="text-sm text-muted-foreground">Requested:</span>
                  <p className="mt-1 font-medium text-sm">{formatDate(selectedRequest.requested_at)}</p>
                </div>
              </div>
            </div>
          )}

          <DialogFooter>
            <Button variant="outline" onClick={() => setIsViewModalOpen(false)}>
              Close
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Approve Request Modal */}
      <Dialog open={isApproveModalOpen} onOpenChange={setIsApproveModalOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Approve Number Request</DialogTitle>
            <DialogDescription>
              Assign this phone number to the customer
            </DialogDescription>
          </DialogHeader>
          
          {selectedRequest && (
            <form onSubmit={submitApproval}>
              <div className="space-y-4 py-4">
                <div className="rounded-lg border p-4 space-y-2 bg-green-50 dark:bg-green-950/20">
                  <div className="flex items-center justify-between">
                    <span className="text-sm font-medium">Customer:</span>
                    <span className="font-bold">{selectedRequest.customer.name}</span>
                  </div>
                  <div className="flex items-center justify-between">
                    <span className="text-sm font-medium">Phone Number:</span>
                    <span className="font-bold">{selectedRequest.phone_number.formatted_number}</span>
                  </div>
                  <div className="flex items-center justify-between">
                    <span className="text-sm font-medium">Monthly Cost:</span>
                    <span className="font-bold">{formatCurrency(selectedRequest.phone_number.monthly_cost)}</span>
                  </div>
                </div>

                <div className="space-y-2">
                  <Label htmlFor="approve-notes">Admin Notes (Optional)</Label>
                  <Textarea
                    id="approve-notes"
                    placeholder="Add any notes for the customer..."
                    value={approveForm.data.admin_notes}
                    onChange={(e) => approveForm.setData('admin_notes', e.target.value)}
                    rows={3}
                  />
                  {approveForm.errors.admin_notes && (
                    <p className="text-sm text-destructive">{approveForm.errors.admin_notes}</p>
                  )}
                </div>

                <Alert>
                  <CheckCircle className="h-4 w-4" />
                  <AlertDescription>
                    The customer will receive an email notification and the number will be immediately available for use in their campaigns.
                  </AlertDescription>
                </Alert>
              </div>

              <DialogFooter>
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => setIsApproveModalOpen(false)}
                  disabled={approveForm.processing}
                >
                  Cancel
                </Button>
                <Button type="submit" disabled={approveForm.processing} className="bg-green-600 hover:bg-green-700">
                  {approveForm.processing ? 'Approving...' : 'Approve Request'}
                </Button>
              </DialogFooter>
            </form>
          )}
        </DialogContent>
      </Dialog>

      {/* Reject Request Modal */}
      <Dialog open={isRejectModalOpen} onOpenChange={setIsRejectModalOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Reject Number Request</DialogTitle>
            <DialogDescription>
              Decline this request and provide a reason for the customer
            </DialogDescription>
          </DialogHeader>
          
          {selectedRequest && (
            <form onSubmit={submitRejection}>
              <div className="space-y-4 py-4">
                <div className="rounded-lg border p-4 space-y-2">
                  <div className="flex items-center justify-between">
                    <span className="text-sm font-medium">Customer:</span>
                    <span className="font-bold">{selectedRequest.customer.name}</span>
                  </div>
                  <div className="flex items-center justify-between">
                    <span className="text-sm font-medium">Phone Number:</span>
                    <span className="font-bold">{selectedRequest.phone_number.formatted_number}</span>
                  </div>
                </div>

                <div className="space-y-2">
                  <Label htmlFor="reject-notes">Rejection Reason *</Label>
                  <Textarea
                    id="reject-notes"
                    placeholder="Explain why this request is being rejected..."
                    value={rejectForm.data.admin_notes}
                    onChange={(e) => rejectForm.setData('admin_notes', e.target.value)}
                    rows={4}
                    required
                  />
                  {rejectForm.errors.admin_notes && (
                    <p className="text-sm text-destructive">{rejectForm.errors.admin_notes}</p>
                  )}
                  <p className="text-xs text-muted-foreground">
                    This message will be sent to the customer via email.
                  </p>
                </div>

                <Alert variant="destructive">
                  <XCircle className="h-4 w-4" />
                  <AlertDescription>
                    The customer will be notified and the number will be returned to the available pool.
                  </AlertDescription>
                </Alert>
              </div>

              <DialogFooter>
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => setIsRejectModalOpen(false)}
                  disabled={rejectForm.processing}
                >
                  Cancel
                </Button>
                <Button 
                  type="submit" 
                  variant="destructive" 
                  disabled={rejectForm.processing || !rejectForm.data.admin_notes.trim()}
                >
                  {rejectForm.processing ? 'Rejecting...' : 'Reject Request'}
                </Button>
              </DialogFooter>
            </form>
          )}
        </DialogContent>
      </Dialog>
    </AppLayout>
  );
}
