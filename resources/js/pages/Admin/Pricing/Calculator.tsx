import AppLayout from '@/layouts/app-layout';
import { useTranslation } from 'react-i18next';
import { useFormatCurrency } from '@/hooks/use-currency';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Head } from '@inertiajs/react';
import { providerLabel, useTelephony } from '@/lib/telephony';
import { type BreadcrumbItem } from '@/types';
import { useState, useEffect } from 'react';
import { 
  Phone, 
  PhoneIncoming,
  PhoneOutgoing,
  Globe,
  Calculator,
  Info,
  Loader2,
  TrendingUp,
  DollarSign
} from 'lucide-react';
import axios from 'axios';

interface Country {
  code: string;
  name: string;
}

interface AdminPricingData {
  base_cost: number;
  customer_charge: number;
  per_minute: number;
  profit: number;
  margin_percent: number;
  available: boolean;
  note?: string;
  rule_id?: number;
  markup_type?: string;
  markup_value?: number;
}

interface AdminCalculatorResponse {
  from_country: { code: string; name: string };
  to_country: { code: string; name: string };
  minutes: number;
  rounded_minutes: number;
  tier: string;
  inbound: AdminPricingData;
  outbound: AdminPricingData;
  sip: AdminPricingData;
  total_cost: number;
}

export default function AdminPricingCalculator() {
  const { t } = useTranslation();
  const { formatCurrency } = useFormatCurrency();
  // Whose rate the base cost is. Named once a provider is locked, since an
  // admin chose it; neutral before that, when there is nothing to name.
  const { provider, locked } = useTelephony();
  const costSource = locked ? providerLabel(provider) : 'your telephony provider';
  const [countries, setCountries] = useState<Country[]>([]);
  const [fromCountry, setFromCountry] = useState<string>('US');
  const [toCountry, setToCountry] = useState<string>('AU');
  const [minutes, setMinutes] = useState<number>(1);
  const [loading, setLoading] = useState<boolean>(false);
  const [loadingCountries, setLoadingCountries] = useState<boolean>(true);
  const [pricing, setPricing] = useState<AdminCalculatorResponse | null>(null);
  const [error, setError] = useState<string | null>(null);

  const breadcrumbs: BreadcrumbItem[] = [
    {
      title: t('common.dashboard'), href: '/dashboard',
    },
    {
      title: t('admin.pricing.pricing_rates'), href: '/admin/pricing',
    },
    {
      title: t('admin.pricing.price_calculator'), href: '/admin/pricing/calculator',
    },
  ];

  // Fetch available countries
  useEffect(() => {
    const fetchCountries = async () => {
      try {
        const response = await axios.get('/api/v1/pricing/countries');
        setCountries(response.data.countries);
        setLoadingCountries(false);
      } catch (err) {
        console.error('Failed to load countries:', err);
        setLoadingCountries(false);
      }
    };

    fetchCountries();
  }, []);

  // Calculate pricing when inputs change
  useEffect(() => {
    const calculatePricing = async () => {
      if (!fromCountry || !toCountry || minutes < 0.1) return;

      setLoading(true);
      setError(null);

      try {
        const response = await axios.get('/api/v1/admin/pricing/calculator', {
          params: {
            from_country: fromCountry,
            to_country: toCountry,
            minutes: minutes,
          },
        });

        setPricing(response.data);
      } catch (err: any) {
        setError(err.response?.data?.error || 'Failed to calculate pricing');
        setPricing(null);
      } finally {
        setLoading(false);
      }
    };

    const timer = setTimeout(calculatePricing, 500);
    return () => clearTimeout(timer);
  }, [fromCountry, toCountry, minutes]);

  const formatPercent = (value: number | undefined) => {
    if (value === undefined || value === null) return '0.00%';
    return `${value.toFixed(2)}%`;
  };

  const renderDetailedPricingCard = (
    title: string,
    icon: React.ReactNode,
    data: AdminPricingData,
    colorClass: string
  ) => (
    <Card className={`border-2 ${colorClass}`}>
      <CardHeader>
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            {icon}
            <CardTitle className="text-lg">{title}</CardTitle>
          </div>
          {data.available ? (
            <Badge className="bg-green-500">Available</Badge>
          ) : (
            <Badge variant="destructive">Not Available</Badge>
          )}
        </div>
      </CardHeader>
      <CardContent>
        {data.available ? (
          <div className="space-y-4">
            {/* Customer Charge */}
            <div className="flex justify-between items-baseline border-b pb-2">
              <span className="text-sm font-medium text-gray-600">Customer Charge:</span>
              <div className="text-right">
                <div className="text-2xl font-bold text-blue-700">
                  {formatCurrency(data.customer_charge, undefined, 4)}
                </div>
                <div className="text-xs text-muted-foreground">
                  {formatCurrency(data.per_minute, undefined, 4)}/min
                </div>
              </div>
            </div>

            {/* Base Cost */}
            <div className="flex justify-between items-center">
              <span className="text-sm text-gray-600">Base Cost ({costSource}):</span>
              <span className="text-lg font-semibold text-gray-700">
                {formatCurrency(data.base_cost, undefined, 4)}
              </span>
            </div>

            {/* Profit */}
            <div className="flex justify-between items-center">
              <span className="text-sm text-gray-600">Profit:</span>
              <span className="text-lg font-semibold text-green-600">
                {formatCurrency(data.profit, undefined, 4)}
              </span>
            </div>

            {/* Margin */}
            <div className="flex justify-between items-center">
              <span className="text-sm text-gray-600">Margin:</span>
              <span className="text-lg font-semibold text-blue-600">
                {formatPercent(data.margin_percent)}
              </span>
            </div>

            {/* Markup Info */}
            {data.markup_type && (
              <div className="pt-2 border-t">
                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                  <Info className="h-3 w-3" />
                  <span>
                    Markup: {data.markup_value}
                    {data.markup_type === 'percentage' ? '%' : ' (fixed)'}
                  </span>
                </div>
              </div>
            )}

            {/* Note */}
            {data.note && (
              <div className="pt-2 border-t">
                <div className="flex items-start gap-2 text-xs text-muted-foreground">
                  <Info className="h-3 w-3 mt-0.5 flex-shrink-0" />
                  <span>{data.note}</span>
                </div>
              </div>
            )}
          </div>
        ) : (
          <div className="text-center py-4 text-gray-500">
            <p>Pricing not available for this route</p>
            {data.note && <p className="text-xs mt-2">{data.note}</p>}
          </div>
        )}
      </CardContent>
    </Card>
  );

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={t('admin.pricing.admin_pricing_calculator')} />
      
      <div className="flex h-full flex-1 flex-col gap-4 p-4 md:gap-8 md:p-8">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <div className="flex items-center gap-3 mb-2">
              <Calculator className="h-8 w-8 text-blue-600" />
              <h1 className="text-3xl font-bold tracking-tight">Pricing Calculator (Admin)</h1>
            </div>
            <p className="text-muted-foreground">
              Calculate pricing with complete cost breakdown including base costs, profit margins, and markup details.
            </p>
          </div>
          <Badge variant="outline" className="text-blue-600 border-blue-300">
            Full Cost Breakdown
          </Badge>
        </div>

        {/* Calculator Controls */}
        <Card>
          <CardHeader>
            <CardTitle>{t('admin.pricing.configure_calculation')}</CardTitle>
            <CardDescription>
              Select countries, duration, and pricing tier
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="grid gap-4 md:grid-cols-3">
              <div className="space-y-2">
                <Label htmlFor="from-country">From Country</Label>
                <Select
                  value={fromCountry}
                  onValueChange={setFromCountry}
                  disabled={loadingCountries}
                >
                  <SelectTrigger id="from-country">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {countries.map((country) => (
                      <SelectItem key={country.code} value={country.code}>
                        {country.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <div className="space-y-2">
                <Label htmlFor="to-country">To Country</Label>
                <Select
                  value={toCountry}
                  onValueChange={setToCountry}
                  disabled={loadingCountries}
                >
                  <SelectTrigger id="to-country">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {countries.map((country) => (
                      <SelectItem key={country.code} value={country.code}>
                        {country.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <div className="space-y-2">
                <Label htmlFor="minutes">Duration (min)</Label>
                <Input
                  id="minutes"
                  type="number"
                  min="0.1"
                  max="1000"
                  step="0.5"
                  value={minutes}
                  onChange={(e) => setMinutes(parseFloat(e.target.value) || 1)}
                />
              </div>
            </div>
          </CardContent>
        </Card>

        {loading && (
          <Card>
            <CardContent className="py-12">
              <div className="flex items-center justify-center">
                <Loader2 className="h-8 w-8 animate-spin text-blue-600" />
                <span className="ml-3 text-muted-foreground">Calculating pricing...</span>
              </div>
            </CardContent>
          </Card>
        )}

        {error && !loading && (
          <Alert variant="destructive">
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        )}

        {pricing && !loading && !error && (
          <>
            {/* Route Summary */}
            <Card className="border-blue-200 bg-blue-50/50">
              <CardContent className="p-6">
                <div className="grid gap-4 md:grid-cols-3">
                  <div>
                    <p className="text-xs text-muted-foreground mb-1">From</p>
                    <p className="font-semibold text-blue-700">
                      {pricing.from_country.name} ({pricing.from_country.code})
                    </p>
                  </div>
                  <div>
                    <p className="text-xs text-muted-foreground mb-1">To</p>
                    <p className="font-semibold text-blue-700">
                      {pricing.to_country.name} ({pricing.to_country.code})
                    </p>
                  </div>
                  <div>
                    <p className="text-xs text-muted-foreground mb-1">Duration</p>
                    <p className="font-semibold text-blue-700">
                      {pricing.rounded_minutes} min
                    </p>
                  </div>
                </div>
              </CardContent>
            </Card>

            {/* Detailed Pricing Cards */}
            <div className="grid gap-4 lg:grid-cols-3">
              {renderDetailedPricingCard(
                'Inbound Calls',
                <PhoneIncoming className="h-5 w-5 text-green-600" />,
                pricing.inbound,
                'border-green-200 bg-green-50/50'
              )}
              {renderDetailedPricingCard(
                'Outbound Calls',
                <PhoneOutgoing className="h-5 w-5 text-blue-600" />,
                pricing.outbound,
                'border-blue-200 bg-blue-50/50'
              )}
              {renderDetailedPricingCard(
                'SIP/VoIP Calls',
                <Globe className="h-5 w-5 text-purple-600" />,
                pricing.sip,
                'border-purple-200 bg-purple-50/50'
              )}
            </div>

            {/* Profit Summary */}
            <div className="grid gap-4 md:grid-cols-3">
              <Card className="border-2 border-green-300 bg-gradient-to-br from-green-50 to-emerald-50">
                <CardHeader>
                  <div className="flex items-center gap-2">
                    <TrendingUp className="h-5 w-5 text-green-600" />
                    <CardTitle>{t('admin.pricing.total_profit')}</CardTitle>
                  </div>
                </CardHeader>
                <CardContent>
                  <div className="text-4xl font-bold text-green-700">
                    {formatCurrency(
                      pricing.inbound.profit + pricing.outbound.profit,
                      undefined, 4
                    )}
                  </div>
                  <p className="text-sm text-muted-foreground mt-2">
                    From inbound + outbound for {pricing.rounded_minutes} minute(s)
                  </p>
                </CardContent>
              </Card>

              <Card className="border-2 border-blue-300 bg-gradient-to-br from-blue-50 to-cyan-50">
                <CardHeader>
                  <div className="flex items-center gap-2">
                    <DollarSign className="h-5 w-5 text-blue-600" />
                    <CardTitle>{t('admin.pricing.customer_total')}</CardTitle>
                  </div>
                </CardHeader>
                <CardContent>
                  <div className="text-4xl font-bold text-blue-700">
                    {formatCurrency(pricing.total_cost, undefined, 4)}
                  </div>
                  <p className="text-sm text-muted-foreground mt-2">
                    What customer pays for this call
                  </p>
                </CardContent>
              </Card>
            </div>

            {/* Cost Breakdown Table */}
            <Card>
              <CardHeader>
                <CardTitle>{t('admin.pricing.detailed_cost_breakdown')}</CardTitle>
                <CardDescription>
                  Line-by-line cost analysis for {pricing.rounded_minutes} minute(s)
                </CardDescription>
              </CardHeader>
              <CardContent>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Service</TableHead>
                      <TableHead className="text-right">Base Cost</TableHead>
                      <TableHead className="text-right">Customer Charge</TableHead>
                      <TableHead className="text-right">Profit</TableHead>
                      <TableHead className="text-right">Margin</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    <TableRow>
                      <TableCell className="font-medium">Inbound</TableCell>
                      <TableCell className="text-right text-gray-600">
                        {formatCurrency(pricing.inbound.base_cost, undefined, 4)}
                      </TableCell>
                      <TableCell className="text-right font-semibold text-blue-600">
                        {formatCurrency(pricing.inbound.customer_charge, undefined, 4)}
                      </TableCell>
                      <TableCell className="text-right text-green-600">
                        {formatCurrency(pricing.inbound.profit, undefined, 4)}
                      </TableCell>
                      <TableCell className="text-right">
                        {formatPercent(pricing.inbound.margin_percent)}
                      </TableCell>
                    </TableRow>
                    <TableRow>
                      <TableCell className="font-medium">Outbound</TableCell>
                      <TableCell className="text-right text-gray-600">
                        {formatCurrency(pricing.outbound.base_cost, undefined, 4)}
                      </TableCell>
                      <TableCell className="text-right font-semibold text-blue-600">
                        {formatCurrency(pricing.outbound.customer_charge, undefined, 4)}
                      </TableCell>
                      <TableCell className="text-right text-green-600">
                        {formatCurrency(pricing.outbound.profit, undefined, 4)}
                      </TableCell>
                      <TableCell className="text-right">
                        {formatPercent(pricing.outbound.margin_percent)}
                      </TableCell>
                    </TableRow>
                    <TableRow>
                      <TableCell className="font-medium">SIP/VoIP</TableCell>
                      <TableCell className="text-right text-gray-600">
                        {formatCurrency(pricing.sip.base_cost, undefined, 4)}
                      </TableCell>
                      <TableCell className="text-right font-semibold text-blue-600">
                        {formatCurrency(pricing.sip.customer_charge, undefined, 4)}
                      </TableCell>
                      <TableCell className="text-right text-green-600">
                        {formatCurrency(pricing.sip.profit, undefined, 4)}
                      </TableCell>
                      <TableCell className="text-right">
                        {formatPercent(pricing.sip.margin_percent)}
                      </TableCell>
                    </TableRow>
                    <TableRow className="border-t-2 font-semibold bg-gray-50">
                      <TableCell>TOTAL</TableCell>
                      <TableCell className="text-right">
                        {formatCurrency(
                          pricing.inbound.base_cost +
                          pricing.outbound.base_cost +
                          pricing.sip.base_cost,
                          undefined, 4
                        )}
                      </TableCell>
                      <TableCell className="text-right text-blue-700">
                        {formatCurrency(pricing.total_cost, undefined, 4)}
                      </TableCell>
                      <TableCell className="text-right text-green-700">
                        {formatCurrency(
                          pricing.inbound.profit +
                          pricing.outbound.profit +
                          pricing.sip.profit,
                          undefined, 4
                        )}
                      </TableCell>
                      <TableCell className="text-right">
                        {formatPercent(
                          ((pricing.inbound.profit + pricing.outbound.profit) /
                            (pricing.inbound.base_cost + pricing.outbound.base_cost)) * 100
                        )}
                      </TableCell>
                    </TableRow>
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          </>
        )}
      </div>
    </AppLayout>
  );
}
