import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Loader2, Upload, AlertCircle, CheckCircle } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { QualityBadge } from './quality-badge';
import axios from 'axios';

interface ContactImportPreview {
    index: number;
    original: any;
    cleaned: any;
    quality_score: number;
    issues: Array<{
        field: string;
        issue: string;
        severity: string;
    }>;
    suggestions: any;
    is_valid: boolean;
}

interface ImportPreviewSummary {
    total: number;
    average_score: number;
    high_quality: number;
    medium_quality: number;
    low_quality: number;
}

interface ContactImportQualityPreviewProps {
    contacts: any[];
    onImportApproved?: (cleanedContacts: any[]) => void;
}

export function ContactImportQualityPreview({
    contacts,
    onImportApproved,
}: ContactImportQualityPreviewProps) {
    const [preview, setPreview] = useState<ContactImportPreview[]>([]);
    const [summary, setSummary] = useState<ImportPreviewSummary | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const analyzeContacts = async () => {
        setLoading(true);
        setError(null);

        try {
            const response = await axios.post('/api/v1/contacts/quality/preview', {
                contacts: contacts,
            });

            setPreview(response.data.results);
            setSummary(response.data.summary);
        } catch (err: any) {
            setError(err.response?.data?.message || 'Failed to analyze contacts');
        } finally {
            setLoading(false);
        }
    };

    const handleApproveImport = () => {
        const cleanedContacts = preview.map((p) => p.cleaned);
        onImportApproved?.(cleanedContacts);
    };

    if (!preview.length && !loading) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle>AI Quality Check</CardTitle>
                    <CardDescription>
                        Analyze {contacts.length} contact{contacts.length !== 1 ? 's' : ''} before importing
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Button onClick={analyzeContacts} className="w-full">
                        <Upload className="h-4 w-4 mr-2" />
                        Analyze Data Quality
                    </Button>
                </CardContent>
            </Card>
        );
    }

    if (loading) {
        return (
            <Card>
                <CardContent className="pt-6">
                    <div className="flex flex-col items-center justify-center space-y-4 py-8">
                        <Loader2 className="h-8 w-8 animate-spin text-primary" />
                        <div className="text-center">
                            <p className="font-medium">Analyzing contacts...</p>
                            <p className="text-sm text-muted-foreground">
                                Checking data quality for {contacts.length} contact{contacts.length !== 1 ? 's' : ''}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        );
    }

    if (error) {
        return (
            <Alert variant="destructive">
                <AlertCircle className="h-4 w-4" />
                <AlertDescription>{error}</AlertDescription>
            </Alert>
        );
    }

    return (
        <div className="space-y-4">
            {summary && (
                <Card>
                    <CardHeader>
                        <CardTitle>Import Quality Summary</CardTitle>
                        <CardDescription>
                            AI analysis of {summary.total} contact{summary.total !== 1 ? 's' : ''}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="space-y-2">
                            <div className="flex items-center justify-between text-sm">
                                <span className="text-muted-foreground">Average Quality Score</span>
                                <span className="font-semibold">{summary.average_score.toFixed(1)}/100</span>
                            </div>
                            <Progress value={summary.average_score} className="h-2" />
                        </div>

                        <div className="grid grid-cols-3 gap-4 pt-2">
                            <div className="text-center p-3 rounded-lg bg-green-500/10">
                                <p className="text-2xl font-bold text-green-600">{summary.high_quality}</p>
                                <p className="text-xs text-muted-foreground">High Quality</p>
                            </div>
                            <div className="text-center p-3 rounded-lg bg-yellow-500/10">
                                <p className="text-2xl font-bold text-yellow-600">{summary.medium_quality}</p>
                                <p className="text-xs text-muted-foreground">Medium Quality</p>
                            </div>
                            <div className="text-center p-3 rounded-lg bg-red-500/10">
                                <p className="text-2xl font-bold text-red-600">{summary.low_quality}</p>
                                <p className="text-xs text-muted-foreground">Low Quality</p>
                            </div>
                        </div>

                        {summary.average_score >= 70 ? (
                            <Alert>
                                <CheckCircle className="h-4 w-4 text-green-600" />
                                <AlertDescription>
                                    Good data quality! Contacts are ready to import.
                                </AlertDescription>
                            </Alert>
                        ) : (
                            <Alert variant="destructive">
                                <AlertCircle className="h-4 w-4" />
                                <AlertDescription>
                                    Data quality needs improvement. Review issues before importing.
                                </AlertDescription>
                            </Alert>
                        )}
                    </CardContent>
                </Card>
            )}

            <Card>
                <CardHeader>
                    <CardTitle>Contact Preview ({preview.length})</CardTitle>
                    <CardDescription>Review AI-cleaned data before importing</CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="space-y-3 max-h-[500px] overflow-y-auto">
                        {preview.slice(0, 10).map((contact, idx) => (
                            <div
                                key={idx}
                                className="p-4 border rounded-lg space-y-3"
                            >
                                <div className="flex items-center justify-between">
                                    <div className="font-medium">
                                        {contact.cleaned.first_name} {contact.cleaned.last_name}
                                    </div>
                                    <QualityBadge score={contact.quality_score} />
                                </div>

                                <div className="grid grid-cols-2 gap-2 text-sm">
                                    <div>
                                        <span className="text-muted-foreground">Phone:</span>{' '}
                                        <span className="font-mono">{contact.cleaned.phone_number}</span>
                                    </div>
                                    <div>
                                        <span className="text-muted-foreground">Email:</span>{' '}
                                        <span className="font-mono">{contact.cleaned.email}</span>
                                    </div>
                                </div>

                                {contact.issues.length > 0 && (
                                    <div className="text-xs text-muted-foreground">
                                        {contact.issues.length} issue{contact.issues.length !== 1 ? 's' : ''} found
                                        {Object.keys(contact.suggestions).length > 0 && 
                                            ` • ${Object.keys(contact.suggestions).length} suggestion${Object.keys(contact.suggestions).length !== 1 ? 's' : ''} applied`
                                        }
                                    </div>
                                )}
                            </div>
                        ))}

                        {preview.length > 10 && (
                            <p className="text-sm text-muted-foreground text-center py-2">
                                Showing 10 of {preview.length} contacts
                            </p>
                        )}
                    </div>
                </CardContent>
            </Card>

            <div className="flex gap-3">
                <Button onClick={handleApproveImport} className="flex-1">
                    <CheckCircle className="h-4 w-4 mr-2" />
                    Import {preview.length} Contact{preview.length !== 1 ? 's' : ''}
                </Button>
                <Button variant="outline" onClick={() => setPreview([])} className="flex-1">
                    Cancel
                </Button>
            </div>
        </div>
    );
}
