import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Input } from '@/components/ui/input';
import { Link, Head, router } from '@inertiajs/react';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
  Phone,
  PhoneCall,
  MessageSquare,
  Search,
  Calendar,
  AlertCircle,
  CheckCircle,
  Bot,
  X,
  Network,
  Zap
} from 'lucide-react';

interface AiAgent {
  id: number;
  name: string;
  type: 'inbound' | 'outbound';
  active: boolean;
}

interface PhoneNumber {
  id: number;
  number: string;
  formatted_number: string;
  friendly_name: string;
  country_code: string;
  area_code: string;
  status: 'assigned';
  source?: 'twilio_direct' | 'sip_trunk';
  capabilities: {
    voice: boolean;
    sms: boolean;
    mms: boolean;
  };
  monthly_cost: number;
  original_monthly_cost?: number;
  discount_percentage?: number;
  calling_price?: number | null;
  assigned_at: string;
  created_at: string;
  ai_agent: AiAgent | null;
}

interface PaginatedNumbers {
  data: PhoneNumber[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

interface Props {
  numbers: PaginatedNumbers;
}

export default function MyNumbers({ numbers }: Props) {
  const { t } = useTranslation();
  const [searchTerm, setSearchTerm] = useState('');

  const formatCurrency = (amount: number | null | undefined) => {
    const value = Number(amount) || 0;
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
    }).format(value);
  };

  const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    });
  };

  const totalMonthlyCost = numbers.data.reduce((sum, num) => sum + (num.monthly_cost || 0), 0);

  // Filter numbers based on search term
  const filteredNumbers = numbers.data.filter(number => {
    if (!searchTerm) return true;
    const search = searchTerm.toLowerCase();
    return (
      number.number.toLowerCase().includes(search) ||
      number.formatted_number.toLowerCase().includes(search) ||
      number.friendly_name.toLowerCase().includes(search) ||
      (number.ai_agent?.name.toLowerCase().includes(search))
    );
  });

  const handleClearSearch = () => {
    setSearchTerm('');
  };

  const breadcrumbs: BreadcrumbItem[] = [
    {
      title: 'Dashboard',
      href: dashboard().url,
    },
    {
      title: t('numbers.my_numbers_title'),
      href: '/numbers/my-numbers',
    },
  ];

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={t('numbers.my_numbers_title')} />

      <div className="flex h-full flex-1 flex-col gap-4 p-4 md:gap-8 md:p-8">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold tracking-tight">{t('numbers.my_numbers_title')}</h1>
            <p className="text-muted-foreground">
              {t('numbers.my_numbers_description')}
            </p>
          </div>
          <div className="flex gap-2">
            <Button variant="outline" asChild>
              <Link href="/numbers/my-requests">
                <Calendar className="mr-2 h-4 w-4" />
                {t('numbers.purchase_history')}
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

        {/* Summary Card */}
        {numbers.data.length > 0 && (
          <div className="grid gap-4 md:grid-cols-5">
            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">{t('numbers.total')}</CardTitle>
                <Phone className="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{numbers.total}</div>
                <p className="text-xs text-muted-foreground">
                  {t('numbers.active')}
                </p>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">{t('numbers.voice_numbers')}</CardTitle>
                <PhoneCall className="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">
                  {numbers.data.filter(n => n.capabilities.voice).length}
                </div>
                <p className="text-xs text-muted-foreground">
                  {t('numbers.voice_description')}
                </p>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">{t('numbers.sms_numbers')}</CardTitle>
                <MessageSquare className="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">
                  {numbers.data.filter(n => n.capabilities.sms).length}
                </div>
                <p className="text-xs text-muted-foreground">
                  {t('numbers.sms_description')}
                </p>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">{t('numbers.ai_agents')}</CardTitle>
                <Bot className="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">
                  {numbers.data.filter(n => n.ai_agent).length}
                </div>
                <p className="text-xs text-muted-foreground">
                  {t('numbers.ai_agents_description')}
                </p>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">{t('numbers.monthly_cost')}</CardTitle>
                <Phone className="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{formatCurrency(totalMonthlyCost)}</div>
                <p className="text-xs text-muted-foreground">
                  {numbers.data.filter(n => n.source === 'sip_trunk').length > 0 && (
                    <span className="text-green-600 font-semibold">
                      <Zap className="inline h-3 w-3" /> {t('numbers.sip_saving')}
                    </span>
                  )}
                  {numbers.data.filter(n => n.source === 'sip_trunk').length === 0 && t('numbers.total_cost')}
                </p>
              </CardContent>
            </Card>
          </div>
        )}

        {/* Numbers List */}
        <Card>
          <CardHeader>
            <div className="flex items-center justify-between">
              <div>
                <CardTitle>{t('numbers.assigned_numbers')}</CardTitle>
                <CardDescription>
                  {t('numbers.assigned_description')}
                </CardDescription>
              </div>
              {numbers.data.length > 0 && (
                <div className="relative w-64">
                  <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                  <Input
                    placeholder={t('numbers.search')}
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    className="pl-9 pr-9"
                  />
                  {searchTerm && (
                    <Button
                      variant="ghost"
                      size="sm"
                      className="absolute right-1 top-1 h-7 w-7 p-0"
                      onClick={handleClearSearch}
                    >
                      <X className="h-4 w-4" />
                    </Button>
                  )}
                </div>
              )}
            </div>
          </CardHeader>
          <CardContent>
            {numbers.data.length === 0 ? (
              <div className="text-center py-12">
                <Phone className="mx-auto h-12 w-12 text-muted-foreground mb-4" />
                <h3 className="text-lg font-semibold mb-2">{t('numbers.no_numbers')}</h3>
                <p className="text-muted-foreground mb-6">
                  {t('numbers.no_numbers_description')}
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
                      <TableHead>{t('common.country')}</TableHead>
                      <TableHead>{t('numbers.area_code')}</TableHead>
                      <TableHead>{t('common.capabilities')}</TableHead>
                      <TableHead>{t('numbers.ai_agent')}</TableHead>
                      <TableHead>{t('numbers.monthly_cost')}</TableHead>
                      <TableHead>{t('numbers.calling_rate')}</TableHead>
                      <TableHead>{t('numbers.assigned_date')}</TableHead>
                      <TableHead>{t('common.status')}</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {filteredNumbers.length === 0 ? (
                      <TableRow>
                        <TableCell colSpan={9} className="text-center py-8 text-muted-foreground">
                          {searchTerm ? `No numbers found matching "${searchTerm}"` : 'No numbers available'}
                        </TableCell>
                      </TableRow>
                    ) : (
                      filteredNumbers.map((number) => (
                      <TableRow key={number.id}>
                        <TableCell>
                          <div className="flex flex-col gap-1">
                            <div className="flex items-center gap-2">
                              <span className="font-medium">{number.formatted_number}</span>
                              {number.source === 'sip_trunk' && (
                                <Badge variant="outline" className="bg-green-50 text-green-700 border-green-200">
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
                        <TableCell>
                          {number.ai_agent ? (
                            <div className="flex flex-col gap-1">
                              <Badge
                                variant="default"
                                className={number.ai_agent.active ? "bg-blue-600" : "bg-gray-500"}
                              >
                                <Bot className="mr-1 h-3 w-3" />
                                {number.ai_agent.name}
                              </Badge>
                              <span className="text-xs text-muted-foreground capitalize">
                                {number.ai_agent.type}
                              </span>
                            </div>
                          ) : (
                            <Badge variant="outline" className="text-xs">
                              {t('common.not_assigned')}
                            </Badge>
                          )}
                        </TableCell>
                        <TableCell className="font-medium">
                          {number.source === 'sip_trunk' ? (
                            <div className="flex items-center gap-2">
                              <span className="text-green-600 font-semibold">{t('common.free')}</span>
                              <Zap className="h-3 w-3 text-green-600" />
                              {number.original_monthly_cost && (
                                <span className="text-xs text-muted-foreground line-through">
                                  {formatCurrency(number.original_monthly_cost)}
                                </span>
                              )}
                            </div>
                          ) : (
                            formatCurrency(number.monthly_cost || 0)
                          )}
                        </TableCell>
                        <TableCell>
                          {number.calling_price !== null && number.calling_price !== undefined ? (
                            <div className="flex flex-col">
                              <span className="font-medium text-blue-600">
                                {new Intl.NumberFormat('en-US', {
                                  style: 'currency',
                                  currency: 'USD',
                                  minimumFractionDigits: 4,
                                  maximumFractionDigits: 4,
                                }).format(number.calling_price)}/min
                              </span>
                              <span className="text-xs text-muted-foreground">{t('common.per_minute')}</span>
                            </div>
                          ) : (
                            <Badge variant="outline" className="text-xs text-muted-foreground">
                              {t('common.unavailable')}
                            </Badge>
                          )}
                        </TableCell>
                        <TableCell className="text-sm text-muted-foreground">
                          {formatDate(number.assigned_at)}
                        </TableCell>
                        <TableCell>
                          <Badge variant="default" className="bg-green-600">
                            <CheckCircle className="mr-1 h-3 w-3" />
                            {t('common.active')}
                          </Badge>
                        </TableCell>
                      </TableRow>
                    ))
                    )}
                  </TableBody>
                </Table>

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
                        disabled={numbers.current_page === 1}
                      >
                        {t('common.previous')}
                      </Button>
                      <Button
                        variant="outline"
                        size="sm"
                        disabled={numbers.current_page === numbers.last_page}
                      >
                        {t('common.next')}
                      </Button>
                    </div>
                  </div>
                )}

                {/* Usage Instructions */}
                <Alert className="mt-4">
                  <AlertCircle className="h-4 w-4" />
                  <AlertDescription>
                    <strong>{t('numbers.how_to_use')}</strong> {t('numbers.how_to_use_description')}
                  </AlertDescription>
                </Alert>
              </>
            )}
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  );
}
