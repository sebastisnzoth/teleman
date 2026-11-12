import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { 
    Info, 
    Copy, 
    Eye, 
    EyeOff, 
    AlertCircle, 
    CheckCircle2,
    Sparkles 
} from 'lucide-react';
import { useState, useMemo } from 'react';

interface MessageInputWithVariablesProps {
    message: string;
    onChange: (value: string) => void;
    error?: string;
    campaignContacts?: Array<{
        first_name?: string;
        last_name?: string;
        email?: string;
        company?: string;
        phone_number?: string;
        variables?: Record<string, string>;
    }>;
}

export function MessageInputWithVariables({
    message,
    onChange,
    error,
    campaignContacts = [],
}: MessageInputWithVariablesProps) {
    const [showPreview, setShowPreview] = useState(false);

    // Standard variables always available
    const standardVariables = useMemo(() => [
        { name: 'first_name', description: "Contact's first name", example: 'John' },
        { name: 'last_name', description: "Contact's last name", example: 'Doe' },
        { name: 'email', description: "Contact's email", example: 'john@example.com' },
        { name: 'company', description: "Contact's company", example: 'Acme Corp' },
        { name: 'phone_number', description: "Contact's phone", example: '+1234567890' },
    ], []);

    // Extract custom variables from uploaded contacts
    const customVariables = useMemo(() => {
        const vars = new Map<string, { count: number; examples: string[] }>();

        campaignContacts.forEach(contact => {
            const contactVars = contact.variables || {};
            Object.entries(contactVars).forEach(([key, value]) => {
                if (!vars.has(key)) {
                    vars.set(key, { count: 0, examples: [] });
                }
                const varData = vars.get(key)!;
                varData.count++;
                if (varData.examples.length < 3 && value) {
                    varData.examples.push(String(value));
                }
            });
        });

        return Array.from(vars.entries()).map(([name, data]) => ({
            name,
            count: data.count,
            coverage: campaignContacts.length > 0 
                ? Math.round((data.count / campaignContacts.length) * 100)
                : 0,
            examples: data.examples,
        }));
    }, [campaignContacts]);

    // Find variables used in message
    const usedVariables = useMemo(() => {
        const matches = message.match(/\{\{(\w+)\}\}/g);
        if (!matches) return [];
        return matches.map(match => match.replace(/\{\{|\}\}/g, ''));
    }, [message]);

    // Find undefined variables (used but not available)
    const undefinedVariables = useMemo(() => {
        const allAvailable = new Set([
            ...standardVariables.map(v => v.name),
            ...customVariables.map(v => v.name),
        ]);
        return usedVariables.filter(v => !allAvailable.has(v));
    }, [usedVariables, customVariables, standardVariables]);

    // Generate preview with sample data
    const previewMessage = useMemo(() => {
        if (!showPreview || campaignContacts.length === 0) return null;

        const sample = campaignContacts[0];
        let preview = message;

        // Replace standard variables
        preview = preview.replace(/\{\{first_name\}\}/g, sample.first_name || '[No first name]');
        preview = preview.replace(/\{\{last_name\}\}/g, sample.last_name || '[No last name]');
        preview = preview.replace(/\{\{email\}\}/g, sample.email || '[No email]');
        preview = preview.replace(/\{\{company\}\}/g, sample.company || '[No company]');
        preview = preview.replace(/\{\{phone_number\}\}/g, sample.phone_number || '[No phone]');

        // Replace custom variables
        if (sample.variables) {
            Object.entries(sample.variables).forEach(([key, value]) => {
                const regex = new RegExp(`\\{\\{${key}\\}\\}`, 'g');
                preview = preview.replace(regex, String(value));
            });
        }

        // Highlight undefined variables
        preview = preview.replace(/\{\{(\w+)\}\}/g, '[UNDEFINED: $1]');

        return preview;
    }, [message, showPreview, campaignContacts]);

    const insertVariable = (varName: string) => {
        const variable = `{{${varName}}}`;
        const textarea = document.querySelector('textarea[name="message"]') as HTMLTextAreaElement;
        
        if (textarea) {
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const newMessage = message.substring(0, start) + variable + message.substring(end);
            onChange(newMessage);
            
            // Set cursor position after inserted variable
            setTimeout(() => {
                textarea.focus();
                textarea.setSelectionRange(start + variable.length, start + variable.length);
            }, 0);
        } else {
            onChange(message + variable);
        }
    };

    return (
        <div className="grid gap-6 lg:grid-cols-3">
            {/* Main Message Input */}
            <div className="lg:col-span-2">
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Message</CardTitle>
                                <CardDescription>
                                    Write your message using variables like {'{{first_name}}'} for personalization
                                </CardDescription>
                            </div>
                            {campaignContacts.length > 0 && (
                                <Button
                                    type="button"
                                    variant={showPreview ? 'default' : 'outline'}
                                    size="sm"
                                    onClick={() => setShowPreview(!showPreview)}
                                >
                                    {showPreview ? (
                                        <>
                                            <EyeOff className="mr-2 h-4 w-4" />
                                            Hide Preview
                                        </>
                                    ) : (
                                        <>
                                            <Eye className="mr-2 h-4 w-4" />
                                            Preview
                                        </>
                                    )}
                                </Button>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <Textarea
                            name="message"
                            value={message}
                            onChange={(e) => onChange(e.target.value)}
                            placeholder="Hello {{first_name}}, this is a call from our company..."
                            rows={8}
                            className="font-mono text-sm"
                        />
                        {error && <p className="text-sm text-red-600 mt-1">{error}</p>}

                        {/* Preview Section */}
                        {showPreview && previewMessage && (
                            <Alert>
                                <Sparkles className="h-4 w-4" />
                                <AlertDescription>
                                    <div className="font-semibold mb-2">Preview with sample contact:</div>
                                    <div className="text-sm whitespace-pre-wrap bg-slate-50 p-3 rounded border">
                                        {previewMessage}
                                    </div>
                                </AlertDescription>
                            </Alert>
                        )}

                        {/* Variable Usage Stats */}
                        {usedVariables.length > 0 && (
                            <div className="flex items-center gap-2 flex-wrap">
                                <span className="text-sm text-muted-foreground">Variables used:</span>
                                {usedVariables.map((varName, idx) => (
                                    <Badge 
                                        key={idx} 
                                        variant={undefinedVariables.includes(varName) ? 'destructive' : 'secondary'}
                                    >
                                        {varName}
                                    </Badge>
                                ))}
                            </div>
                        )}

                        {/* Undefined Variables Warning */}
                        {undefinedVariables.length > 0 && (
                            <Alert variant="destructive">
                                <AlertCircle className="h-4 w-4" />
                                <AlertDescription>
                                    <div className="font-semibold">Undefined variables detected:</div>
                                    <div className="text-sm mt-1">
                                        {undefinedVariables.join(', ')} - These variables won't be replaced. 
                                        Upload contacts with these fields or remove them from your message.
                                    </div>
                                </AlertDescription>
                            </Alert>
                        )}

                        {/* Character Count */}
                        <div className="flex justify-between text-xs text-muted-foreground">
                            <span>{message.length} characters</span>
                            {message.length > 500 && (
                                <span className="text-amber-600">
                                    Long messages may be truncated or split into multiple calls
                                </span>
                            )}
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Variable Helper Panel */}
            <div className="lg:col-span-1">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-sm">Available Variables</CardTitle>
                        <CardDescription className="text-xs">
                            Click to insert into message
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {/* Standard Variables */}
                        <div>
                            <h4 className="text-xs font-semibold text-muted-foreground mb-2 flex items-center gap-2">
                                <CheckCircle2 className="h-3 w-3" />
                                Standard Fields
                            </h4>
                            <div className="space-y-1">
                                {standardVariables.map(variable => (
                                    <Button
                                        key={variable.name}
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="w-full justify-start text-xs h-auto py-2"
                                        onClick={() => insertVariable(variable.name)}
                                    >
                                        <div className="flex-1 text-left">
                                            <div className="font-mono text-primary">
                                                {'{{' + variable.name + '}}'}
                                            </div>
                                            <div className="text-muted-foreground">
                                                {variable.description}
                                            </div>
                                        </div>
                                        <Copy className="h-3 w-3 opacity-50" />
                                    </Button>
                                ))}
                            </div>
                        </div>

                        {/* Custom Variables from Contacts */}
                        {customVariables.length > 0 && (
                            <div>
                                <h4 className="text-xs font-semibold text-muted-foreground mb-2 flex items-center gap-2">
                                    <Sparkles className="h-3 w-3" />
                                    Custom Variables
                                    <Badge variant="secondary" className="text-xs">
                                        {customVariables.length}
                                    </Badge>
                                </h4>
                                <div className="space-y-1">
                                    {customVariables.map(variable => (
                                        <Button
                                            key={variable.name}
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="w-full justify-start text-xs h-auto py-2"
                                            onClick={() => insertVariable(variable.name)}
                                        >
                                            <div className="flex-1 text-left">
                                                <div className="font-mono text-primary">
                                                    {'{{' + variable.name + '}}'}
                                                </div>
                                                <div className="text-muted-foreground flex items-center gap-2">
                                                    <span>{variable.coverage}% coverage</span>
                                                    {variable.examples[0] && (
                                                        <span className="text-xs">e.g. "{variable.examples[0]}"</span>
                                                    )}
                                                </div>
                                            </div>
                                            <Copy className="h-3 w-3 opacity-50" />
                                        </Button>
                                    ))}
                                </div>
                            </div>
                        )}

                        {/* Upload Reminder */}
                        {customVariables.length === 0 && campaignContacts.length === 0 && (
                            <Alert>
                                <Info className="h-4 w-4" />
                                <AlertDescription className="text-xs">
                                    Upload contacts with CSV to unlock custom variables. 
                                    Any column beyond the standard fields becomes a variable.
                                </AlertDescription>
                            </Alert>
                        )}

                        {/* CSV Format Help */}
                        <div className="pt-4 border-t">
                            <h4 className="text-xs font-semibold mb-2">CSV Format</h4>
                            <div className="text-xs text-muted-foreground space-y-1">
                                <p>Standard columns:</p>
                                <code className="text-xs bg-slate-100 px-1 py-0.5 rounded block">
                                    phone_number, first_name, last_name, email, company
                                </code>
                                <p className="mt-2">Any other column becomes a custom variable!</p>
                                <code className="text-xs bg-slate-100 px-1 py-0.5 rounded block">
                                    store_name, discount, expiry_date
                                </code>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
