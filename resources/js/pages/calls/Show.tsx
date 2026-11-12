import AppLayout from '@/layouts/app-layout';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Phone, Clock, User, MessageSquare } from 'lucide-react';
import { type BreadcrumbItem } from '@/types';
import { useTranslation } from 'react-i18next';

interface Call {
    id: number;
    campaign_id: number;
    campaign_name: string;
    contact_id: number;
    contact_name: string;
    contact_phone: string;
    status: string;
    duration: number;
    direction: string;
    recording_url?: string;
    notes?: string;
    started_at: string;
    ended_at: string;
}

interface Contact {
    id: number | null;
    name: string | null;
    phone_number: string;
}

interface Props {
    call: Call;
    contact: Contact | null;
    breadcrumbs?: BreadcrumbItem[];
}

export default function Show({ call, contact, breadcrumbs }: Props) {
    const { t } = useTranslation();

    const callData = call || {
        id: 0,
        campaign_id: 0,
        campaign_name: '',
        contact_id: 0,
        contact_name: '',
        contact_phone: '',
        status: '',
        duration: 0,
        direction: '',
        recording_url: '',
        notes: '',
        started_at: '',
        ended_at: ''
    };

    const formatDuration = (seconds: number) => {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins}m ${secs}s`;
    };

    const getStatusColor = (status: string) => {
        const colors: Record<string, 'default' | 'destructive' | 'secondary'> = {
            'completed': 'default',
            'failed': 'destructive',
            'no-answer': 'secondary',
            'busy': 'secondary',
        };
        return colors[status] || 'secondary';
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('calls.show_title', { id: callData.id })} />

            <div className="mx-auto max-w-7xl space-y-6">
                <div className="flex items-center gap-4">
                    <Link href="/calls">
                        <Button variant="ghost" size="icon">
                            <ArrowLeft className="h-4 w-4" />
                        </Button>
                    </Link>
                    <Heading
                        title={t('calls.show_title', { id: callData.id })}
                        description={t('calls.show_description')}
                    />
                </div>

                <div className="grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">{t('common.status')}</CardTitle>
                            <Phone className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <Badge variant={getStatusColor(callData.status)}>
                                {callData.status}
                            </Badge>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">{t('calls.duration')}</CardTitle>
                            <Clock className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-lg font-bold">
                                {formatDuration(callData.duration)}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">{t('calls.show_direction')}</CardTitle>
                            <Phone className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-lg font-medium capitalize">
                                {callData.direction}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">{t('calls.campaign')}</CardTitle>
                            <MessageSquare className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <Link
                                href={`/campaigns/${callData.campaign_id}`}
                                className="text-sm text-blue-600 hover:underline"
                            >
                                {callData.campaign_name}
                            </Link>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('calls.show_contact_information')}</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {contact ? (
                                <>
                                    <div>
                                        <div className="flex items-center gap-2 text-sm text-muted-foreground mb-1">
                                            <User className="h-4 w-4" />
                                            <span>{t('calls.show_contact_name')}</span>
                                        </div>
                                        <p className="font-medium">{contact.name || t('common.unknown')}</p>
                                    </div>

                                    <div>
                                        <div className="flex items-center gap-2 text-sm text-muted-foreground mb-1">
                                            <Phone className="h-4 w-4" />
                                            <span>{t('common.phone')}</span>
                                        </div>
                                        <p className="font-medium">{contact.phone_number}</p>
                                    </div>

                                    {contact.id && (
                                        <Button variant="outline" className="w-full" asChild>
                                            <Link href={`/contacts/${contact.id}`}>
                                                {t('calls.show_view_contact')}
                                            </Link>
                                        </Button>
                                    )}
                                </>
                            ) : (
                                <p className="text-sm text-muted-foreground">{t('calls.show_no_contact')}</p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{t('calls.show_timeline')}</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div>
                                <p className="text-sm text-muted-foreground">{t('calls.show_started_at')}</p>
                                <p className="font-medium">
                                    {new Date(callData.started_at).toLocaleString()}
                                </p>
                            </div>

                            <div>
                                <p className="text-sm text-muted-foreground">{t('calls.show_ended_at')}</p>
                                <p className="font-medium">
                                    {new Date(callData.ended_at).toLocaleString()}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {callData.recording_url && (
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('calls.show_recording')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <audio controls className="w-full">
                                <source src={callData.recording_url} type="audio/mpeg" />
                                {t('calls.show_audio_unsupported')}
                            </audio>
                        </CardContent>
                    </Card>
                )}

                {callData.notes && (
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('common.notes')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-muted-foreground">{callData.notes}</p>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
