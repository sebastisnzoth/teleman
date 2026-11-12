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
import { Head } from '@inertiajs/react';
import { type BreadcrumbItem } from '@/types';
import { useState, useEffect } from 'react';
import { 
  Phone, 
  PhoneIncoming,
  PhoneOutgoing,
  Globe,
  Calculator,
  Info,
  Loader2
} from 'lucide-react';
import axios from 'axios';

interface Country {
  code: string;
  name: string;
}

interface PricingData {
  customer_charge: number;
  per_minute: number;
  available: boolean;
  note?: string;
}

interface CalculatorResponse {
  from_country: { code: string; name: string };
  to_country: { code: string; name: string };
  minutes: number;
  rounded_minutes: number;
  tier: string;
  inbound: PricingData;
  outbound: PricingData;
  sip: PricingData;
  total_cost: number;
}

export default function PricingCalculator() {
  const { t } = useTranslation();
  const { formatCurrency, baseCurrency } = useFormatCurrency();
  const [countries, setCountries] = useState<Country[]>([]);
  const [fromCountry, setFromCountry] = useState<string>('US');
  const [toCountry, setToCountry] = useState<string>('AU');
  const [minutes, setMinutes] = useState<number>(1);
  const [loading, setLoading] = useState<boolean>(false);
  const [loadingCountries, setLoadingCountries] = useState<boolean>(true);
  const [pricing, setPricing] = useState<CalculatorResponse | null>(null);
  const [error, setError] = useState<string | null>(null);

  const breadcrumbs: BreadcrumbItem[] = [
    {
      title: t('common.dashboard'), href: '/dashboard',
    },
    {
      title: t('customer.phone_numbers'), href: '/numbers/my-numbers',
    },
    {
      title: t('customer.pricing_calculator'), href: '/pricing/calculator',
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
        const response = await axios.get('/api/v1/pricing/calculator', {
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

    // Debounce the calculation
    const timer = setTimeout(calculatePricing, 500);
    return () => clearTimeout(timer);
  }, [fromCountry, toCountry, minutes]);

  const getCountryName = (code: string) => {
    const country = countries.find(c => c.code === code);
    return country ? country.name : code;
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={t('customer.pricing_calculator')} />
      
      <div className="flex h-full flex-1 flex-col gap-4 p-4 md:gap-8 md:p-8">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <div className="flex items-center gap-3 mb-2">
              <Calculator className="h-8 w-8 text-blue-600" />
              <h1 className="text-3xl font-bold tracking-tight">International Calling Price Calculator</h1>
            </div>
            <p className="text-muted-foreground">
              Calculate real-time pricing for international calls. Get accurate rates for inbound, outbound, and VoIP calling.
            </p>
          </div>
        </div>

        {/* Calculator Controls */}
        <Card>
          <CardHeader>
            <CardTitle>{t('customer.calculate_pricing')}</CardTitle>
            <CardDescription>
              Select countries and duration to see pricing breakdown
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="grid gap-4 md:grid-cols-3">
              {/* From Country */}
              <div className="space-y-2">
                <Label htmlFor="from-country">From Country (Origin)</Label>
                <Select
                  value={fromCountry}
                  onValueChange={setFromCountry}
                  disabled={loadingCountries}
                >
                  <SelectTrigger id="from-country">
                    <SelectValue placeholder="Select origin country" />
                  </SelectTrigger>
                  <SelectContent>
                    {countries.map((country) => (
                      <SelectItem key={country.code} value={country.code}>
                        {country.name} ({country.code})
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <p className="text-xs text-muted-foreground">
                  Where your phone number is located
                </p>
              </div>

              {/* To Country */}
              <div className="space-y-2">
                <Label htmlFor="to-country">To Country (Destination)</Label>
                <Select
                  value={toCountry}
                  onValueChange={setToCountry}
                  disabled={loadingCountries}
                >
                  <SelectTrigger id="to-country">
                    <SelectValue placeholder="Select destination" />
                  </SelectTrigger>
                  <SelectContent>
                    {countries.map((country) => (
                      <SelectItem key={country.code} value={country.code}>
                        {country.name} ({country.code})
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                <p className="text-xs text-muted-foreground">
                  Where you're calling to
                </p>
              </div>

              {/* Duration */}
              <div className="space-y-2">
                <Label htmlFor="minutes">Duration (minutes)</Label>
                <Input
                  id="minutes"
                  type="number"
                  min="0.1"
                  max="1000"
                  step="0.5"
                  value={minutes}
                  onChange={(e) => setMinutes(parseFloat(e.target.value) || 1)}
                  placeholder="1"
                />
                <p className="text-xs text-muted-foreground">
                  Call duration in minutes
                </p>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Loading State */}
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

        {/* Error State */}
        {error && !loading && (
          <Alert variant="destructive">
            <AlertCircle className="h-4 w-4" />
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        )}

        {/* Pricing Results */}
        {pricing && !loading && !error && (
          <>
            {/* Route Information */}
            <Card className="border-blue-200 bg-blue-50/50">
              <CardContent className="p-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                  <div className="flex items-center gap-2">
                    <span className="text-sm font-medium text-muted-foreground">From:</span>
                    <span className="text-sm font-semibold text-blue-700">
                      {pricing.from_country.name} ({pricing.from_country.code})
                    </span>
                  </div>
                  <div className="text-muted-foreground">→</div>
                  <div className="flex items-center gap-2">
                    <span className="text-sm font-medium text-muted-foreground">To:</span>
                    <span className="text-sm font-semibold text-blue-700">
                      {pricing.to_country.name} ({pricing.to_country.code})
                    </span>
                  </div>
                  <div className="text-muted-foreground">•</div>
                  <div className="flex items-center gap-2">
                    <span className="text-sm font-medium text-muted-foreground">Duration:</span>
                    <span className="text-sm font-semibold text-blue-700">
                      {pricing.rounded_minutes} {pricing.rounded_minutes === 1 ? 'minute' : 'minutes'}
                    </span>
                  </div>
                </div>
              </CardContent>
            </Card>

            {/* Pricing Cards */}
            <div className="grid gap-4 md:grid-cols-3">
              {/* Inbound Calls */}
              <Card className="border-green-200 bg-green-50">
                <CardHeader>
                  <div className="flex items-center gap-2">
                    <PhoneIncoming className="h-5 w-5 text-green-600" />
                    <CardTitle className="text-lg">{t('customer.inbound_calls')}</CardTitle>
                  </div>
                  <CardDescription>Receiving calls on your number</CardDescription>
                </CardHeader>
                <CardContent>
                  {pricing.inbound.available ? (
                    <>
                      <div className="text-3xl font-bold text-green-700 mb-2">
                        {formatCurrency(pricing.inbound.customer_charge, undefined, 4)}
                      </div>
                      <div className="text-sm text-muted-foreground mb-3">
                        {formatCurrency(pricing.inbound.per_minute, undefined, 4)}/min
                      </div>
                      {pricing.inbound.note && (
                        <div className="flex items-start gap-2 bg-white p-3 rounded-md">
                          <Info className="h-4 w-4 text-blue-500 mt-0.5 flex-shrink-0" />
                          <span className="text-xs text-gray-600">{pricing.inbound.note}</span>
                        </div>
                      )}
                    </>
                  ) : (
                    <Badge variant="outline" className="text-red-600 border-red-300">
                      Not Available
                    </Badge>
                  )}
                </CardContent>
              </Card>

              {/* Outbound Calls */}
              <Card className="border-blue-200 bg-blue-50/50">
                <CardHeader>
                  <div className="flex items-center gap-2">
                    <PhoneOutgoing className="h-5 w-5 text-blue-600" />
                    <CardTitle className="text-lg">{t('customer.outbound_calls')}</CardTitle>
                  </div>
                  <CardDescription>Phone to Phone calling</CardDescription>
                </CardHeader>
                <CardContent>
                  {pricing.outbound.available ? (
                    <>
                      <div className="text-3xl font-bold text-blue-700 mb-2">
                        {formatCurrency(pricing.outbound.customer_charge, undefined, 4)}
                      </div>
                      <div className="text-sm text-muted-foreground mb-3">
                        {formatCurrency(pricing.outbound.per_minute, undefined, 4)}/min
                      </div>
                      {pricing.outbound.note && (
                        <div className="flex items-start gap-2 bg-white p-3 rounded-md">
                          <Info className="h-4 w-4 text-blue-500 mt-0.5 flex-shrink-0" />
                          <span className="text-xs text-gray-600">{pricing.outbound.note}</span>
                        </div>
                      )}
                    </>
                  ) : (
                    <Badge variant="outline" className="text-red-600 border-red-300">
                      Not Available
                    </Badge>
                  )}
                </CardContent>
              </Card>

              {/* SIP/VoIP Calls */}
              <Card className="border-purple-200 bg-purple-50/50">
                <CardHeader>
                  <div className="flex items-center gap-2">
                    <Globe className="h-5 w-5 text-purple-600" />
                    <CardTitle className="text-lg">{t('customer.sipvoip_calls')}</CardTitle>
                  </div>
                  <CardDescription>Browser/App SDK calling</CardDescription>
                </CardHeader>
                <CardContent>
                  {pricing.sip.available ? (
                    <>
                      <div className="text-3xl font-bold text-purple-700 mb-2">
                        {formatCurrency(pricing.sip.customer_charge, undefined, 4)}
                      </div>
                      <div className="text-sm text-muted-foreground mb-3">
                        {formatCurrency(pricing.sip.per_minute, undefined, 4)}/min
                      </div>
                      {pricing.sip.note && (
                        <div className="flex items-start gap-2 bg-white p-3 rounded-md">
                          <Info className="h-4 w-4 text-blue-500 mt-0.5 flex-shrink-0" />
                          <span className="text-xs text-gray-600">{pricing.sip.note}</span>
                        </div>
                      )}
                    </>
                  ) : (
                    <Badge variant="outline" className="text-red-600 border-red-300">
                      Not Available
                    </Badge>
                  )}
                </CardContent>
              </Card>
            </div>

            {/* Total Cost Summary */}
            <Card className="border-2 border-blue-300 bg-gradient-to-br from-blue-50 to-indigo-50">
              <CardHeader>
                <CardTitle className="text-2xl">{t('customer.estimated_total_cost')}</CardTitle>
                <CardDescription>
                  For {pricing.rounded_minutes} {pricing.rounded_minutes === 1 ? 'minute' : 'minutes'} of calling from {pricing.from_country.name} to {pricing.to_country.name}
                </CardDescription>
              </CardHeader>
              <CardContent>
                <div className="flex items-baseline gap-2">
                  <span className="text-5xl font-bold text-blue-700">
                    {formatCurrency(pricing.total_cost, undefined, 4)}
                  </span>
                  <span className="text-xl text-gray-600">
                    ({formatCurrency(pricing.total_cost / pricing.rounded_minutes, undefined, 4)}/min)
                  </span>
                </div>
                <p className="text-sm text-muted-foreground mt-4">
                  * Prices shown are per-minute rates. Minutes are rounded up to the nearest whole minute. 
                  Actual charges may vary based on your account tier and any applicable promotions.
                </p>
              </CardContent>
            </Card>
          </>
        )}

        {/* Info Box */}
        <Alert>
          <Info className="h-4 w-4" />
          <AlertDescription>
            <strong>How it works:</strong> This calculator shows real-time pricing based on your current account tier. 
            Inbound calls are typically free (included with your phone number rental). Outbound and SIP/VoIP calls are charged per minute, 
            rounded up to the nearest minute. All prices are in {baseCurrency}.
          </AlertDescription>
        </Alert>
      </div>
    </AppLayout>
  );
}
