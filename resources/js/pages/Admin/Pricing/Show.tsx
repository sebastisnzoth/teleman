import AppLayout from '@/layouts/app-layout';
import { useTranslation } from 'react-i18next';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Edit } from 'lucide-react';

interface PricingRule {
    id: number;
    country_code: string;
    service_type: 'call' | 'sms' | 'phone_number';
    base_cost: number;
    markup_percentage: number;
    selling_price: number;
    tier: string;
    is_active: boolean;
    created_at: string;
}

interface Props {
    pricingRule: PricingRule;
}

export default function ShowPricingRule({ pricingRule }: Props) {
  const { t } = useTranslation();
    const getServiceLabel = (type: string) => {
        switch (type) {
            case 'call':
                return 'Voice Call';
            case 'sms':
                return 'SMS';
            case 'phone_number':
                return 'Phone Number';
            default:
                return type;
        }
    };

    return (
        <AppLayout>
            <Head title={`Pricing Rule - ${pricingRule.country_code} ${getServiceLabel(pricingRule.service_type)}`} />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <Heading
                        title={t('admin.pricing.pricing_rule_details')}
                        description={`${pricingRule.country_code} - ${getServiceLabel(pricingRule.service_type)}`}
                    />
                    <div className="flex items-center space-x-2">
                        <Button variant="outline" asChild>
                            <Link href={`/admin/pricing/${pricingRule.id}/edit`}>
                                <Edit className="mr-2 h-4 w-4" />
                                Edit
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href="/admin/pricing">
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Back
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('admin.pricing.basic_information')}</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <p className="text-sm text-muted-foreground">Country Code</p>
                                <p className="text-lg font-semibold">{pricingRule.country_code}</p>
                            </div>
                            <div>
                                <p className="text-sm text-muted-foreground">Service Type</p>
                                <p className="text-lg font-semibold">{getServiceLabel(pricingRule.service_type)}</p>
                            </div>
                            <div>
                                <p className="text-sm text-muted-foreground">Pricing Tier</p>
                                <Badge>{pricingRule.tier}</Badge>
                            </div>
                            <div>
                                <p className="text-sm text-muted-foreground">Status</p>
                                <Badge variant={pricingRule.is_active ? 'default' : 'secondary'}>
                                    {pricingRule.is_active ? 'Active' : 'Inactive'}
                                </Badge>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{t('admin.pricing.pricing_details')}</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <p className="text-sm text-muted-foreground">Base Cost</p>
                                <p className="text-lg font-semibold">${Number(pricingRule.base_cost).toFixed(4)}</p>
                            </div>
                            <div>
                                <p className="text-sm text-muted-foreground">Markup Percentage</p>
                                <p className="text-lg font-semibold">{Number(pricingRule.markup_percentage).toFixed(2)}%</p>
                            </div>
                            <div>
                                <p className="text-sm text-muted-foreground">Selling Price</p>
                                <p className="text-2xl font-bold text-green-600">
                                    ${Number(pricingRule.selling_price).toFixed(4)}
                                </p>
                            </div>
                            <div>
                                <p className="text-sm text-muted-foreground">Profit Margin</p>
                                <p className="text-lg font-semibold text-green-600">
                                    ${(Number(pricingRule.selling_price) - Number(pricingRule.base_cost)).toFixed(4)}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>{t('admin.pricing.additional_information')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-2">
                            <div>
                                <p className="text-sm text-muted-foreground">Created At</p>
                                <p className="text-base">{new Date(pricingRule.created_at).toLocaleString()}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
