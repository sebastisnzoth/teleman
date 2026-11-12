import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Loader2, Sparkles, AlertCircle } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { QualityBadge } from './quality-badge';
import { IssuesList } from './issues-list';
import { SuggestionsReview } from './suggestions-review';
import axios from 'axios';

interface ContactQualityAnalysis {
    quality_score: number;
    issues: Array<{
        field: string;
        issue: string;
        severity: 'low' | 'medium' | 'high';
    }>;
    suggestions: {
        [key: string]: string;
    };
    duplicate_risk: 'low' | 'medium' | 'high';
    is_valid: boolean;
}

interface ContactQualityCheckerProps {
    contactId?: number;
    contactData?: any;
    onApply?: (contactId: number) => void;
    showApplyButton?: boolean;
}

export function ContactQualityChecker({
    contactId,
    contactData,
    onApply,
    showApplyButton = true,
}: ContactQualityCheckerProps) {
    const [analysis, setAnalysis] = useState<ContactQualityAnalysis | null>(null);
    const [loading, setLoading] = useState(false);
    const [applying, setApplying] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [success, setSuccess] = useState<string | null>(null);

    const checkQuality = async () => {
        if (!contactId) {
            setError('No contact ID provided');
            return;
        }

        setLoading(true);
        setError(null);

        try {
            const response = await axios.get(`/api/v1/contacts/${contactId}/quality/check`);
            setAnalysis(response.data.analysis);
        } catch (err: any) {
            setError(err.response?.data?.message || 'Failed to check contact quality');
        } finally {
            setLoading(false);
        }
    };

    const applySuggestions = async (approvedFields: string[]) => {
        if (!contactId) return;

        setApplying(true);
        setError(null);
        setSuccess(null);

        try {
            const response = await axios.post(`/api/v1/contacts/${contactId}/quality/apply-suggestions`);
            setSuccess('✅ Suggestions applied successfully! Contact data has been updated.');
            setAnalysis(null); // Clear analysis to show fresh state
            onApply?.(contactId);
            
            // Wait a moment before reloading
            setTimeout(() => {
                setSuccess(null);
            }, 3000);
        } catch (err: any) {
            setError(err.response?.data?.message || 'Failed to apply suggestions');
        } finally {
            setApplying(false);
        }
    };

    const getDuplicateRiskColor = (risk: string) => {
        switch (risk) {
            case 'high':
                return 'bg-red-500/10 text-red-700 dark:text-red-400 border-red-500/20';
            case 'medium':
                return 'bg-yellow-500/10 text-yellow-700 dark:text-yellow-400 border-yellow-500/20';
            default:
                return 'bg-green-500/10 text-green-700 dark:text-green-400 border-green-500/20';
        }
    };

    return (
        <div className="space-y-4">
            {!analysis && (
                <Button
                    onClick={checkQuality}
                    disabled={loading}
                    className="flex items-center gap-2"
                >
                    {loading ? (
                        <>
                            <Loader2 className="h-4 w-4 animate-spin" />
                            Analyzing...
                        </>
                    ) : (
                        <>
                            <Sparkles className="h-4 w-4" />
                            Check Data Quality
                        </>
                    )}
                </Button>
            )}

            {error && (
                <Alert variant="destructive">
                    <AlertCircle className="h-4 w-4" />
                    <AlertDescription>{error}</AlertDescription>
                </Alert>
            )}

            {success && (
                <Alert className="bg-green-50 border-green-200 dark:bg-green-950 dark:border-green-800">
                    <AlertDescription className="text-green-800 dark:text-green-200">{success}</AlertDescription>
                </Alert>
            )}

            {analysis && (
                <div className="space-y-4">
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle>Quality Analysis</CardTitle>
                                    <CardDescription>AI-powered data validation</CardDescription>
                                </div>
                                <QualityBadge score={analysis.quality_score} />
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <p className="text-sm text-muted-foreground mb-1">Quality Score</p>
                                    <p className="text-2xl font-bold">{analysis.quality_score}/100</p>
                                </div>
                                <div>
                                    <p className="text-sm text-muted-foreground mb-1">Duplicate Risk</p>
                                    <span
                                        className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium capitalize ${getDuplicateRiskColor(
                                            analysis.duplicate_risk
                                        )}`}
                                    >
                                        {analysis.duplicate_risk}
                                    </span>
                                </div>
                            </div>

                            <div className="flex items-center gap-2 p-3 rounded-md border">
                                {analysis.is_valid ? (
                                    <div className="flex items-center gap-2 text-green-600">
                                        <div className="h-2 w-2 rounded-full bg-green-600" />
                                        <span className="text-sm font-medium">Valid Contact</span>
                                    </div>
                                ) : (
                                    <div className="flex items-center gap-2 text-red-600">
                                        <AlertCircle className="h-4 w-4" />
                                        <span className="text-sm font-medium">Invalid Contact</span>
                                    </div>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {analysis.issues.length > 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Issues Found</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <IssuesList issues={analysis.issues} />
                            </CardContent>
                        </Card>
                    )}

                    {Object.keys(analysis.suggestions).length > 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle>AI Suggestions</CardTitle>
                                <CardDescription>
                                    Recommended changes to improve data quality
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <SuggestionsReview
                                    suggestions={analysis.suggestions}
                                    originalData={contactData || {}}
                                    onApprove={showApplyButton ? applySuggestions : undefined}
                                    onReject={() => setAnalysis(null)}
                                    showActions={showApplyButton}
                                />
                            </CardContent>
                        </Card>
                    )}

                    {applying && (
                        <Alert className="bg-blue-50 border-blue-200 dark:bg-blue-950 dark:border-blue-800">
                            <Loader2 className="h-4 w-4 animate-spin" />
                            <AlertDescription>Applying suggestions and updating contact...</AlertDescription>
                        </Alert>
                    )}
                </div>
            )}
        </div>
    );
}
