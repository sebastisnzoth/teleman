import { useState, useEffect } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { 
    Brain,
    TrendingUp,
    TrendingDown,
    Minus,
    Sparkles,
    Target,
    RefreshCw,
    Loader2,
    AlertCircle
} from 'lucide-react';
import axios from 'axios';

interface SentimentStats {
    total_analyzed: number;
    sentiment_breakdown: {
        positive: number;
        neutral: number;
        negative: number;
    };
    lead_quality_breakdown: {
        hot: number;
        warm: number;
        cold: number;
    };
    average_lead_score: number;
    top_intents: Record<string, number>;
}

interface SentimentStatsProps {
    campaignId: number;
}

export function SentimentStats({ campaignId }: SentimentStatsProps) {
    const [stats, setStats] = useState<SentimentStats | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [analyzing, setAnalyzing] = useState(false);

    const loadStats = async () => {
        setLoading(true);
        setError(null);

        try {
            const response = await axios.get(`/api/v1/campaigns/${campaignId}/sentiment-stats`);
            setStats(response.data.data);
        } catch (err: any) {
            setError(err.response?.data?.message || 'Failed to load sentiment stats');
        } finally {
            setLoading(false);
        }
    };

    const analyzeCampaign = async () => {
        setAnalyzing(true);
        setError(null);

        try {
            const response = await axios.post(`/api/v1/campaigns/${campaignId}/analyze-sentiment`);
            
            // Show success message
            const analyzed = response.data.data.analyzed;
            if (analyzed > 0) {
                // Reload stats after analysis
                await loadStats();
            }
        } catch (err: any) {
            setError(err.response?.data?.message || 'Failed to analyze campaign');
        } finally {
            setAnalyzing(false);
        }
    };

    useEffect(() => {
        loadStats();
    }, [campaignId]);

    const getPercentage = (value: number, total: number) => {
        return total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';
    };

    const getSentimentIcon = (sentiment: string) => {
        switch (sentiment) {
            case 'positive':
                return <TrendingUp className="h-5 w-5 text-green-600" />;
            case 'neutral':
                return <Minus className="h-5 w-5 text-gray-600" />;
            case 'negative':
                return <TrendingDown className="h-5 w-5 text-red-600" />;
            default:
                return null;
        }
    };

    const getScoreColor = (score: number) => {
        if (score >= 70) return 'text-green-600';
        if (score >= 50) return 'text-yellow-600';
        return 'text-red-600';
    };

    if (loading && !stats) {
        return (
            <Card>
                <CardContent className="pt-6">
                    <div className="flex items-center justify-center py-12">
                        <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
                    </div>
                </CardContent>
            </Card>
        );
    }

    if (!stats || stats.total_analyzed === 0) {
        return (
            <Card>
                <CardHeader>
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <Brain className="h-6 w-6 text-blue-600" />
                            <div>
                                <CardTitle>AI Sentiment Analysis</CardTitle>
                                <CardDescription>
                                    Analyze call transcripts to identify sentiment and lead quality
                                </CardDescription>
                            </div>
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    {error && (
                        <Alert variant="destructive" className="mb-4">
                            <AlertCircle className="h-4 w-4" />
                            <AlertDescription>{error}</AlertDescription>
                        </Alert>
                    )}

                    <div className="text-center py-12">
                        <Brain className="h-12 w-12 mx-auto mb-4 text-muted-foreground" />
                        <h3 className="text-lg font-semibold mb-2">No Analysis Data Yet</h3>
                        <p className="text-sm text-muted-foreground mb-6">
                            Analyze completed calls to get sentiment insights and identify hot leads
                        </p>
                        <Button
                            onClick={analyzeCampaign}
                            disabled={analyzing}
                        >
                            {analyzing ? (
                                <>
                                    <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                                    Analyzing...
                                </>
                            ) : (
                                <>
                                    <Sparkles className="h-4 w-4 mr-2" />
                                    Analyze Campaign Calls
                                </>
                            )}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        );
    }

    return (
        <div className="space-y-6">
            {/* Overview Card */}
            <Card>
                <CardHeader>
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            <Brain className="h-6 w-6 text-blue-600" />
                            <div>
                                <CardTitle>AI Sentiment Analysis</CardTitle>
                                <CardDescription>
                                    {stats.total_analyzed} calls analyzed
                                </CardDescription>
                            </div>
                        </div>
                        <div className="flex gap-2">
                            <Button
                                onClick={analyzeCampaign}
                                disabled={analyzing}
                                variant="outline"
                                size="sm"
                            >
                                {analyzing ? (
                                    <Loader2 className="h-4 w-4 animate-spin" />
                                ) : (
                                    <>
                                        <Sparkles className="h-4 w-4 mr-2" />
                                        Analyze More
                                    </>
                                )}
                            </Button>
                            <Button
                                onClick={loadStats}
                                disabled={loading}
                                variant="outline"
                                size="sm"
                            >
                                <RefreshCw className="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </CardHeader>
            </Card>

            <div className="grid gap-6 md:grid-cols-2">
                {/* Sentiment Breakdown */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-lg">Sentiment Distribution</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {/* Positive */}
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                {getSentimentIcon('positive')}
                                <span className="font-medium">Positive</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <span className="text-2xl font-bold text-green-600">
                                    {stats.sentiment_breakdown.positive}
                                </span>
                                <span className="text-sm text-muted-foreground">
                                    ({getPercentage(stats.sentiment_breakdown.positive, stats.total_analyzed)}%)
                                </span>
                            </div>
                        </div>
                        <div className="w-full bg-muted rounded-full h-2">
                            <div 
                                className="bg-green-600 h-2 rounded-full transition-all"
                                style={{ width: `${getPercentage(stats.sentiment_breakdown.positive, stats.total_analyzed)}%` }}
                            />
                        </div>

                        {/* Neutral */}
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                {getSentimentIcon('neutral')}
                                <span className="font-medium">Neutral</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <span className="text-2xl font-bold text-gray-600">
                                    {stats.sentiment_breakdown.neutral}
                                </span>
                                <span className="text-sm text-muted-foreground">
                                    ({getPercentage(stats.sentiment_breakdown.neutral, stats.total_analyzed)}%)
                                </span>
                            </div>
                        </div>
                        <div className="w-full bg-muted rounded-full h-2">
                            <div 
                                className="bg-gray-600 h-2 rounded-full transition-all"
                                style={{ width: `${getPercentage(stats.sentiment_breakdown.neutral, stats.total_analyzed)}%` }}
                            />
                        </div>

                        {/* Negative */}
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                {getSentimentIcon('negative')}
                                <span className="font-medium">Negative</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <span className="text-2xl font-bold text-red-600">
                                    {stats.sentiment_breakdown.negative}
                                </span>
                                <span className="text-sm text-muted-foreground">
                                    ({getPercentage(stats.sentiment_breakdown.negative, stats.total_analyzed)}%)
                                </span>
                            </div>
                        </div>
                        <div className="w-full bg-muted rounded-full h-2">
                            <div 
                                className="bg-red-600 h-2 rounded-full transition-all"
                                style={{ width: `${getPercentage(stats.sentiment_breakdown.negative, stats.total_analyzed)}%` }}
                            />
                        </div>
                    </CardContent>
                </Card>

                {/* Lead Quality */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-lg">Lead Quality</CardTitle>
                        <CardDescription>
                            Average Score: <span className={`font-bold ${getScoreColor(stats.average_lead_score)}`}>
                                {stats.average_lead_score}/100
                            </span>
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <div className="grid grid-cols-3 gap-4">
                            {/* Hot */}
                            <div className="text-center">
                                <div className="text-3xl font-bold text-red-600 mb-1">
                                    {stats.lead_quality_breakdown.hot}
                                </div>
                                <div className="text-sm font-medium text-red-600">Hot</div>
                                <div className="text-xs text-muted-foreground">
                                    {getPercentage(stats.lead_quality_breakdown.hot, stats.total_analyzed)}%
                                </div>
                            </div>

                            {/* Warm */}
                            <div className="text-center">
                                <div className="text-3xl font-bold text-orange-600 mb-1">
                                    {stats.lead_quality_breakdown.warm}
                                </div>
                                <div className="text-sm font-medium text-orange-600">Warm</div>
                                <div className="text-xs text-muted-foreground">
                                    {getPercentage(stats.lead_quality_breakdown.warm, stats.total_analyzed)}%
                                </div>
                            </div>

                            {/* Cold */}
                            <div className="text-center">
                                <div className="text-3xl font-bold text-blue-600 mb-1">
                                    {stats.lead_quality_breakdown.cold}
                                </div>
                                <div className="text-sm font-medium text-blue-600">Cold</div>
                                <div className="text-xs text-muted-foreground">
                                    {getPercentage(stats.lead_quality_breakdown.cold, stats.total_analyzed)}%
                                </div>
                            </div>
                        </div>

                        {/* Top Intents */}
                        {Object.keys(stats.top_intents).length > 0 && (
                            <div>
                                <h4 className="font-medium mb-3 flex items-center gap-2">
                                    <Target className="h-4 w-4" />
                                    Top Customer Intents
                                </h4>
                                <div className="space-y-2">
                                    {Object.entries(stats.top_intents).slice(0, 5).map(([intent, count]) => (
                                        <div key={intent} className="flex items-center justify-between">
                                            <span className="text-sm capitalize">
                                                {intent.replace('_', ' ')}
                                            </span>
                                            <span className="text-sm font-medium">{count}</span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
