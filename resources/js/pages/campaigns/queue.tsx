import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import {
    ArrowLeft,
    Phone,
    SkipForward,
    ChevronLeft,
    ChevronRight,
    User,
    PhoneCall,
    CheckCircle2,
    XCircle,
    Clock,
    RefreshCw,
} from 'lucide-react';
import { useState, useEffect, useCallback } from 'react';
import { useSoftphone } from '@/contexts/SoftphoneContext';
import { type BreadcrumbItem } from '@/types';
import { formatPhone } from '@/lib/phone';

interface CampaignContact {
    id: number;
    phone_number: string;
    first_name: string | null;
    last_name: string | null;
    status: 'pending' | 'calling' | 'completed' | 'failed';
    call_attempts: number;
    contact_id: number | null;
}

interface AssignedAgent {
    id: number;
    name: string;
    email: string;
}

interface Campaign {
    id: number;
    name: string;
    status: string;
    assigned_agent_id: number | null;
    assigned_agent?: AssignedAgent | null;
    campaign_contacts_count: number;
}

interface Stats {
    total: number;
    pending: number;
    calling: number;
    completed: number;
    failed: number;
}

interface Props {
    campaign: Campaign;
    contacts: CampaignContact[];
    stats: Stats;
}

const statusConfig: Record<string, { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline' }> = {
    pending: { label: 'Pending', variant: 'secondary' },
    calling: { label: 'Calling', variant: 'default' },
    completed: { label: 'Completed', variant: 'outline' },
    failed: { label: 'Failed', variant: 'destructive' },
};

export default function CampaignQueue({ campaign, contacts, stats }: Props) {
    const { callContact, callState, deviceState, setOnDispositionSavedCallback } = useSoftphone();
    const [currentIndex, setCurrentIndex] = useState(0);
    const [localContacts, setLocalContacts] = useState<CampaignContact[]>(contacts);

    const currentContact = localContacts[currentIndex] || null;
    const totalContacts = localContacts.length;
    const canMakeCalls = deviceState === 'registered';
    const isCallActive = ['dialing', 'ringing', 'connected'].includes(callState);

    // Sync contacts from props (for page reloads)
    useEffect(() => {
        setLocalContacts(contacts);
    }, [contacts]);

    const handleCallCurrent = useCallback(() => {
        if (!currentContact) return;
        const name = [currentContact.first_name, currentContact.last_name].filter(Boolean).join(' ');
        callContact(
            currentContact.phone_number,
            currentContact.contact_id ?? 0,
            name || currentContact.phone_number,
            currentContact.id,
        );
        // Optimistically mark as calling in local state
        setLocalContacts((prev) =>
            prev.map((c) => (c.id === currentContact.id ? { ...c, status: 'calling' } : c)),
        );
    }, [currentContact, callContact]);

    const handleNext = useCallback(() => {
        if (currentIndex < totalContacts - 1) setCurrentIndex((i) => i + 1);
    }, [currentIndex, totalContacts]);

    const handlePrev = useCallback(() => {
        if (currentIndex > 0) setCurrentIndex((i) => i - 1);
    }, [currentIndex]);

    const handleSkip = useCallback(() => {
        handleNext();
    }, [handleNext]);

    const handleRefresh = useCallback(() => {
        router.reload({ only: ['contacts', 'stats'] });
    }, []);

    // Auto-advance to next pending contact after disposition is saved
    useEffect(() => {
        const advance = () => {
            setCurrentIndex((prev) => {
                // Find next pending or failed contact after current
                const nextIdx = localContacts.findIndex(
                    (c, i) => i > prev && (c.status === 'pending' || c.status === 'failed'),
                );
                if (nextIdx !== -1) return nextIdx;
                // No more ahead — try from the beginning
                const fromStart = localContacts.findIndex(
                    (c, i) => i !== prev && (c.status === 'pending' || c.status === 'failed'),
                );
                return fromStart !== -1 ? fromStart : prev;
            });
        };
        setOnDispositionSavedCallback(() => advance);
        return () => setOnDispositionSavedCallback(null);
    }, [localContacts, setOnDispositionSavedCallback]);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Campaigns', href: '/campaigns' },
        { title: campaign.name, href: `/campaigns/${campaign.id}` },
        { title: 'Call Queue', href: `/campaigns/${campaign.id}/queue` },
    ];

    const progressPercent = stats.total > 0 ? Math.round((stats.completed / stats.total) * 100) : 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Call Queue — ${campaign.name}`} />

            <div className="mx-auto max-w-5xl space-y-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-3">
                        <Link href={`/campaigns/${campaign.id}`}>
                            <Button variant="outline" size="icon">
                                <ArrowLeft className="h-4 w-4" />
                            </Button>
                        </Link>
                        <div>
                            <h1 className="flex items-center gap-2 text-2xl font-bold">
                                <PhoneCall className="h-6 w-6" />
                                Call Queue
                            </h1>
                            <p className="text-sm text-muted-foreground">{campaign.name}</p>
                        </div>
                    </div>
                    <Button variant="outline" size="sm" onClick={handleRefresh}>
                        <RefreshCw className="mr-2 h-4 w-4" />
                        Refresh
                    </Button>
                </div>

                {/* Stats row */}
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <Card>
                        <CardContent className="p-4">
                            <p className="text-sm text-muted-foreground">Pending</p>
                            <p className="mt-1 text-2xl font-bold">{stats.pending}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <p className="text-sm text-muted-foreground">Completed</p>
                            <p className="mt-1 text-2xl font-bold text-green-600">{stats.completed}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <p className="text-sm text-muted-foreground">Failed</p>
                            <p className="mt-1 text-2xl font-bold text-red-600">{stats.failed}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="p-4">
                            <p className="text-sm text-muted-foreground">Progress</p>
                            <p className="mt-1 text-2xl font-bold">{progressPercent}%</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Progress bar */}
                <div className="space-y-1">
                    <div className="flex justify-between text-sm text-muted-foreground">
                        <span>{currentIndex + 1} of {totalContacts}</span>
                        <span>{stats.completed} completed</span>
                    </div>
                    <Progress value={progressPercent} className="h-2" />
                </div>

                {totalContacts === 0 ? (
                    <Card className="p-12 text-center">
                        <p className="text-lg text-muted-foreground">No contacts in this campaign yet.</p>
                        <Link href={`/campaigns/${campaign.id}`}>
                            <Button className="mt-4">Upload Contacts</Button>
                        </Link>
                    </Card>
                ) : (
                    <>
                        {/* Current contact card */}
                        {currentContact && (
                            <Card className="p-6">
                                <div className="space-y-4">
                                    <div className="flex items-start justify-between">
                                        <div className="flex items-center gap-3">
                                            <div className="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10">
                                                <User className="h-6 w-6 text-primary" />
                                            </div>
                                            <div>
                                                <h2 className="text-xl font-bold">
                                                    {[currentContact.first_name, currentContact.last_name].filter(Boolean).join(' ') || 'Unknown Contact'}
                                                </h2>
                                                <p className="font-mono text-lg text-muted-foreground">
                                                    {formatPhone(currentContact.phone_number)}
                                                </p>
                                            </div>
                                        </div>
                                        <Badge variant={statusConfig[currentContact.status]?.variant ?? 'outline'}>
                                            {statusConfig[currentContact.status]?.label ?? currentContact.status}
                                        </Badge>
                                    </div>

                                    <div className="flex items-center gap-4 text-sm text-muted-foreground">
                                        <span className="flex items-center gap-1">
                                            <Clock className="h-4 w-4" />
                                            {currentContact.call_attempts} attempt{currentContact.call_attempts !== 1 ? 's' : ''}
                                        </span>
                                        {currentContact.status === 'completed' && (
                                            <span className="flex items-center gap-1 text-green-600">
                                                <CheckCircle2 className="h-4 w-4" />
                                                Completed
                                            </span>
                                        )}
                                        {currentContact.status === 'failed' && (
                                            <span className="flex items-center gap-1 text-red-600">
                                                <XCircle className="h-4 w-4" />
                                                Failed — can retry
                                            </span>
                                        )}
                                    </div>
                                </div>

                                {/* Navigation + Call */}
                                <div className="mt-6 flex items-center justify-between border-t pt-4">
                                    <Button
                                        variant="outline"
                                        onClick={handlePrev}
                                        disabled={currentIndex === 0}
                                    >
                                        <ChevronLeft className="mr-1 h-4 w-4" />
                                        Previous
                                    </Button>

                                    <div className="flex gap-2">
                                        <Button
                                            variant="outline"
                                            onClick={handleSkip}
                                            disabled={currentIndex >= totalContacts - 1}
                                        >
                                            <SkipForward className="mr-1 h-4 w-4" />
                                            Skip
                                        </Button>
                                        <Button
                                            size="lg"
                                            onClick={handleCallCurrent}
                                            disabled={!canMakeCalls || isCallActive}
                                            className="bg-green-600 px-8 hover:bg-green-700"
                                        >
                                            <Phone className="mr-2 h-5 w-5" />
                                            {isCallActive ? 'Call in Progress' : 'Call Now'}
                                        </Button>
                                    </div>

                                    <Button
                                        variant="outline"
                                        onClick={handleNext}
                                        disabled={currentIndex >= totalContacts - 1}
                                    >
                                        Next
                                        <ChevronRight className="ml-1 h-4 w-4" />
                                    </Button>
                                </div>
                            </Card>
                        )}

                        {/* Contact list */}
                        <Card>
                            <CardHeader className="pb-3">
                                <div className="flex items-center justify-between">
                                    <CardTitle>Contact Queue</CardTitle>
                                    <CardDescription>{totalContacts} contacts</CardDescription>
                                </div>
                            </CardHeader>
                            <CardContent className="p-0">
                                <div className="max-h-72 overflow-y-auto">
                                    {localContacts.map((contact, idx) => (
                                        <button
                                            key={contact.id}
                                            onClick={() => setCurrentIndex(idx)}
                                            className={`flex w-full items-center justify-between px-4 py-2.5 text-left text-sm transition-colors ${
                                                idx === currentIndex
                                                    ? 'bg-primary text-primary-foreground'
                                                    : 'hover:bg-muted'
                                            }`}
                                        >
                                            <span className="truncate">
                                                <span className={`mr-1 font-medium ${idx === currentIndex ? 'text-primary-foreground/70' : 'text-muted-foreground'}`}>
                                                    {idx + 1}.
                                                </span>
                                                {[contact.first_name, contact.last_name].filter(Boolean).join(' ') || contact.phone_number}
                                                <span className={`ml-2 font-mono text-xs ${idx === currentIndex ? 'text-primary-foreground/70' : 'text-muted-foreground'}`}>
                                                    {formatPhone(contact.phone_number)}
                                                </span>
                                            </span>
                                            <Badge
                                                variant={statusConfig[contact.status]?.variant ?? 'outline'}
                                                className={`ml-2 shrink-0 text-[10px] ${idx === currentIndex ? 'opacity-80' : ''}`}
                                            >
                                                {statusConfig[contact.status]?.label ?? contact.status}
                                            </Badge>
                                        </button>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    </>
                )}
            </div>
        </AppLayout>
    );
}
