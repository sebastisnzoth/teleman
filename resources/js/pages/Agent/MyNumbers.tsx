import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Phone, Globe } from 'lucide-react';

interface PhoneNumber {
    id: number;
    number: string;
    formatted_number: string;
    friendly_name: string;
    capabilities: {
        voice: boolean;
        sms: boolean;
        mms: boolean;
    };
    source: 'twilio_direct' | 'sip_trunk';
    trunk_name?: string;
    status: string;
}

interface Props {
    numbers: PhoneNumber[];
    totalNumbers: number;
}

export default function MyNumbers({ numbers, totalNumbers }: Props) {
    const { t } = useTranslation();
    return (
        <AppLayout>
            <Head title={t('numbers.my_phone_numbers')} />

            <div className="space-y-6">
                <div>
                    <h1 className="text-3xl font-bold">{t('numbers.my_phone_numbers')}</h1>
                    <p className="text-muted-foreground">
                        {t('numbers.assigned_to_you')}
                    </p>
                </div>

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {numbers.length === 0 ? (
                        <Card className="col-span-full">
                            <CardContent className="flex flex-col items-center justify-center py-10">
                                <Phone className="h-12 w-12 text-muted-foreground mb-4" />
                                <p className="text-lg font-medium">{t('numbers.no_numbers_assigned')}</p>
                                <p className="text-sm text-muted-foreground text-center mt-2">
                                    {t('numbers.no_numbers_assigned_description')}<br />
                                    {t('numbers.contact_admin_for_numbers')}
                                </p>
                            </CardContent>
                        </Card>
                    ) : (
                        numbers.map((number) => (
                            <Card key={`${number.source}-${number.id}`}>
                                <CardHeader>
                                    <div className="flex items-start justify-between">
                                        <div className="flex-1">
                                            <CardTitle className="text-lg">
                                                {number.formatted_number}
                                            </CardTitle>
                                            <CardDescription className="mt-1">
                                                {number.friendly_name || t('numbers.no_name_set')}
                                            </CardDescription>
                                        </div>
                                        <Badge
                                            variant={number.source === 'sip_trunk' ? 'default' : 'secondary'}
                                        >
                                            {number.source === 'sip_trunk' ? (
                                                <><Globe className="h-3 w-3 mr-1" /> {t('numbers.source_sip')}</>
                                            ) : (
                                                <><Phone className="h-3 w-3 mr-1" /> {t('numbers.source_direct')}</>
                                            )}
                                        </Badge>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-2">
                                        {number.source === 'sip_trunk' && number.trunk_name && (
                                            <div className="text-sm">
                                                <span className="text-muted-foreground">{t('numbers.trunk_label')}</span>{' '}
                                                <span className="font-medium">{number.trunk_name}</span>
                                            </div>
                                        )}
                                        <div className="flex flex-wrap gap-2">
                                            {number.capabilities.voice && (
                                                <Badge variant="outline" className="text-xs">
                                                    {t('numbers.capability_voice')}
                                                </Badge>
                                            )}
                                            {number.capabilities.sms && (
                                                <Badge variant="outline" className="text-xs">
                                                    {t('numbers.capability_sms')}
                                                </Badge>
                                            )}
                                            {number.capabilities.mms && (
                                                <Badge variant="outline" className="text-xs">
                                                    {t('numbers.capability_mms')}
                                                </Badge>
                                            )}
                                        </div>
                                        <div className="text-xs text-muted-foreground pt-2 border-t">
                                            {t('numbers.softphone_hint')}
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        ))
                    )}
                </div>

                {totalNumbers > 0 && (
                    <div className="text-sm text-muted-foreground text-center pt-4 border-t">
                        {t('numbers.numbers_assigned_count', { count: totalNumbers })}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
