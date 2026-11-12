import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { ArrowRight, Check, X } from 'lucide-react';
import { Alert, AlertDescription } from '@/components/ui/alert';

interface Suggestions {
    [key: string]: string;
}

interface SuggestionsReviewProps {
    suggestions: Suggestions;
    originalData: any;
    onApprove?: (approvedFields: string[]) => void;
    onReject?: () => void;
    showActions?: boolean;
}

export function SuggestionsReview({
    suggestions,
    originalData,
    onApprove,
    onReject,
    showActions = true,
}: SuggestionsReviewProps) {
    const [selectedFields, setSelectedFields] = useState<Set<string>>(
        new Set(Object.keys(suggestions))
    );

    const suggestionEntries = Object.entries(suggestions).filter(
        ([field, value]) => value !== 'N/A' && value !== originalData[field]
    );

    if (suggestionEntries.length === 0) {
        return (
            <Alert>
                <AlertDescription>No suggestions available.</AlertDescription>
            </Alert>
        );
    }

    const toggleField = (field: string) => {
        const newSelected = new Set(selectedFields);
        if (newSelected.has(field)) {
            newSelected.delete(field);
        } else {
            newSelected.add(field);
        }
        setSelectedFields(newSelected);
    };

    const handleApprove = () => {
        onApprove?.(Array.from(selectedFields));
    };

    return (
        <div className="space-y-4">
            <div className="space-y-1">
                <h4 className="text-sm font-semibold">AI Suggestions ({suggestionEntries.length})</h4>
                <p className="text-sm text-muted-foreground">
                    Review and select which suggestions to apply
                </p>
            </div>

            <div className="space-y-2">
                {suggestionEntries.map(([field, suggestedValue]) => {
                    const originalValue = originalData[field] || 'N/A';
                    const isSelected = selectedFields.has(field);

                    return (
                        <Card
                            key={field}
                            className={`cursor-pointer transition-colors ${
                                isSelected ? 'border-primary bg-primary/5' : ''
                            }`}
                            onClick={() => toggleField(field)}
                        >
                            <CardHeader className="pb-3">
                                <div className="flex items-center justify-between">
                                    <CardTitle className="text-sm capitalize">
                                        {field.replace(/_/g, ' ')}
                                    </CardTitle>
                                    <Badge variant={isSelected ? 'default' : 'outline'}>
                                        {isSelected ? (
                                            <>
                                                <Check className="h-3 w-3 mr-1" />
                                                Selected
                                            </>
                                        ) : (
                                            'Not Selected'
                                        )}
                                    </Badge>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div className="flex items-center gap-3 text-sm">
                                    <div className="flex-1">
                                        <p className="text-muted-foreground mb-1">Original:</p>
                                        <code className="px-2 py-1 rounded bg-muted">
                                            {originalValue}
                                        </code>
                                    </div>
                                    <ArrowRight className="h-4 w-4 text-muted-foreground flex-shrink-0" />
                                    <div className="flex-1">
                                        <p className="text-muted-foreground mb-1">Suggested:</p>
                                        <code className="px-2 py-1 rounded bg-primary/10 text-primary">
                                            {suggestedValue}
                                        </code>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    );
                })}
            </div>

            {showActions && (
                <div className="flex items-center gap-3 pt-2">
                    <Button
                        onClick={handleApprove}
                        disabled={selectedFields.size === 0}
                        className="flex-1"
                    >
                        <Check className="h-4 w-4 mr-2" />
                        Apply {selectedFields.size} Suggestion{selectedFields.size !== 1 ? 's' : ''}
                    </Button>
                    <Button
                        variant="outline"
                        onClick={onReject}
                        className="flex-1"
                    >
                        <X className="h-4 w-4 mr-2" />
                        Cancel
                    </Button>
                </div>
            )}
        </div>
    );
}
