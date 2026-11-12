import AppLayout from '@/layouts/app-layout';
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
import { Label } from '@/components/ui/label';
import { Link, router, Head } from '@inertiajs/react';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { useState, useCallback } from 'react';
import { useTranslation } from 'react-i18next';
import {
  Search,
  Phone,
  PhoneCall,
  MessageSquare,
  Filter,
  X,
  AlertCircle,
  Network,
  Zap,
  Loader2,
  ShoppingCart,
  Wallet,
  CheckCircle2
} from 'lucide-react';
import { Separator } from '@/components/ui/separator';
import { Alert, AlertDescription } from '@/components/ui/alert';

interface PhoneNumber {
  id: number;
  number: string;
  formatted_number: string;
  friendly_name: string;
  country_code: string;
  area_code: string;
  status: 'available' | 'requested' | 'assigned' | 'released';
  source: 'twilio_direct' | 'sip_trunk'; // NEW: Track number source
  capabilities: {
    voice: boolean;
    sms: boolean;
    mms: boolean;
  };
  monthly_cost: number;
  original_monthly_cost?: number; // NEW: Original price before discount
  discount_percentage?: number; // NEW: Savings percentage
  created_at: string;
}

interface PaginatedNumbers {
  data: PhoneNumber[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

interface Stats {
  total_available: number;
  voice_capable: number;
  sms_capable: number;
  average_cost: number;
}

interface Country {
  code: string;
  name: string;
  continent: string;
}

interface Props {
  numbers: PaginatedNumbers;
  filters: {
    search?: string;
    country_code?: string;
    area_code?: string;
    voice_capable?: boolean;
    sms_capable?: boolean;
  };
  stats: Stats;
  countries: Country[];
}

interface PriceInfo {
  monthly_cost: number;
  charge_amount: number;
  user_balance: number;
  can_afford: boolean;
  is_sip_trunk: boolean;
  phone_number: string;
  country_code: string;
}

export default function AvailableNumbers({ numbers, filters, stats, countries = [] }: Props) {
  const { t } = useTranslation();
  const [selectedNumber, setSelectedNumber] = useState<PhoneNumber | null>(null);
  const [isPurchaseModalOpen, setIsPurchaseModalOpen] = useState(false);
  const [isFilterSheetOpen, setIsFilterSheetOpen] = useState(false);
  const [priceInfo, setPriceInfo] = useState<PriceInfo | null>(null);
  const [loadingPrice, setLoadingPrice] = useState(false);
  const [purchasing, setPurchasing] = useState(false);
  const [purchaseError, setPurchaseError] = useState<string | null>(null);

  const [localFilters, setLocalFilters] = useState({
    search: filters.search || '',
    country_code: filters.country_code || 'all',
    area_code: filters.area_code || '',
    voice_capable: filters.voice_capable?.toString() || 'all',
    sms_capable: filters.sms_capable?.toString() || 'all',
  });

  const handleSearch = () => {
    // Filter out 'all' values before sending to backend
    const searchParams = {
      ...localFilters,
      country_code: localFilters.country_code === 'all' ? '' : localFilters.country_code,
      voice_capable: localFilters.voice_capable === 'all' ? '' : localFilters.voice_capable,
      sms_capable: localFilters.sms_capable === 'all' ? '' : localFilters.sms_capable,
    };

    router.get('/numbers/available', searchParams, {
      preserveState: true,
      preserveScroll: true,
    });
  };

  const handleClearFilters = () => {
    const clearedFilters = {
      search: '',
      country_code: 'all',
      area_code: '',
      voice_capable: 'all',
      sms_capable: 'all',
    };
    setLocalFilters(clearedFilters);
    router.get('/numbers/available', {}, {
      preserveState: true,
      preserveScroll: true,
    });
  };

  const handlePurchaseNumber = useCallback(async (number: PhoneNumber) => {
    setSelectedNumber(number);
    setPriceInfo(null);
    setPurchaseError(null);
    setIsPurchaseModalOpen(true);
    setLoadingPrice(true);

    try {
      const response = await window.axios.get(`/numbers/${number.id}/price`);
      setPriceInfo(response.data);
    } catch {
      setPurchaseError('Failed to load pricing information. Please try again.');
    } finally {
      setLoadingPrice(false);
    }
  }, []);

  const submitPurchase = () => {
    if (!selectedNumber) return;
    setPurchasing(true);
    setPurchaseError(null);

    router.post(`/numbers/${selectedNumber.id}/request`, {}, {
      onSuccess: () => {
        setIsPurchaseModalOpen(false);
        setSelectedNumber(null);
        setPriceInfo(null);
      },
      onError: (errors) => {
        setPurchaseError(Object.values(errors).flat().join(' ') || 'Purchase failed. Please try again.');
      },
      onFinish: () => {
        setPurchasing(false);
      },
    });
  };

  const getStatusColor = (status: string) => {
    switch (status) {
      case 'available': return 'default';
      case 'requested': return 'secondary';
      case 'assigned': return 'destructive';
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
    {
      title: 'Dashboard',
      href: dashboard().url,
    },
    {
      title: t('numbers.available_title'),
      href: '/numbers/available',
    },
  ];

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={t('numbers.available_title')} />

      <div className="flex h-full flex-1 flex-col gap-4 p-4 md:gap-8 md:p-8">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold tracking-tight">{t('numbers.available_title')}</h1>
            <p className="text-muted-foreground">
              {t('numbers.available_description')}
            </p>
          </div>
          <Button asChild>
            <Link href="/numbers/my-numbers">
              <Phone className="mr-2 h-4 w-4" />
              {t('numbers.my_numbers_title')}
            </Link>
          </Button>
        </div>

        {/* Stats Cards */}
        <div className="grid gap-4 md:grid-cols-4">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">{t('numbers.total_available')}</CardTitle>
              <Phone className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{stats.total_available}</div>
              <p className="text-xs text-muted-foreground">
                {t('numbers.numbers_ready')}
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">{t('numbers.voice_capable')}</CardTitle>
              <PhoneCall className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{stats.voice_capable}</div>
              <p className="text-xs text-muted-foreground">
                {t('numbers.voice_description')}
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">{t('numbers.sms_capable')}</CardTitle>
              <MessageSquare className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{stats.sms_capable}</div>
              <p className="text-xs text-muted-foreground">
                {t('numbers.sms_description')}
              </p>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-sm font-medium">{t('numbers.avg_monthly_cost')}</CardTitle>
              <Phone className="h-4 w-4 text-muted-foreground" />
            </CardHeader>
            <CardContent>
              <div className="text-2xl font-bold">{formatCurrency(stats.average_cost)}</div>
              <p className="text-xs text-muted-foreground">
                {t('numbers.per_number')}
              </p>
            </CardContent>
          </Card>
        </div>

        {/* Filters */}
        <Card>
          <CardHeader>
            <div className="flex items-center justify-between">
              <div>
                <CardTitle>{t('numbers.search_numbers')}</CardTitle>
                <CardDescription>{t('numbers.search_hint')}</CardDescription>
              </div>
              <Sheet open={isFilterSheetOpen} onOpenChange={setIsFilterSheetOpen}>
                <SheetTrigger asChild>
                  <Button variant="outline" size="sm">
                    <Filter className="mr-2 h-4 w-4" />
                    {t('numbers.advanced_filters')}
                  </Button>
                </SheetTrigger>
                <SheetContent>
                  <SheetHeader>
                    <SheetTitle>{t('numbers.filter_numbers')}</SheetTitle>
                    <SheetDescription>
                      {t('numbers.filter_description')}
                    </SheetDescription>
                  </SheetHeader>
                  <div className="space-y-4 py-4">
                    <div className="space-y-2">
                      <Label htmlFor="country">{t('common.country')}</Label>
                      <Select
                        value={localFilters.country_code}
                        onValueChange={(value) => setLocalFilters({ ...localFilters, country_code: value })}
                      >
                        <SelectTrigger id="country">
                          <SelectValue placeholder="Select country..." />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="all">{t('numbers.all_countries')}</SelectItem>
                          {countries.map((country) => (
                            <SelectItem key={country.code} value={country.code}>
                              {country.code} - {country.name}
                            </SelectItem>
                          ))}
                        </SelectContent>
                      </Select>
                    </div>

                    <div className="space-y-2">
                      <Label htmlFor="area">{t('numbers.area_code')}</Label>
                      <Input
                        id="area"
                        placeholder="415, 510, 212..."
                        value={localFilters.area_code}
                        onChange={(e) => setLocalFilters({ ...localFilters, area_code: e.target.value })}
                      />
                    </div>

                    <Separator />

                    <div className="space-y-2">
                      <Label htmlFor="voice">{t('numbers.voice_capability')}</Label>
                      <Select
                        value={localFilters.voice_capable}
                        onValueChange={(value) => setLocalFilters({ ...localFilters, voice_capable: value })}
                      >
                        <SelectTrigger id="voice">
                          <SelectValue placeholder={t('numbers.any')} />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="all">{t('numbers.any')}</SelectItem>
                          <SelectItem value="1">{t('numbers.voice_only')}</SelectItem>
                          <SelectItem value="0">{t('numbers.no_voice')}</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>

                    <div className="space-y-2">
                      <Label htmlFor="sms">{t('numbers.sms_capability')}</Label>
                      <Select
                        value={localFilters.sms_capable}
                        onValueChange={(value) => setLocalFilters({ ...localFilters, sms_capable: value })}
                      >
                        <SelectTrigger id="sms">
                          <SelectValue placeholder={t('numbers.any')} />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem value="all">{t('numbers.any')}</SelectItem>
                          <SelectItem value="1">{t('numbers.sms_only')}</SelectItem>
                          <SelectItem value="0">{t('numbers.no_sms')}</SelectItem>
                        </SelectContent>
                      </Select>
                    </div>
                  </div>
                  <div className="flex gap-2">
                    <Button onClick={() => { handleSearch(); setIsFilterSheetOpen(false); }} className="flex-1">
                      {t('numbers.apply_filters')}
                    </Button>
                    <Button variant="outline" onClick={handleClearFilters}>
                      {t('common.clear')}
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
                  placeholder={t('numbers.search_numbers')}
                  className="pl-8"
                  value={localFilters.search}
                  onChange={(e) => setLocalFilters({ ...localFilters, search: e.target.value })}
                  onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
                />
              </div>
              <Button onClick={handleSearch}>{t('common.search')}</Button>
              {Object.values(localFilters).some(v => v !== '') && (
                <Button variant="ghost" onClick={handleClearFilters}>
                  <X className="mr-2 h-4 w-4" />
                  {t('common.clear')}
                </Button>
              )}
            </div>
          </CardContent>
        </Card>

        {/* No Countries Warning */}
        {countries.length === 0 && (
          <Alert>
            <AlertCircle className="h-4 w-4" />
            <AlertDescription>
              <strong>{t('numbers.no_countries')}</strong> {t('numbers.contact_admin')}
              <ol className="mt-2 ml-4 list-decimal">
                <li>Configure Twilio settings (Settings → Twilio)</li>
                <li>Enable countries in geo-permissions</li>
                <li>Sync phone numbers for enabled countries</li>
              </ol>
            </AlertDescription>
          </Alert>
        )}

        {/* Numbers Table */}
        <Card>
          <CardHeader>
            <CardTitle>{t('numbers.select_number')} ({numbers.total})</CardTitle>
            <CardDescription>
              Select a phone number to purchase and assign to your account
            </CardDescription>
          </CardHeader>
          <CardContent>
            {/* Alert for missing prices */}
            {numbers.data.some(n => n.source !== 'sip_trunk' && (!n.monthly_cost || n.monthly_cost <= 0)) && (
              <Alert variant="destructive" className="mb-4">
                <AlertCircle className="h-4 w-4" />
                <AlertDescription>
                  <strong>{t('numbers.pricing_unavailable')}:</strong> {t('numbers.pricing_unavailable_description')}
                  <span className="block mt-1 text-xs">Numbers without pricing cannot be purchased.</span>
                </AlertDescription>
              </Alert>
            )}

            {numbers.data.length === 0 ? (
              <Alert>
                <AlertCircle className="h-4 w-4" />
                <AlertDescription>
                  {countries.length === 0 ? (
                    <>
                      <strong>{t('numbers.no_numbers_available')}</strong> {t('numbers.system_not_configured')}
                    </>
                  ) : (
                    <>
                      {t('numbers.no_match')}
                      {numbers.total === 0 && (
                        <p className="mt-2">
                          <strong>Note:</strong> The database appears empty. An administrator may need to run:
                          <code className="ml-1 px-2 py-1 bg-muted rounded">php scripts/sync-multiple-countries.php</code>
                        </p>
                      )}
                    </>
                  )}
                </AlertDescription>
              </Alert>
            ) : (
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>{t('common.phone')}</TableHead>
                    <TableHead>{t('common.country')}</TableHead>
                    <TableHead>{t('numbers.area_code')}</TableHead>
                    <TableHead>{t('common.capabilities')}</TableHead>
                    <TableHead>{t('numbers.monthly_cost')}</TableHead>
                    <TableHead>{t('common.status')}</TableHead>
                    <TableHead className="text-right">{t('common.actions')}</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {numbers.data.map((number) => (
                    <TableRow key={number.id}>
                      <TableCell>
                        <div className="flex flex-col">
                          <div className="flex items-center gap-2">
                            <span className="font-medium">{number.formatted_number}</span>
                            {number.source === 'sip_trunk' && (
                              <Badge variant="default" className="bg-green-600 hover:bg-green-700">
                                <Network className="mr-1 h-3 w-3" />
                                {t('numbers.sip_trunk')}
                              </Badge>
                            )}
                          </div>
                          <span className="text-sm text-muted-foreground">{number.friendly_name}</span>
                        </div>
                      </TableCell>
                      <TableCell>
                        <Badge variant="outline">{number.country_code}</Badge>
                      </TableCell>
                      <TableCell>{number.area_code}</TableCell>
                      <TableCell>
                        <div className="flex gap-1">
                          {number.capabilities.voice && (
                            <Badge variant="secondary" className="text-xs">
                              <PhoneCall className="mr-1 h-3 w-3" />
                              {t('common.voice')}
                            </Badge>
                          )}
                          {number.capabilities.sms && (
                            <Badge variant="secondary" className="text-xs">
                              <MessageSquare className="mr-1 h-3 w-3" />
                              {t('common.sms')}
                            </Badge>
                          )}
                        </div>
                      </TableCell>
                      <TableCell className="font-medium">
                        {number.source === 'sip_trunk' ? (
                          <div className="flex flex-col">
                            <div className="flex items-center gap-1">
                              <span className="text-lg font-bold text-green-600">{t('common.free')}</span>
                              <Zap className="h-4 w-4 text-yellow-500" />
                            </div>
                            {number.original_monthly_cost && number.original_monthly_cost > 0 && (
                              <span className="text-xs text-muted-foreground line-through">
                                {formatCurrency(number.original_monthly_cost)}
                              </span>
                            )}
                          </div>
                        ) : number.monthly_cost > 0 ? (
                          formatCurrency(number.monthly_cost)
                        ) : (
                          <Badge variant="destructive" className="text-xs">
                            {t('numbers.price_not_set')}
                          </Badge>
                        )}
                      </TableCell>
                      <TableCell>
                        <Badge variant={getStatusColor(number.status)}>
                          {number.status}
                        </Badge>
                      </TableCell>
                      <TableCell className="text-right">
                        <Button
                          size="sm"
                          onClick={() => handlePurchaseNumber(number)}
                          disabled={number.status !== 'available' || (number.source !== 'sip_trunk' && (!number.monthly_cost || number.monthly_cost <= 0))}
                        >
                          {number.status === 'available' ? (
                            (number.source !== 'sip_trunk' && (!number.monthly_cost || number.monthly_cost <= 0)) ? (
                              t('numbers.no_price')
                            ) : (
                              <><ShoppingCart className="mr-1 h-3.5 w-3.5" /> {t('common.purchase')}</>
                            )
                          ) : t('numbers.unavailable')}
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            )}

            {/* Pagination */}
            {numbers.last_page > 1 && (
              <div className="flex items-center justify-between pt-4">
                <div className="text-sm text-muted-foreground">
                  {t('common.showing')} {numbers.data.length} {t('common.of')} {numbers.total} {t('common.numbers')}
                </div>
                <div className="flex gap-2">
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => router.get(`/numbers/available?page=${numbers.current_page - 1}`, localFilters)}
                    disabled={numbers.current_page === 1}
                  >
                    {t('common.previous')}
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => router.get(`/numbers/available?page=${numbers.current_page + 1}`, localFilters)}
                    disabled={numbers.current_page === numbers.last_page}
                  >
                    {t('common.next')}
                  </Button>
                </div>
              </div>
            )}
          </CardContent>
        </Card>
      </div>

      {/* Purchase Confirmation Modal */}
      <Dialog open={isPurchaseModalOpen} onOpenChange={(open) => {
        if (!purchasing) {
          setIsPurchaseModalOpen(open);
          if (!open) {
            setSelectedNumber(null);
            setPriceInfo(null);
            setPurchaseError(null);
          }
        }
      }}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <ShoppingCart className="h-5 w-5" />
              {t('numbers.purchase_title')}
            </DialogTitle>
            <DialogDescription>
              {t('numbers.purchase_description')}
            </DialogDescription>
          </DialogHeader>

          {selectedNumber && (
            <div className="space-y-4 py-4">
              {/* Number Details */}
              <div className="rounded-lg border p-4 space-y-2">
                <div className="flex items-center justify-between">
                  <span className="text-sm font-medium">{t('numbers.phone_number_label')}</span>
                  <div className="flex items-center gap-2">
                    <span className="text-lg font-bold">{selectedNumber.formatted_number}</span>
                    {selectedNumber.source === 'sip_trunk' && (
                      <Badge variant="default" className="bg-green-600">
                        <Network className="mr-1 h-3 w-3" />
                        {t('numbers.sip_trunk')}
                      </Badge>
                    )}
                  </div>
                </div>
                <div className="flex items-center justify-between">
                  <span className="text-sm font-medium">{t('numbers.location_label')}</span>
                  <span>{selectedNumber.country_code} • {t('numbers.area_code_label')} {selectedNumber.area_code}</span>
                </div>
                <div className="flex items-center justify-between">
                  <span className="text-sm font-medium">{t('numbers.capabilities_label')}</span>
                  <div className="flex gap-1">
                    {selectedNumber.capabilities.voice && <Badge variant="secondary">{t('common.voice')}</Badge>}
                    {selectedNumber.capabilities.sms && <Badge variant="secondary">{t('common.sms')}</Badge>}
                  </div>
                </div>
              </div>

              {/* Pricing Details */}
              {loadingPrice ? (
                <div className="flex items-center justify-center py-6">
                  <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
                  <span className="ml-2 text-sm text-muted-foreground">{t('numbers.loading_pricing')}</span>
                </div>
              ) : priceInfo ? (
                <div className="rounded-lg border bg-muted/30 p-4 space-y-3">
                  {/* Check if pricing is missing or zero */}
                  {!priceInfo.is_sip_trunk && (!priceInfo.monthly_cost || priceInfo.monthly_cost <= 0) ? (
                    <Alert variant="destructive">
                      <AlertCircle className="h-4 w-4" />
                      <AlertDescription>
                        <strong>{t('numbers.pricing_unavailable')}:</strong> {t('numbers.pricing_unavailable_description')}
                      </AlertDescription>
                    </Alert>
                  ) : (
                    <>
                  <div className="flex items-center justify-between">
                    <span className="text-sm font-medium">{t('numbers.purchase_cost')}</span>
                    {priceInfo.is_sip_trunk ? (
                      <div className="flex items-center gap-1">
                        <span className="text-lg font-bold text-green-600">{t('common.free')}</span>
                        <Zap className="h-4 w-4 text-yellow-500" />
                      </div>
                    ) : (
                      <span className="text-lg font-bold">{formatCurrency(priceInfo.charge_amount)}</span>
                    )}
                  </div>
                  <Separator />
                  <div className="flex items-center justify-between">
                    <span className="text-sm text-muted-foreground flex items-center gap-1">
                      <Wallet className="h-3.5 w-3.5" /> {t('numbers.your_balance')}
                    </span>
                    <span className={`font-medium ${
                      priceInfo.can_afford ? 'text-green-600' : 'text-destructive'
                    }`}>
                      {formatCurrency(priceInfo.user_balance)}
                    </span>
                  </div>
                  {!priceInfo.is_sip_trunk && (
                    <div className="flex items-center justify-between">
                      <span className="text-sm text-muted-foreground">{t('numbers.balance_after')}</span>
                      <span className={`font-medium ${
                        priceInfo.can_afford ? '' : 'text-destructive'
                      }`}>
                        {formatCurrency(priceInfo.user_balance - priceInfo.charge_amount)}
                      </span>
                    </div>
                  )}

                  {!priceInfo.can_afford && !priceInfo.is_sip_trunk && (
                    <Alert variant="destructive" className="mt-2">
                      <AlertCircle className="h-4 w-4" />
                      <AlertDescription>
                        {t('numbers.insufficient_balance')} <strong>{formatCurrency(priceInfo.charge_amount)}</strong> {t('numbers.to_purchase')}
                      </AlertDescription>
                    </Alert>
                  )}

                  {priceInfo.can_afford && !priceInfo.is_sip_trunk && (
                    <Alert className="mt-2 border-green-600 bg-green-50 dark:bg-green-950/20">
                      <CheckCircle2 className="h-4 w-4 text-green-600" />
                      <AlertDescription className="text-sm text-green-800 dark:text-green-200">
                        {t('numbers.sufficient_balance')}
                      </AlertDescription>
                    </Alert>
                  )}

                  {priceInfo.is_sip_trunk && (
                    <Alert className="mt-2 border-green-600 bg-green-50 dark:bg-green-950/20">
                      <Network className="h-4 w-4 text-green-600" />
                      <AlertDescription className="text-sm text-green-800 dark:text-green-200">
                        {t('numbers.sip_free')}
                      </AlertDescription>
                    </Alert>
                  )}
                    </>
                  )}
                </div>
              ) : null}

              {/* Error */}
              {purchaseError && (
                <Alert variant="destructive">
                  <AlertCircle className="h-4 w-4" />
                  <AlertDescription>{purchaseError}</AlertDescription>
                </Alert>
              )}

              <DialogFooter>
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => setIsPurchaseModalOpen(false)}
                  disabled={purchasing}
                >
                  {t('common.cancel')}
                </Button>
                <Button
                  onClick={submitPurchase}
                  disabled={
                    purchasing ||
                    loadingPrice ||
                    !priceInfo ||
                    (!priceInfo.is_sip_trunk && (!priceInfo.monthly_cost || priceInfo.monthly_cost <= 0)) ||
                    (priceInfo !== null && !priceInfo.can_afford && !priceInfo.is_sip_trunk)
                  }
                >
                  {purchasing ? (
                    <><Loader2 className="mr-2 h-4 w-4 animate-spin" /> {t('numbers.purchasing')}</>
                  ) : (
                    <><ShoppingCart className="mr-2 h-4 w-4" /> {t('numbers.confirm_purchase')}</>
                  )}
                </Button>
              </DialogFooter>
            </div>
          )}
        </DialogContent>
      </Dialog>
    </AppLayout>
  );
}
