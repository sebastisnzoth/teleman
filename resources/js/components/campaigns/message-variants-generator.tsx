import { useState, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Loader2, Sparkles, TrendingUp } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import axios from 'axios';

interface MessageVariant {
    id: number;
    variant_label: string;
    variant_name: string;
    message_text: string;
    tone_description: string;
    is_active: boolean;
    is_winner: boolean;
    sent_count: number;
    answered_count: number;
    completed_count: number;
    positive_response_count: number;
    answer_rate: number;
    completion_rate: number;
    effectiveness_score: number;
}

interface MessageVariantsGeneratorProps {
    campaignId: number;
    baseMessage: string;
    description: string;
    onVariantsGenerated?: (variants: MessageVariant[]) => void;
}

export function MessageVariantsGenerator({
    campaignId,
    baseMessage,
    description,
    onVariantsGenerated,
}: MessageVariantsGeneratorProps) {
    const [variants, setVariants] = useState<MessageVariant[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [loadingVariants, setLoadingVariants] = useState(false);

    // Load existing variants on mount
    useEffect(() => {
        loadVariants();
    }, [campaignId]);

    const generateVariants = async () => {
        if (!baseMessage.trim() || !description.trim()) {
            setError('Please provide both a base message and description');
            return;
        }

        setLoading(true);
        setError(null);

        try {
            const response = await axios.post(`/api/v1/campaigns/${campaignId}/variants/generate`, {
                base_message: baseMessage,
                description: description,
            });

            setVariants(response.data.variants);
            onVariantsGenerated?.(response.data.variants);
        } catch (err: any) {
            setError(err.response?.data?.message || 'Failed to generate variants');
        } finally {
            setLoading(false);
        }
    };

    const loadVariants = async () => {
        setLoadingVariants(true);
        setError(null);
        try {
            const response = await axios.get(`/api/v1/campaigns/${campaignId}/variants`);
            setVariants(response.data.variants || []);
        } catch (err: any) {
            console.error('Failed to load variants:', err);
            // Don't show error for empty variants
            if (err.response?.status !== 404) {
                setError(err.response?.data?.message || 'Failed to load variants');
            }
        } finally {
            setLoadingVariants(false);
        }
    };

    const toggleVariantActive = async (variantId: number) => {
        try {
            await axios.patch(`/api/v1/campaigns/${campaignId}/variants/${variantId}/toggle-active`);
            loadVariants();
        } catch (err: any) {
            setError(err.response?.data?.message || 'Failed to toggle variant');
        }
    };

    const selectWinner = async (variantId: number) => {
        try {
            await axios.post(`/api/v1/campaigns/${campaignId}/variants/${variantId}/select-winner`);
            loadVariants();
        } catch (err: any) {
            setError(err.response?.data?.message || 'Failed to select winner');
        }
    };

    return (
        <div className="space-y-4">
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Sparkles className="h-5 w-5 text-blue-600" />
                        AI Message Variants A/B Testing
                    </CardTitle>
                    <CardDescription>
                        Generate 5 AI-powered message variants to test different tones and approaches. 
                        The system will automatically track performance and help you identify the most effective message.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="flex items-center gap-4">
                        <Button
                            onClick={generateVariants}
                            disabled={loading || !baseMessage.trim() || !description.trim()}
                            className="flex items-center gap-2"
                        >
                            {loading ? (
                                <>
                                    <Loader2 className="h-4 w-4 animate-spin" />
                                    Generating...
                                </>
                            ) : (
                                <>
                                    <Sparkles className="h-4 w-4" />
                                    Generate AI Variants
                                </>
                            )}
                        </Button>

                        {variants.length > 0 && (
                            <Button
                                variant="outline"
                                onClick={loadVariants}
                                disabled={loadingVariants}
                            >
                                {loadingVariants ? (
                                    <Loader2 className="h-4 w-4 animate-spin" />
                                ) : (
                                    'Refresh Variants'
                                )}
                            </Button>
                        )}
                    </div>

                    {error && (
                        <Alert variant="destructive" className="mt-4">
                            <AlertDescription>{error}</AlertDescription>
                        </Alert>
                    )}
                </CardContent>
            </Card>

            {variants.length > 0 && (
                <div className="space-y-4">
                    <div className="flex items-center justify-between">
                        <h3 className="text-lg font-semibold">Message Variants ({variants.length})</h3>
                        <Badge variant="outline">A/B Testing Enabled</Badge>
                    </div>

                    <div className="grid gap-4">
                        {variants.map((variant) => (
                            <Card key={variant.id} className={variant.is_winner ? 'border-green-500' : ''}>
                                <CardHeader>
                                    <div className="flex items-start justify-between">
                                        <div className="space-y-1">
                                            <div className="flex items-center gap-2">
                                                <CardTitle className="text-base">
                                                    [{variant.variant_label}] {variant.variant_name}
                                                </CardTitle>
                                                {variant.is_winner && (
                                                    <Badge variant="default" className="bg-green-600">
                                                        <TrendingUp className="h-3 w-3 mr-1" />
                                                        Winner
                                                    </Badge>
                                                )}
                                                <Badge variant={variant.is_active ? 'default' : 'secondary'}>
                                                    {variant.is_active ? 'Active' : 'Inactive'}
                                                </Badge>
                                            </div>
                                            <CardDescription className="text-sm">
                                                {variant.tone_description}
                                            </CardDescription>
                                        </div>
                                        <div className="flex gap-2">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() => toggleVariantActive(variant.id)}
                                            >
                                                {variant.is_active ? 'Deactivate' : 'Activate'}
                                            </Button>
                                            {variant.sent_count >= 50 && !variant.is_winner && (
                                                <Button
                                                    size="sm"
                                                    onClick={() => selectWinner(variant.id)}
                                                >
                                                    Select Winner
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-3">
                                        <div className="p-3 bg-muted rounded-md">
                                            <p className="text-sm">{variant.message_text}</p>
                                        </div>

                                        {variant.sent_count > 0 && (
                                            <div className="grid grid-cols-4 gap-4 text-sm">
                                                <div>
                                                    <p className="text-muted-foreground">Sent</p>
                                                    <p className="font-semibold">{variant.sent_count}</p>
                                                </div>
                                                <div>
                                                    <p className="text-muted-foreground">Answered</p>
                                                    <p className="font-semibold">
                                                        {variant.answered_count} ({variant.answer_rate.toFixed(1)}%)
                                                    </p>
                                                </div>
                                                <div>
                                                    <p className="text-muted-foreground">Completed</p>
                                                    <p className="font-semibold">
                                                        {variant.completed_count} ({variant.completion_rate.toFixed(1)}%)
                                                    </p>
                                                </div>
                                                <div>
                                                    <p className="text-muted-foreground">Effectiveness</p>
                                                    <p className="font-semibold">
                                                        {variant.effectiveness_score.toFixed(1)}%
                                                    </p>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>

                    <Alert>
                        <AlertDescription>
                            💡 Active variants will be randomly selected for A/B testing. Winner selection requires a minimum of 50 samples per variant.
                        </AlertDescription>
                    </Alert>
                </div>
            )}
        </div>
    );
}
