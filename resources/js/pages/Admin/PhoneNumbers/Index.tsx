import AppLayout from '@/layouts/app-layout';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
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
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Link, router, Head, useForm, usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import { 
  Search, 
  Phone, 
  PhoneCall, 
  MessageSquare, 
  Filter,
  X,
  RefreshCw,
  TrendingUp,
  AlertCircle,
  Check,
  Settings,
  Loader2,
  Wallet,
  ShoppingCart
} from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import Heading from '@/components/heading';
import { PageHelp } from '@/components/page-help';
import { isHidden, providerLabel, useTelephony } from '@/lib/telephony';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';

interface PhoneNumber {
  id: number;
  number: string;
  formatted_number: string;
  friendly_name: string;
  country_code: string;
  area_code: string;
  status: 'available' | 'requested' | 'assigned' | 'released';
  capabilities: {
    voice: boolean;
    sms: boolean;
    mms: boolean;
  };
  monthly_cost: number;
  user_id: number | null;
  user?: {
    id: number;
    name: string;
    email: string;
  };
  requested_by: number | null;
  approved_by: number | null;
  requested_at: string | null;
  assigned_at: string | null;
  released_at: string | null;
  created_at: string;
}

/**
 * One number on the account this installation calls out of, as the server
 * reports it. The prop keeps its original name so a Twilio installation's
 * payload is untouched; on any other provider the same shape is filled in
 * by that provider's own listing.
 */
interface TwilioNumber {
  sid: string;
  phone_number: string;
  friendly_name: string;
  voice_application_sid: string | null;
  voice_url: string | null;
  status_callback: string | null;
  capabilities: {
    voice: boolean;
    sms: boolean;
    mms: boolean;
  };
  is_configured: boolean;
  assigned_to: {
    id: number;
    user_id: number;
    user_name: string | null;
    assigned_at: string;
  } | null;
}

interface TwilioConfig {
  account_sid: string;
  twiml_app_sid: string;
  webhook_url: string;
  is_active: boolean;
}

interface PaginatedNumbers {
  data: PhoneNumber[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

interface Stats {
  total: number;
  available: number;
  requested: number;
  assigned: number;
  released: number;
  total_monthly_cost: number;
}

interface Props {
  numbers: PaginatedNumbers;
  filters: {
    search?: string;
    status?: string;
    country_code?: string;
    area_code?: string;
  };
  stats: Stats;
  twilioNumbers?: TwilioNumber[];
  twilioConfig?: TwilioConfig | null;
  /** Whether the locked provider has credentials saved — the gate on this whole page. */
  providerConfigured?: boolean;
  users: Array<{
    id: number;
    name: string;
    email: string;
  }>;
}

interface AssignPriceInfo {
  needs_purchase: boolean;
  already_owned: boolean;
  monthly_cost: number;
  charge_amount: number;
  user_balance: number;
  can_afford: boolean;
  user_name: string;
  phone_number: string;
  country_code: string;
}

export default function AdminPhoneNumbers({ 
  numbers = { data: [], current_page: 1, last_page: 1, per_page: 15, total: 0 },
  filters = {},
  stats = { total: 0, available: 0, requested: 0, assigned: 0, released: 0, total_monthly_cost: 0 },
  twilioNumbers = [],
  twilioConfig = null,
  providerConfigured = false,
  users = [],
}: Props) {
  const { t } = useTranslation();
  // Which provider this installation is locked to. Every place this page
  // used to spell one provider's name into its own copy reads it from
  // here instead, the way the admin pricing pages do.
  const { provider, locked, hidden } = useTelephony();
  const providerName = locked ? providerLabel(provider) : 'your telephony provider';
  // Writing a voice URL and a status callback onto one rented number is a
  // provider-specific operation: where numbers answer through a single
  // application instead, that work is already done at purchase and there
  // is nothing here for an admin to run.
  const canConfigureNumbers = !isHidden('ai_agent_number_webhooks', hidden);
  // Refreshing monthly number costs reads a provider's own published
  // price list, and only Twilio's is wired up behind
  // /admin/numbers/sync-pricing. Offering the button elsewhere offers an
  // error.
  const canSyncPricing = provider === 'twilio';
  // Where this installation's telephony credentials are managed.
  const providerSettingsUrl = provider === 'plivo' ? '/admin/telephony' : '/settings/twilio';
  const { flash } = usePage().props as any;
  const [selectedNumber, setSelectedNumber] = useState<PhoneNumber | null>(null);
  const [isSyncModalOpen, setIsSyncModalOpen] = useState(false);
  const [isReleaseDialogOpen, setIsReleaseDialogOpen] = useState(false);
  const [isFilterSheetOpen, setIsFilterSheetOpen] = useState(false);
  const [isAssignModalOpen, setIsAssignModalOpen] = useState(false);
  const [isAssignConfirmOpen, setIsAssignConfirmOpen] = useState(false);
  const [selectedTwilioNumbers, setSelectedTwilioNumbers] = useState<string[]>([]);
  const [supportedCountries, setSupportedCountries] = useState<Array<{ code: string; name: string }>>([]);
  const [loadingCountries, setLoadingCountries] = useState(false);
  const [syncedNumbers, setSyncedNumbers] = useState<Array<any>>([]);
  const [syncError, setSyncError] = useState<string>('');
  const [assignPriceInfo, setAssignPriceInfo] = useState<AssignPriceInfo | null>(null);
  const [loadingAssignPrice, setLoadingAssignPrice] = useState(false);
  const [assignPriceError, setAssignPriceError] = useState<string | null>(null);
  const [skipCreditDeduction, setSkipCreditDeduction] = useState(false);
  const [assigningNumber, setAssigningNumber] = useState(false);
  const [syncingPricing, setSyncingPricing] = useState(false);
  
  const [localFilters, setLocalFilters] = useState({
    search: filters.search || '',
    status: filters.status || 'all',
    country_code: filters.country_code || '',
    area_code: filters.area_code || '',
  });

  const syncForm = useForm({
    country_code: 'US',
    area_code: '',
  });

  const assignForm = useForm({
    user_id: '',
    phone_number_id: '',
    phone_number: '',
    friendly_name: '',
  });

  // Fetch supported countries when sync modal opens.
  // /admin/numbers/supported-countries answers out of the Twilio
  // catalogue alone, so it is asked only where there is one; every other
  // installation falls through to the short built-in list below.
  useEffect(() => {
    if (isSyncModalOpen && twilioConfig && supportedCountries.length === 0) {
      setLoadingCountries(true);
      window.axios.get('/admin/numbers/supported-countries')
        .then(response => {
          setSupportedCountries(response.data.countries || []);
        })
        .catch(error => {
          console.error('Failed to fetch supported countries:', error);
          // Fallback to basic countries if API fails
          setSupportedCountries([
            { code: 'US', name: 'United States' },
            { code: 'GB', name: 'United Kingdom' },
            { code: 'CA', name: 'Canada' },
            { code: 'AU', name: 'Australia' },
          ]);
        })
        .finally(() => {
          setLoadingCountries(false);
        });
    }
  }, [isSyncModalOpen, twilioConfig, supportedCountries.length]);

  const handleSearch = () => {
    // Filter out 'all' values before sending to backend
    const searchParams = {
      ...localFilters,
      status: localFilters.status === 'all' ? '' : localFilters.status,
    };
    
    router.get('/admin/numbers', searchParams, {
      preserveState: true,
      preserveScroll: true,
    });
  };

  const handleClearFilters = () => {
    const clearedFilters = { search: '', status: 'all', country_code: '', area_code: '' };
    setLocalFilters(clearedFilters);
    router.get('/admin/numbers', {}, { preserveState: true, preserveScroll: true });
  };

  const handleSyncNumbers = async (e: React.FormEvent) => {
    e.preventDefault();
    setSyncError('');
    setSyncedNumbers([]);
    syncForm.clearErrors();

    try {
      const response = await window.axios.post('/admin/numbers/sync', {
        country_code: syncForm.data.country_code,
        area_code: syncForm.data.area_code,
      });

      if (response.data.success) {
        const numbers = response.data.numbers || [];
        
        // Check if no numbers were found
        if (numbers.length === 0) {
          setSyncError('No numbers found for the selected country/area code. Please try a different country or area code.');
        } else {
          setSyncedNumbers(numbers);
          // Reload the page data after 2 seconds to show updated list
          setTimeout(() => {
            router.reload({ only: ['phoneNumbers', 'statistics'] });
          }, 2000);
        }
      }
    } catch (error: any) {
      const errorMessage = error.response?.data?.message || error.message || 'Failed to sync numbers';
      setSyncError(errorMessage);
    }
  };

  const handleCloseSyncModal = () => {
    setIsSyncModalOpen(false);
    setSyncedNumbers([]);
    setSyncError('');
    syncForm.reset();
  };

  const handleSyncPricing = async () => {
    setSyncingPricing(true);
    try {
      const response = await window.axios.post('/admin/numbers/sync-pricing');
      
      if (response.data.success) {
        // Show success message and reload page
        alert(`Successfully updated pricing for ${response.data.updated} phone numbers!`);
        router.reload({ only: ['numbers', 'stats'] });
      } else {
        alert(response.data.message || 'Failed to sync pricing');
      }
    } catch (error: any) {
      const errorMessage = error.response?.data?.message || 'Failed to sync pricing';
      alert(errorMessage);
    } finally {
      setSyncingPricing(false);
    }
  };

  const handleReleaseNumber = () => {
    if (!selectedNumber) return;
    
    router.post(`/admin/numbers/${selectedNumber.id}/revoke`, {}, {
      onSuccess: () => {
        setIsReleaseDialogOpen(false);
        setSelectedNumber(null);
      },
      onError: () => {
        // Keep dialog open on error so user can try again or cancel
        // The error message will be shown via flash message
      },
    });
  };

  const handleOpenAssignModal = (number?: PhoneNumber) => {
    if (number) {
      assignForm.setData({
        user_id: '',
        phone_number_id: number.id.toString(),
        phone_number: '',
        friendly_name: number.friendly_name || '',
      });
      setSelectedNumber(number);
    } else {
      assignForm.reset();
      setSelectedNumber(null);
    }
    setIsAssignModalOpen(true);
  };

  const handleAssignNumber = async (e: React.FormEvent) => {
    e.preventDefault();
    
    // Validate that user is selected
    if (!assignForm.data.user_id) {
      return; // Form validation will show error
    }
    
    // Validate that we have either a phone_number_id or phone_number
    if (!assignForm.data.phone_number_id && !assignForm.data.phone_number) {
      return; // Form validation will show error
    }
    
    // Fetch price info before showing confirmation
    setIsAssignModalOpen(false);
    setIsAssignConfirmOpen(true);
    setLoadingAssignPrice(true);
    setAssignPriceInfo(null);
    setAssignPriceError(null);
    setSkipCreditDeduction(false);

    try {
      const response = await window.axios.post('/admin/numbers/assign-price', {
        user_id: assignForm.data.user_id,
        phone_number_id: assignForm.data.phone_number_id || undefined,
        phone_number: assignForm.data.phone_number || undefined,
      });
      setAssignPriceInfo(response.data);
    } catch (error: any) {
      setAssignPriceError(
        error.response?.data?.message || 'Failed to fetch pricing information. Please try again.'
      );
    } finally {
      setLoadingAssignPrice(false);
    }
  };

  const confirmAssignNumber = () => {
    const formData = {
      ...assignForm.data,
      skip_credit_deduction: skipCreditDeduction,
    };

    setAssigningNumber(true);
    router.post('/admin/numbers/assign', formData, {
      onSuccess: () => {
        setIsAssignConfirmOpen(false);
        assignForm.reset();
        setSelectedNumber(null);
        setAssignPriceInfo(null);
        setSkipCreditDeduction(false);
      },
      onError: () => {
        // On error, show the assign modal again
        setIsAssignConfirmOpen(false);
        setIsAssignModalOpen(true);
        setAssignPriceInfo(null);
      },
      onFinish: () => {
        setAssigningNumber(false);
      },
    });
  };

  const handleConfigureTwilioNumbers = () => {
    if (selectedTwilioNumbers.length === 0) {
      alert('Please select at least one phone number');
      return;
    }

    router.post('/admin/numbers/configure', {
      phone_numbers: selectedTwilioNumbers,
    }, {
      onSuccess: () => {
        setSelectedTwilioNumbers([]);
      },
    });
  };

  const toggleTwilioNumber = (phoneNumber: string) => {
    setSelectedTwilioNumbers(prev =>
      prev.includes(phoneNumber)
        ? prev.filter(p => p !== phoneNumber)
        : [...prev, phoneNumber]
    );
  };

  const selectAllTwilioNumbers = () => {
    if (selectedTwilioNumbers.length === twilioNumbers.length) {
      setSelectedTwilioNumbers([]);
    } else {
      setSelectedTwilioNumbers(twilioNumbers.map(n => n.phone_number));
    }
  };

  const getStatusColor = (status: string): "default" | "secondary" | "destructive" | "outline" => {
    switch (status) {
      case 'available': return 'default';
      case 'requested': return 'secondary';
      case 'assigned': return 'outline';
      case 'released': return 'destructive';
      default: return 'outline';
    }
  };

  const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
    }).format(amount);
  };

  const breadcrumbs: BreadcrumbItem[] = [
    { title: t('common.dashboard'), href: dashboard().url },
    { title: t('admin.phone_numbers.phone_numbers'), href: '/admin/numbers' },
  ];

  const helpSections = [
    {
      title: 'Phone Number Inventory',
      content: `Manage all phone numbers purchased from ${providerName}. View availability, assign numbers to users, and sync new numbers from your ${providerName} account.`,
    },
    {
      title: 'Syncing Numbers',
      content: `Click "Sync Numbers" to fetch available phone numbers from ${providerName}. Select a country and optionally specify an area code to find numbers.`,
    },
    {
      title: 'Number Status',
      content: 'Available: Ready to assign. Requested: Pending approval. Assigned: Currently in use by a customer. Released: Returned to inventory.',
    },
    {
      title: 'Assigning Numbers',
      content: 'Approve number requests from customers or manually assign available numbers to users. Numbers can only be assigned when available.',
    },
    {
      title: 'Number Capabilities',
      content: 'Each number shows its capabilities: Voice (calling), SMS (text messages), MMS (multimedia messages). Choose numbers with required capabilities.',
    },
    {
      title: 'Monthly Costs',
      content: `View individual number costs and total monthly expenses. Costs are charged by ${providerName} based on the number's capabilities and location.`,
    },
  ];

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={t('admin.phone_numbers.phone_numbers_admin')} />
      
      <div className="mx-auto max-w-7xl space-y-6">
        {/* Flash Messages */}
        {flash?.success && (
          <Alert className="border-green-600 bg-green-50 dark:bg-green-950/20">
            <Check className="h-4 w-4 text-green-600" />
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
            title={t('admin.phone_numbers.phone_number_inventory')}
            description={t('admin.phone_numbers.manage_phone_numbers_and_sync_from_provider', {
              provider: providerName,
              defaultValue: 'Manage phone numbers and sync from {{provider}}',
            })}
          />
          <div className="flex gap-2">
            <PageHelp title={t('admin.phone_numbers.phone_numbers_help')} sections={helpSections} />
            <Button variant="outline" asChild>
              <Link href="/admin/number-requests">
                View Requests
              </Link>
            </Button>
            <Button variant="outline" onClick={() => handleOpenAssignModal()}>
              <Phone className="mr-2 h-4 w-4" />
              Assign Number
            </Button>
            {canSyncPricing && (
              <Button variant="outline" onClick={handleSyncPricing} disabled={syncingPricing}>
                {syncingPricing ? (
                  <>
                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                    Syncing...
                  </>
                ) : (
                  <>
                    <TrendingUp className="mr-2 h-4 w-4" />
                    Sync Price
                  </>
                )}
              </Button>
            )}
            <Button onClick={() => setIsSyncModalOpen(true)}>
              <RefreshCw className="mr-2 h-4 w-4" />
              Sync Numbers
            </Button>
          </div>
        </div>

        {providerConfigured && (
          <>
            {/* Stats Cards */}
            <div className="grid gap-4 md:grid-cols-5">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">{t('admin.phone_numbers.total_numbers')}</CardTitle>
              <Phone className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{stats.total}</div>
              <p className="text-xs text-muted-foreground">
                In inventory
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">{t('admin.phone_numbers.available')}</CardTitle>
              <Phone className="h-4 w-4 text-green-600" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-green-600">{stats.available}</div>
              <p className="text-xs text-muted-foreground">
                Ready to assign
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">{t('admin.phone_numbers.requested')}</CardTitle>
              <Phone className="h-4 w-4 text-yellow-600" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-yellow-600">{stats.requested}</div>
              <p className="text-xs text-muted-foreground">
                Pending approval
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">{t('admin.phone_numbers.assigned')}</CardTitle>
              <Phone className="h-4 w-4 text-blue-600" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold text-blue-600">{stats.assigned}</div>
              <p className="text-xs text-muted-foreground">
                In use
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">{t('admin.phone_numbers.monthly_cost')}</CardTitle>
              <TrendingUp className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{formatCurrency(stats.total_monthly_cost)}</div>
              <p className="text-xs text-muted-foreground">
                Assigned numbers
              </p>
            </CardContent>
          </Card>
        </div>

        {/* The numbers already on the provider's account */}
        {twilioNumbers && twilioNumbers.length > 0 && (
          <Card>
            <CardHeader>
              <div className="flex items-center justify-between">
                <div>
                  <CardTitle>
                    {t('admin.phone_numbers.provider_active_numbers', {
                      provider: providerName,
                      defaultValue: '{{provider}} Active Numbers',
                    })}
                  </CardTitle>
                  <CardDescription>
                    {canConfigureNumbers
                      ? `Configure your ${providerName} phone numbers for WebRTC calling`
                      : `The phone numbers on your ${providerName} account`}
                  </CardDescription>
                </div>
                <div className="flex gap-2">
                  <Button variant="outline" asChild>
                    <Link href={providerSettingsUrl}>
                      <Settings className="mr-2 h-4 w-4" />
                      {providerName} Settings
                    </Link>
                  </Button>
                  {canConfigureNumbers && (
                    <Button 
                      onClick={handleConfigureTwilioNumbers}
                      disabled={selectedTwilioNumbers.length === 0}
                    >
                      Configure Selected ({selectedTwilioNumbers.length})
                    </Button>
                  )}
                </div>
              </div>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead className="w-12">
                      <Checkbox
                        checked={selectedTwilioNumbers.length === twilioNumbers.length && twilioNumbers.length > 0}
                        onCheckedChange={selectAllTwilioNumbers}
                      />
                    </TableHead>
                    <TableHead>Phone Number</TableHead>
                    <TableHead>Capabilities</TableHead>
                    <TableHead>Configuration Status</TableHead>
                    <TableHead>Assigned To</TableHead>
                    <TableHead className="text-right">Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {twilioNumbers.map((number) => {
                    // Use the assigned_to data from backend instead of searching paginated results
                    const isAssigned = !!number.assigned_to;
                    
                    return (
                      <TableRow key={number.sid} className={isAssigned ? 'opacity-50' : ''}>
                        <TableCell>
                          <Checkbox
                            checked={selectedTwilioNumbers.includes(number.phone_number)}
                            onCheckedChange={() => toggleTwilioNumber(number.phone_number)}
                            disabled={isAssigned}
                          />
                        </TableCell>
                        <TableCell>
                          <div className="flex flex-col">
                            <span className="font-medium">{number.phone_number}</span>
                            {number.friendly_name && (
                              <span className="text-sm text-muted-foreground">{number.friendly_name}</span>
                            )}
                          </div>
                        </TableCell>
                      <TableCell>
                        <div className="flex gap-1">
                          {number.capabilities.voice && (
                            <Badge variant="secondary" className="text-xs">
                              <PhoneCall className="mr-1 h-3 w-3" />
                              Voice
                            </Badge>
                          )}
                          {number.capabilities.sms && (
                            <Badge variant="secondary" className="text-xs">
                              <MessageSquare className="mr-1 h-3 w-3" />
                              SMS
                            </Badge>
                          )}
                        </div>
                      </TableCell>
                      <TableCell>
                        {number.is_configured ? (
                          <Badge variant="default" className="gap-1">
                            <Check className="h-3 w-3" />
                            Configured for WebRTC
                          </Badge>
                        ) : (
                          <Badge variant="secondary" className="gap-1">
                            <X className="h-3 w-3" />
                            Not Configured
                          </Badge>
                        )}
                      </TableCell>
                      <TableCell>
                        {isAssigned && number.assigned_to ? (
                          <div className="flex items-center gap-2">
                            <Badge variant="default" className="gap-1">
                              <Check className="h-3 w-3" />
                              {number.assigned_to.user_name || 'User'}
                            </Badge>
                          </div>
                        ) : (
                          <span className="text-sm text-muted-foreground">-</span>
                        )}
                      </TableCell>
                      <TableCell className="text-right">
                        {!isAssigned && (
                          <Button
                            variant="outline"
                            size="sm"
                            onClick={() => {
                              assignForm.setData({
                                user_id: '',
                                phone_number_id: '',
                                phone_number: number.phone_number,
                                friendly_name: number.friendly_name || '',
                              });
                              setSelectedNumber(null);
                              setIsAssignModalOpen(true);
                            }}
                          >
                            <Phone className="mr-2 h-3 w-3" />
                            Assign
                          </Button>
                        )}
                      </TableCell>
                    </TableRow>
                    );
                  })}
                </TableBody>
              </Table>
              {canConfigureNumbers && (
                <div className="mt-4 text-sm text-muted-foreground">
                  Select phone numbers and click "Configure Selected" to set them up for inbound and outbound calling.
                </div>
              )}
            </CardContent>
          </Card>
        )}
          </>
        )}

        {!providerConfigured ? (
          <Alert>
            <AlertCircle className="h-4 w-4" />
            <AlertDescription>
              {providerName} not configured. <Link href={providerSettingsUrl} className="underline">Configure {providerName}</Link> to manage your active phone numbers.
            </AlertDescription>
          </Alert>
        ) : (
          <>
            {/* Filters */}
            <Card>
              <CardHeader>
                <div className="flex items-center justify-between">
                  <div>
                    <CardTitle>{t('admin.phone_numbers.search_filter')}</CardTitle>
                    <CardDescription>Find phone numbers by status, country, or area code</CardDescription>
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
                    <SheetTitle>Filter Numbers</SheetTitle>
                    <SheetDescription>Narrow down the inventory</SheetDescription>
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
                          <SelectItem value="available">Available</SelectItem>
                          <SelectItem value="requested">Requested</SelectItem>
                          <SelectItem value="assigned">Assigned</SelectItem>
                          <SelectItem value="released">Released</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                    
                    <div className="space-y-2">
                      <Label htmlFor="filter-country">Country Code</Label>
                      <Input
                        id="filter-country"
                        placeholder="US, GB, CA..."
                        value={localFilters.country_code}
                        onChange={(e) => setLocalFilters({ ...localFilters, country_code: e.target.value })}
                      />
                    </div>

                    <div className="space-y-2">
                      <Label htmlFor="filter-area">Area Code</Label>
                      <Input
                        id="filter-area"
                        placeholder="415, 510, 212..."
                        value={localFilters.area_code}
                        onChange={(e) => setLocalFilters({ ...localFilters, area_code: e.target.value })}
                      />
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
                  placeholder="Search by number..."
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

        {/* Numbers Table */}
        <Card>
          <CardHeader>
            <CardTitle>Numbers ({numbers.total})</CardTitle>
            <CardDescription>All phone numbers in the system</CardDescription>
          </CardHeader>
          <CardContent>
            {numbers.data.length === 0 ? (
              <Alert>
                <AlertCircle className="h-4 w-4" />
                <AlertDescription>
                  No phone numbers found. Click "Sync Numbers" to import numbers from {providerName}.
                </AlertDescription>
              </Alert>
            ) : (
              <>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Phone Number</TableHead>
                      <TableHead>Country/Area</TableHead>
                      <TableHead>Capabilities</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead>Assigned To</TableHead>
                      <TableHead>Monthly Cost</TableHead>
                      <TableHead className="text-right">Actions</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {numbers.data.map((number) => (
                      <TableRow key={number.id}>
                        <TableCell>
                          <div className="flex flex-col">
                            <span className="font-medium">{number.formatted_number}</span>
                            <span className="text-sm text-muted-foreground">{number.friendly_name}</span>
                          </div>
                        </TableCell>
                        <TableCell>
                          <div className="flex flex-col gap-1">
                            <Badge variant="outline" className="w-fit">{number.country_code}</Badge>
                            <span className="text-sm text-muted-foreground">{number.area_code}</span>
                          </div>
                        </TableCell>
                        <TableCell>
                          <div className="flex gap-1">
                            {number.capabilities.voice && (
                              <Badge variant="secondary" className="text-xs">
                                <PhoneCall className="mr-1 h-3 w-3" />
                                Voice
                              </Badge>
                            )}
                            {number.capabilities.sms && (
                              <Badge variant="secondary" className="text-xs">
                                <MessageSquare className="mr-1 h-3 w-3" />
                                SMS
                              </Badge>
                            )}
                          </div>
                        </TableCell>
                        <TableCell>
                          <Badge variant={getStatusColor(number.status)}>
                            {number.status}
                          </Badge>
                        </TableCell>
                        <TableCell>
                          {number.user ? (
                            <div className="flex flex-col">
                              <span className="text-sm font-medium">{number.user.name}</span>
                              <span className="text-xs text-muted-foreground">{number.user.email}</span>
                            </div>
                          ) : (
                            <span className="text-sm text-muted-foreground">—</span>
                          )}
                        </TableCell>
                        <TableCell className="font-medium">
                          {formatCurrency(number.monthly_cost)}
                        </TableCell>
                        <TableCell className="text-right">
                          <div className="flex justify-end gap-2">
                            {number.status === 'available' && (
                              <Button
                                variant="outline"
                                size="sm"
                                onClick={() => handleOpenAssignModal(number)}
                              >
                                <Phone className="mr-2 h-3 w-3" />
                                Assign
                              </Button>
                            )}
                            {number.status === 'assigned' && (
                              <Button
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                  setSelectedNumber(number);
                                  setIsReleaseDialogOpen(true);
                                }}
                              >
                                <X className="mr-2 h-3 w-3" />
                                Revoke
                              </Button>
                            )}
                          </div>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>

                {/* Pagination */}
                {numbers.last_page > 1 && (
                  <div className="flex items-center justify-between pt-4">
                    <div className="text-sm text-muted-foreground">
                      Page {numbers.current_page} of {numbers.last_page}
                    </div>
                    <div className="flex gap-2">
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => {
                          const paginationFilters = {
                            ...localFilters,
                            status: localFilters.status === 'all' ? '' : localFilters.status,
                            page: numbers.current_page - 1
                          };
                          router.get('/admin/numbers', paginationFilters, { preserveState: true, preserveScroll: true });
                        }}
                        disabled={numbers.current_page === 1}
                      >
                        Previous
                      </Button>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => {
                          const paginationFilters = {
                            ...localFilters,
                            status: localFilters.status === 'all' ? '' : localFilters.status,
                            page: numbers.current_page + 1
                          };
                          router.get('/admin/numbers', paginationFilters, { preserveState: true, preserveScroll: true });
                        }}
                        disabled={numbers.current_page === numbers.last_page}
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
          </>
        )}
      </div>

      {/* Sync Numbers Modal */}
      <Dialog open={isSyncModalOpen} onOpenChange={handleCloseSyncModal}>
        <DialogContent className="max-w-2xl max-h-[80vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>Sync Phone Numbers</DialogTitle>
            <DialogDescription>
              Import available phone numbers from {providerName}
            </DialogDescription>
          </DialogHeader>
          
          {syncedNumbers.length > 0 ? (
            // Success view - show synced numbers
            <div className="space-y-4 py-4">
              <Alert className="border-green-500 bg-green-50">
                <Check className="h-4 w-4 text-green-600" />
                <AlertDescription className="text-green-800">
                  Successfully synced {syncedNumbers.length} phone number{syncedNumbers.length !== 1 ? 's' : ''} from {providerName}!
                </AlertDescription>
              </Alert>

              <div className="space-y-2">
                <h4 className="font-medium text-sm">Synced Numbers:</h4>
                <div className="border rounded-md max-h-96 overflow-y-auto">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Phone Number</TableHead>
                        <TableHead>Area Code</TableHead>
                        <TableHead>Capabilities</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {syncedNumbers.map((num, index) => (
                        <TableRow key={index}>
                          <TableCell className="font-mono text-sm">
                            {num.formatted_number || num.number}
                          </TableCell>
                          <TableCell>{num.area_code || 'N/A'}</TableCell>
                          <TableCell>
                            <div className="flex gap-1">
                              {num.capabilities?.voice && (
                                <Badge variant="secondary" className="text-xs">
                                  <PhoneCall className="h-3 w-3 mr-1" />
                                  Voice
                                </Badge>
                              )}
                              {num.capabilities?.sms && (
                                <Badge variant="secondary" className="text-xs">
                                  <MessageSquare className="h-3 w-3 mr-1" />
                                  SMS
                                </Badge>
                              )}
                            </div>
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </div>
              </div>

              <DialogFooter>
                <Button onClick={handleCloseSyncModal}>
                  Close
                </Button>
              </DialogFooter>
            </div>
          ) : (
            // Form view - sync form
            <form onSubmit={handleSyncNumbers}>
              <div className="space-y-4 py-4">
                {syncError && (
                  <Alert variant="destructive">
                    <AlertCircle className="h-4 w-4" />
                    <AlertDescription>{syncError}</AlertDescription>
                  </Alert>
                )}

                <div className="space-y-2">
                  <Label htmlFor="country">Country Code *</Label>
                  <Select
                    value={syncForm.data.country_code}
                    onValueChange={(value) => syncForm.setData('country_code', value)}
                    disabled={loadingCountries || syncForm.processing}
                  >
                    <SelectTrigger id="country">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      {loadingCountries ? (
                        <SelectItem value="loading" disabled>Loading countries...</SelectItem>
                      ) : supportedCountries.length > 0 ? (
                        supportedCountries.map(country => (
                          <SelectItem key={country.code} value={country.code}>
                            {country.name} ({country.code})
                          </SelectItem>
                        ))
                      ) : (
                        <>
                          <SelectItem value="US">United States (US)</SelectItem>
                          <SelectItem value="GB">United Kingdom (GB)</SelectItem>
                          <SelectItem value="CA">Canada (CA)</SelectItem>
                          <SelectItem value="AU">Australia (AU)</SelectItem>
                        </>
                      )}
                    </SelectContent>
                  </Select>
                  {syncForm.errors.country_code && (
                    <p className="text-sm text-destructive">{syncForm.errors.country_code}</p>
                  )}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="sync-area">Area Code (Optional)</Label>
                  <Input
                    id="sync-area"
                    placeholder="415, 510, 212..."
                    value={syncForm.data.area_code}
                    onChange={(e) => syncForm.setData('area_code', e.target.value)}
                    disabled={syncForm.processing}
                  />
                  {syncForm.errors.area_code && (
                    <p className="text-sm text-destructive">{syncForm.errors.area_code}</p>
                  )}
                  <p className="text-xs text-muted-foreground">
                    Leave blank to fetch numbers from all area codes
                  </p>
                </div>

                <Alert>
                  <RefreshCw className="h-4 w-4" />
                  <AlertDescription>
                    This will fetch up to 50 available phone numbers from {providerName} and add them to your inventory. Duplicate numbers will be skipped.
                  </AlertDescription>
                </Alert>
              </div>

              <DialogFooter>
                <Button
                  type="button"
                  variant="outline"
                  onClick={handleCloseSyncModal}
                  disabled={syncForm.processing}
                >
                  Cancel
                </Button>
                <Button type="submit" disabled={syncForm.processing}>
                  {syncForm.processing ? 'Syncing...' : 'Sync Numbers'}
                </Button>
              </DialogFooter>
            </form>
          )}
        </DialogContent>
      </Dialog>

      {/* Revoke Number Confirmation */}
      <Dialog 
        open={isReleaseDialogOpen} 
        onOpenChange={(open) => {
          setIsReleaseDialogOpen(open);
          if (!open) {
            setSelectedNumber(null);
          }
        }}
      >
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Revoke Phone Number Assignment</DialogTitle>
            <DialogDescription>
              This will unassign the phone number from the user and return it to your available inventory.
            </DialogDescription>
          </DialogHeader>
          
          {selectedNumber && (
            <div className="space-y-4">
              <div className="rounded-lg border p-4 space-y-2">
                <div className="flex items-center justify-between">
                  <span className="text-sm font-medium">Number:</span>
                  <span className="font-bold">{selectedNumber.formatted_number}</span>
                </div>
                {selectedNumber.user && (
                  <div className="flex items-center justify-between">
                    <span className="text-sm font-medium">Currently Assigned To:</span>
                    <div className="text-right">
                      <div className="font-medium">{selectedNumber.user.name}</div>
                      <div className="text-xs text-muted-foreground">{selectedNumber.user.email}</div>
                    </div>
                  </div>
                )}
                <div className="flex items-center justify-between">
                  <span className="text-sm font-medium">Friendly Name:</span>
                  <span>{selectedNumber.friendly_name}</span>
                </div>
              </div>

              <Alert>
                <AlertCircle className="h-4 w-4" />
                <AlertDescription>
                  The phone number will remain in your inventory and can be reassigned to another user later. The number will NOT be deleted from your account.
                </AlertDescription>
              </Alert>
            </div>
          )}

          <DialogFooter>
            <Button variant="outline" onClick={() => setIsReleaseDialogOpen(false)}>
              Cancel
            </Button>
            <Button variant="default" onClick={handleReleaseNumber} disabled={!selectedNumber}>
              Revoke Assignment
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Assign Number Confirmation */}
      <Dialog 
        open={isAssignConfirmOpen} 
        onOpenChange={(open) => {
          if (!open && !assigningNumber) {
            // If closing, go back to assign modal
            setIsAssignConfirmOpen(false);
            setIsAssignModalOpen(true);
            setAssignPriceInfo(null);
            setSkipCreditDeduction(false);
          }
        }}
      >
        <DialogContent>
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <ShoppingCart className="h-5 w-5" />
              Confirm Phone Number Assignment
            </DialogTitle>
            <DialogDescription>
              Review assignment details and pricing before confirming.
            </DialogDescription>
          </DialogHeader>
          
          <div className="space-y-4">
            {/* Basic Assignment Details */}
            <div className="rounded-lg border p-4 space-y-3">
              <div className="flex items-center justify-between">
                <span className="text-sm font-medium">Assigning To:</span>
                <div className="text-right">
                  {users.find(u => u.id.toString() === assignForm.data.user_id) && (
                    <>
                      <div className="font-medium">
                        {users.find(u => u.id.toString() === assignForm.data.user_id)?.name}
                      </div>
                      <div className="text-xs text-muted-foreground">
                        {users.find(u => u.id.toString() === assignForm.data.user_id)?.email}
                      </div>
                    </>
                  )}
                </div>
              </div>
              
              <div className="flex items-center justify-between border-t pt-3">
                <span className="text-sm font-medium">Phone Number:</span>
                <span className="font-bold">
                  {selectedNumber ? selectedNumber.formatted_number : assignForm.data.phone_number}
                </span>
              </div>
              
              {assignForm.data.friendly_name && (
                <div className="flex items-center justify-between">
                  <span className="text-sm font-medium">Friendly Name:</span>
                  <span>{assignForm.data.friendly_name}</span>
                </div>
              )}

              {selectedNumber && (
                <div className="flex items-center gap-1 pt-2">
                  <span className="text-sm font-medium">Capabilities:</span>
                  <div className="flex gap-1">
                    {selectedNumber.capabilities.voice && (
                      <Badge variant="secondary" className="text-xs">Voice</Badge>
                    )}
                    {selectedNumber.capabilities.sms && (
                      <Badge variant="secondary" className="text-xs">SMS</Badge>
                    )}
                    {selectedNumber.capabilities.mms && (
                      <Badge variant="secondary" className="text-xs">MMS</Badge>
                    )}
                  </div>
                </div>
              )}
            </div>

            {/* Pricing Details */}
            {loadingAssignPrice ? (
              <div className="flex items-center justify-center py-6">
                <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
                <span className="ml-2 text-sm text-muted-foreground">Checking pricing and availability...</span>
              </div>
            ) : assignPriceError ? (
              <Alert variant="destructive">
                <AlertCircle className="h-4 w-4" />
                <AlertDescription>{assignPriceError}</AlertDescription>
              </Alert>
            ) : assignPriceInfo ? (
              <div className="rounded-lg border bg-muted/30 p-4 space-y-3">
                {/* Purchase Status */}
                {assignPriceInfo.needs_purchase ? (
                  <Alert>
                    <ShoppingCart className="h-4 w-4" />
                    <AlertDescription>
                      <strong>{providerName} Purchase Required:</strong> This number is not yet owned on {providerName}. 
                      It will be purchased and configured for WebRTC automatically.
                    </AlertDescription>
                  </Alert>
                ) : assignPriceInfo.already_owned ? (
                  <Alert className="border-green-600 bg-green-50 dark:bg-green-950/20">
                    <Check className="h-4 w-4 text-green-600" />
                    <AlertDescription className="text-green-800 dark:text-green-200">
                      This number is already owned on {providerName}. No purchase needed.
                    </AlertDescription>
                  </Alert>
                ) : null}

                {/* Cost Breakdown */}
                <div className="flex items-center justify-between">
                  <span className="text-sm font-medium">Charge to User:</span>
                  <span className="text-lg font-bold">{new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(assignPriceInfo.charge_amount)}</span>
                </div>
                <div className="flex items-center justify-between">
                  <span className="text-sm text-muted-foreground flex items-center gap-1">
                    <Wallet className="h-3.5 w-3.5" /> User Balance:
                  </span>
                  <span className={`font-medium ${
                    assignPriceInfo.can_afford ? 'text-green-600' : 'text-destructive'
                  }`}>
                    {new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(assignPriceInfo.user_balance)}
                  </span>
                </div>
                {assignPriceInfo.charge_amount > 0 && (
                  <div className="flex items-center justify-between">
                    <span className="text-sm text-muted-foreground">Balance After:</span>
                    <span className={`font-medium ${
                      assignPriceInfo.can_afford ? '' : 'text-destructive'
                    }`}>
                      {new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(assignPriceInfo.user_balance - assignPriceInfo.charge_amount)}
                    </span>
                  </div>
                )}

                {/* Insufficient Balance Warning */}
                {!assignPriceInfo.can_afford && !skipCreditDeduction && (
                  <Alert variant="destructive" className="mt-2">
                    <AlertCircle className="h-4 w-4" />
                    <AlertDescription>
                      User has insufficient balance ({new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(assignPriceInfo.user_balance)}) 
                      for this charge ({new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(assignPriceInfo.charge_amount)}). 
                      You can skip credit deduction below to assign anyway.
                    </AlertDescription>
                  </Alert>
                )}

                {/* Skip Credit Deduction Checkbox */}
                <div className="flex items-center space-x-2 pt-2 border-t">
                  <Checkbox 
                    id="skip_credit" 
                    checked={skipCreditDeduction}
                    onCheckedChange={(checked) => setSkipCreditDeduction(checked === true)}
                  />
                  <Label htmlFor="skip_credit" className="text-sm cursor-pointer">
                    Skip credit deduction (assign for free)
                  </Label>
                </div>
                {skipCreditDeduction && (
                  <p className="text-xs text-muted-foreground ml-6">
                    No credits will be deducted from the user's balance.
                  </p>
                )}
              </div>
            ) : null}
          </div>

          <DialogFooter>
            <Button 
              variant="outline" 
              onClick={() => {
                setIsAssignConfirmOpen(false);
                setIsAssignModalOpen(true);
                setAssignPriceInfo(null);
                setSkipCreditDeduction(false);
              }}
              disabled={assigningNumber}
            >
              Go Back
            </Button>
            <Button 
              onClick={confirmAssignNumber}
              disabled={
                assigningNumber || 
                loadingAssignPrice || 
                !!assignPriceError ||
                (assignPriceInfo !== null && !assignPriceInfo.can_afford && !skipCreditDeduction)
              }
            >
              {assigningNumber ? (
                <><Loader2 className="mr-2 h-4 w-4 animate-spin" /> Assigning...</>
              ) : (
                'Confirm Assignment'
              )}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Assign Number Modal */}
      <Dialog open={isAssignModalOpen} onOpenChange={setIsAssignModalOpen}>
        <DialogContent className="max-w-2xl">
          <DialogHeader>
            <DialogTitle>Assign Phone Number</DialogTitle>
            <DialogDescription>
              Assign an existing number or purchase a new one from {providerName} and assign it to a user
            </DialogDescription>
          </DialogHeader>
          
          <form onSubmit={handleAssignNumber}>
            <div className="space-y-4 py-4">
              {/* User Selection */}
              <div className="space-y-2">
                <Label htmlFor="user">Select User *</Label>
                <Select
                  value={assignForm.data.user_id}
                  onValueChange={(value) => assignForm.setData('user_id', value)}
                  required
                >
                  <SelectTrigger id="user">
                    <SelectValue placeholder="Choose a user to assign the number to" />
                  </SelectTrigger>
                  <SelectContent>
                    {users.map((user) => (
                      <SelectItem key={user.id} value={user.id.toString()}>
                        {user.name} ({user.email})
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                {assignForm.errors.user_id && (
                  <p className="text-sm text-destructive">{assignForm.errors.user_id}</p>
                )}
              </div>

              {/* Number Assignment Method */}
              <div className="space-y-4 rounded-lg border p-4">
                <Label>Assignment Method</Label>
                
                {/* Option 1: Existing Number */}
                {selectedNumber && (
                  <div className="space-y-2">
                    <div className="flex items-center justify-between rounded-lg bg-muted p-3">
                      <div>
                        <p className="font-medium">{selectedNumber.formatted_number}</p>
                        <p className="text-sm text-muted-foreground">
                          {selectedNumber.friendly_name}
                        </p>
                        <div className="mt-1 flex gap-1">
                          {selectedNumber.capabilities.voice && (
                            <Badge variant="secondary" className="text-xs">Voice</Badge>
                          )}
                          {selectedNumber.capabilities.sms && (
                            <Badge variant="secondary" className="text-xs">SMS</Badge>
                          )}
                          {selectedNumber.capabilities.mms && (
                            <Badge variant="secondary" className="text-xs">MMS</Badge>
                          )}
                        </div>
                      </div>
                      <Badge variant="default">Selected</Badge>
                    </div>
                    <p className="text-xs text-muted-foreground">
                      This available number will be assigned to the selected user
                    </p>
                  </div>
                )}

                {/* Option 2: a number bought from the provider */}
                {!selectedNumber && (
                  <div className="space-y-3">
                    <div className="space-y-2">
                      <Label htmlFor="phone_number">Phone Number (E.164 format) *</Label>
                      <Input
                        id="phone_number"
                        placeholder="+14155551234"
                        value={assignForm.data.phone_number}
                        onChange={(e) => assignForm.setData('phone_number', e.target.value)}
                        required={!selectedNumber}
                      />
                      {assignForm.errors.phone_number && (
                        <p className="text-sm text-destructive">{assignForm.errors.phone_number}</p>
                      )}
                      <p className="text-xs text-muted-foreground">
                        Enter a phone number to purchase from {providerName} (must start with +)
                      </p>
                    </div>

                    <div className="space-y-2">
                      <Label htmlFor="friendly_name">Friendly Name (Optional)</Label>
                      <Input
                        id="friendly_name"
                        placeholder="e.g., San Francisco Office"
                        value={assignForm.data.friendly_name}
                        onChange={(e) => assignForm.setData('friendly_name', e.target.value)}
                      />
                      <p className="text-xs text-muted-foreground">
                        Give this number a memorable name
                      </p>
                    </div>

                    <Alert>
                      <AlertCircle className="h-4 w-4" />
                      <AlertDescription>
                        <strong>Purchase & Assign:</strong> If this number doesn't exist in your inventory, 
                        it will be purchased from {providerName} (charges apply), configured for WebRTC, and assigned to the user.
                      </AlertDescription>
                    </Alert>
                  </div>
                )}
              </div>

              {/* Summary */}
              <Alert>
                <Check className="h-4 w-4" />
                <AlertDescription>
                  The number will be immediately assigned and available in the user's softphone number switcher.
                </AlertDescription>
              </Alert>
            </div>

            <DialogFooter>
              <Button
                type="button"
                variant="outline"
                onClick={() => {
                  setIsAssignModalOpen(false);
                  assignForm.reset();
                  setSelectedNumber(null);
                }}
                disabled={assignForm.processing}
              >
                Cancel
              </Button>
              <Button type="submit" disabled={assignForm.processing || !assignForm.data.user_id}>
                {assignForm.processing ? 'Assigning...' : 'Assign Number'}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </AppLayout>
  );
}
