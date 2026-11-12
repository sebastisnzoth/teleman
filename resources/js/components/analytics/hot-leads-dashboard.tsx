import { useState, useEffect } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { 
    Flame, 
    TrendingUp, 
    Phone, 
    Clock,
    Sparkles,
    RefreshCw,
    Loader2,
    AlertCircle
} from 'lucide-react';
import axios from 'axios';
import { formatDistanceToNow } from 'date-fns';

interface HotLead {
    call_id: number;
    campaign_id: number;
    campaign_name: string;
    contact_id: number;
    contact_name: string;
    phone_number: string;
    lead_score: number;
    sentiment: 'positive' | 'neutral' | 'negative';
    summary: string;
    key_intents: string[];
    called_at: string;
}

interface HotLeadsDashboardProps {
    campaignId?: number; // Optional: filter by campaign
    onContactClick?: (contactId: number) => void;
}

export function HotLeadsDashboard({ campaignId, onContactClick }: HotLeadsDashboardProps) {
    const [hotLeads, setHotLeads] = useState<HotLead[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    const loadHotLeads = async () => {
        setLoading(true);
        setError(null);

        try {
            const url = campaignId 
                ? `/api/v1/campaigns/${campaignId}/hot-leads`
                : '/api/v1/hot-leads';

            const response = await axios.get(url);
            setHotLeads(response.data.data.hot_leads);
        } catch (err: any) {
            setError(err.response?.data?.message || 'Failed to load hot leads');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        loadHotLeads();
    }, [campaignId]);

    const getSentimentColor = (sentiment: string) => {
        switch (sentiment) {
            case 'positive':
                return 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
            case 'neutral':
                return 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200';
            case 'negative':
                return 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200';
            default:
                return 'bg-gray-100 text-gray-800';
        }
    };

    const getScoreColor = (score: number) => {
        if (score >= 80) return 'text-red-600 dark:text-red-400';
        if (score >= 60) return 'text-orange-600 dark:text-orange-400';
        return 'text-yellow-600 dark:text-yellow-400';
    };

    return (
        <div className="space-y-6">
            <Card>
                <CardHeader>
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <Flame className="h-6 w-6 text-red-600" />
                            <div>
                                <CardTitle>Hot Leads</CardTitle>
                                <CardDescription>
                                    High-priority leads identified by AI analysis
                                </CardDescription>
                            </div>
                        </div>
                        <Button
                            onClick={loadHotLeads}
                            disabled={loading}
                            variant="outline"
                            size="sm"
                        >
                            {loading ? (
                                <Loader2 className="h-4 w-4 animate-spin" />
                            ) : (
                                <RefreshCw className="h-4 w-4" />
                            )}
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    {error && (
                        <Alert variant="destructive" className="mb-4">
                            <AlertCircle className="h-4 w-4" />
                            <AlertDescription>{error}</AlertDescription>
                        </Alert>
                    )}

                    {loading && hotLeads.length === 0 ? (
                        <div className="flex items-center justify-center py-12">
                            <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
                        </div>
                    ) : hotLeads.length === 0 ? (
                        <div className="text-center py-12">
                            <Flame className="h-12 w-12 mx-auto mb-4 text-muted-foreground" />
                            <h3 className="text-lg font-semibold mb-2">No Hot Leads Yet</h3>
                            <p className="text-sm text-muted-foreground">
                                Hot leads will appear here after calls are analyzed by AI
                            </p>
                        </div>
                    ) : (
                        <div className="space-y-4">
                            {hotLeads.map((lead) => (
                                <Card 
                                    key={lead.call_id} 
                                    className="border-l-4 border-l-red-500 hover:shadow-md transition-shadow"
                                >
                                    <CardContent className="pt-6">
                                        <div className="flex items-start justify-between gap-4">
                                            <div className="flex-1 space-y-3">
                                                {/* Header */}
                                                <div className="flex items-center gap-3">
                                                    <div className="flex items-center gap-2">
                                                        <Flame className={`h-5 w-5 ${getScoreColor(lead.lead_score)}`} />
                                                        <h3 className="font-semibold text-lg">
                                                            {lead.contact_name || 'Unknown Contact'}
                                                        </h3>
                                                    </div>
                                                    <Badge className="bg-red-600 hover:bg-red-700">
                                                        Score: {lead.lead_score}/100
                                                    </Badge>
                                                    <Badge className={getSentimentColor(lead.sentiment)}>
                                                        {lead.sentiment}
                                                    </Badge>
                                                </div>

                                                {/* Campaign & Contact Info */}
                                                <div className="flex items-center gap-4 text-sm text-muted-foreground">
                                                    {lead.campaign_name && (
                                                        <span className="flex items-center gap-1">
                                                            <TrendingUp className="h-4 w-4" />
                                                            {lead.campaign_name}
                                                        </span>
                                                    )}
                                                    <span className="flex items-center gap-1">
                                                        <Phone className="h-4 w-4" />
                                                        {lead.phone_number}
                                                    </span>
                                                    <span className="flex items-center gap-1">
                                                        <Clock className="h-4 w-4" />
                                                        {formatDistanceToNow(new Date(lead.called_at), { addSuffix: true })}
                                                    </span>
                                                </div>

                                                {/* AI Summary */}
                                                <div className="bg-muted/50 rounded-lg p-3">
                                                    <div className="flex items-start gap-2">
                                                        <Sparkles className="h-4 w-4 text-blue-600 mt-0.5 flex-shrink-0" />
                                                        <p className="text-sm">{lead.summary}</p>
                                                    </div>
                                                </div>

                                                {/* Key Intents */}
                                                {lead.key_intents && lead.key_intents.length > 0 && (
                                                    <div className="flex flex-wrap gap-2">
                                                        {lead.key_intents.map((intent, idx) => (
                                                            <Badge 
                                                                key={idx} 
                                                                variant="outline"
                                                                className="text-xs"
                                                            >
                                                                {intent.replace('_', ' ')}
                                                            </Badge>
                                                        ))}
                                                    </div>
                                                )}
                                            </div>

                                            {/* Actions */}
                                            <div className="flex flex-col gap-2">
                                                <Button
                                                    size="sm"
                                                    onClick={() => onContactClick?.(lead.contact_id)}
                                                >
                                                    <Phone className="h-4 w-4 mr-2" />
                                                    Call Now
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => window.open(`/calls/${lead.call_id}`, '_blank')}
                                                >
                                                    View Call
                                                </Button>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    )}

                    {hotLeads.length > 0 && (
                        <div className="mt-4 text-center">
                            <p className="text-sm text-muted-foreground">
                                Showing {hotLeads.length} hot {hotLeads.length === 1 ? 'lead' : 'leads'}
                            </p>
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
