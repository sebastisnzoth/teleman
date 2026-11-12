import { useState } from 'react';
import { router } from '@inertiajs/react';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import { CheckCircle2, XCircle, AlertTriangle, Loader2, Copy, Check } from 'lucide-react';

interface ValidationResult {
  total: number;
  valid: number;
  invalid: number;
  invalid_contacts: InvalidContact[];
  ai_suggestions: AiSuggestion[];
}

interface InvalidContact {
  id: number;
  name: string;
  phone_number: string;
  error: string;
  company?: string;
  email?: string;
}

interface AiSuggestion {
  contact_id: number;
  contact_name: string;
  original_number: string;
  issue: string;
  suggested_fix: string;
  confidence: 'high' | 'medium' | 'low';
  notes: string;
}

interface Props {
  open: boolean;
  onClose: () => void;
  onValidate: () => void;
}

export function PhoneValidationModal({ open, onClose, onValidate }: Props) {
  const [validating, setValidating] = useState(false);
  const [result, setResult] = useState<ValidationResult | null>(null);
  const [copiedId, setCopiedId] = useState<number | null>(null);

  const handleValidate = async () => {
    setValidating(true);
    setResult(null);

    try {
      const response = await fetch('/contacts/validate-phone-numbers', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
      });

      const data = await response.json();

      if (data.success) {
        setResult(data.data);
        onValidate();
      } else {
        alert(data.message || 'Validation failed');
      }
    } catch (error) {
      console.error('Validation error:', error);
      alert('An error occurred during validation');
    } finally {
      setValidating(false);
    }
  };

  const copyToClipboard = (text: string, contactId: number) => {
    navigator.clipboard.writeText(text);
    setCopiedId(contactId);
    setTimeout(() => setCopiedId(null), 2000);
  };

  const handleEditContact = (contactId: number) => {
    router.get(`/contacts/${contactId}/edit`);
  };

  const getSuggestionForContact = (contactId: number): AiSuggestion | undefined => {
    return result?.ai_suggestions?.find((s) => s.contact_id === contactId);
  };

  const getConfidenceBadge = (confidence: string) => {
    const variants = {
      high: 'default',
      medium: 'secondary',
      low: 'outline',
    } as const;

    return (
      <Badge variant={variants[confidence as keyof typeof variants] || 'outline'} className="text-xs">
        {confidence} confidence
      </Badge>
    );
  };

  return (
    <Dialog open={open} onOpenChange={onClose}>
      <DialogContent className="max-w-4xl max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>Phone Number Validation</DialogTitle>
          <DialogDescription>
            Check all your contacts for invalid phone numbers and get AI-powered fix suggestions
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          {!result && !validating && (
            <div className="text-center py-8">
              <p className="text-muted-foreground mb-4">
                Click the button below to validate all phone numbers in your contact list.
              </p>
              <Button onClick={handleValidate} size="lg">
                Start Validation
              </Button>
            </div>
          )}

          {validating && (
            <div className="text-center py-8">
              <Loader2 className="h-8 w-8 animate-spin mx-auto mb-4" />
              <p className="text-muted-foreground">Validating phone numbers...</p>
            </div>
          )}

          {result && (
            <>
              {/* Summary */}
              <div className="grid grid-cols-3 gap-4">
                <div className="bg-blue-50 dark:bg-blue-950 p-4 rounded-lg">
                  <div className="text-2xl font-bold text-blue-600 dark:text-blue-400">
                    {result.total}
                  </div>
                  <div className="text-sm text-blue-600/70 dark:text-blue-400/70">Total Contacts</div>
                </div>
                <div className="bg-green-50 dark:bg-green-950 p-4 rounded-lg">
                  <div className="flex items-center gap-2">
                    <CheckCircle2 className="h-5 w-5 text-green-600 dark:text-green-400" />
                    <div className="text-2xl font-bold text-green-600 dark:text-green-400">
                      {result.valid}
                    </div>
                  </div>
                  <div className="text-sm text-green-600/70 dark:text-green-400/70">Valid Numbers</div>
                </div>
                <div className="bg-red-50 dark:bg-red-950 p-4 rounded-lg">
                  <div className="flex items-center gap-2">
                    <XCircle className="h-5 w-5 text-red-600 dark:text-red-400" />
                    <div className="text-2xl font-bold text-red-600 dark:text-red-400">
                      {result.invalid}
                    </div>
                  </div>
                  <div className="text-sm text-red-600/70 dark:text-red-400/70">Invalid Numbers</div>
                </div>
              </div>

              {/* Invalid Contacts */}
              {result.invalid > 0 ? (
                <div className="space-y-4">
                  <Alert>
                    <AlertTriangle className="h-4 w-4" />
                    <AlertDescription>
                      Found {result.invalid} contact{result.invalid !== 1 ? 's' : ''} with invalid phone
                      numbers. Review the suggestions below to fix them.
                    </AlertDescription>
                  </Alert>

                  <div className="border rounded-lg overflow-hidden">
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead>Contact</TableHead>
                          <TableHead>Invalid Number</TableHead>
                          <TableHead>Issue</TableHead>
                          <TableHead>Suggested Fix</TableHead>
                          <TableHead className="text-right">Actions</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {result.invalid_contacts.map((contact) => {
                          const suggestion = getSuggestionForContact(contact.id);
                          return (
                            <TableRow key={contact.id}>
                              <TableCell>
                                <div>
                                  <div className="font-medium">{contact.name}</div>
                                  {contact.company && (
                                    <div className="text-xs text-muted-foreground">{contact.company}</div>
                                  )}
                                </div>
                              </TableCell>
                              <TableCell>
                                <code className="text-sm bg-red-50 dark:bg-red-950 px-2 py-1 rounded">
                                  {contact.phone_number}
                                </code>
                              </TableCell>
                              <TableCell>
                                <div className="text-sm text-muted-foreground">{contact.error}</div>
                              </TableCell>
                              <TableCell>
                                {suggestion && (
                                  <div className="space-y-2">
                                    {suggestion.suggested_fix !== 'Manual entry required' ? (
                                      <div className="flex items-center gap-2">
                                        <code className="text-sm bg-green-50 dark:bg-green-950 px-2 py-1 rounded font-medium">
                                          {suggestion.suggested_fix}
                                        </code>
                                        <Button
                                          size="sm"
                                          variant="ghost"
                                          onClick={() =>
                                            copyToClipboard(suggestion.suggested_fix, contact.id)
                                          }
                                        >
                                          {copiedId === contact.id ? (
                                            <Check className="h-4 w-4 text-green-600" />
                                          ) : (
                                            <Copy className="h-4 w-4" />
                                          )}
                                        </Button>
                                      </div>
                                    ) : (
                                      <Badge variant="outline" className="text-xs">
                                        {suggestion.suggested_fix}
                                      </Badge>
                                    )}
                                    {suggestion.confidence && getConfidenceBadge(suggestion.confidence)}
                                    {suggestion.notes && (
                                      <div className="text-xs text-muted-foreground">{suggestion.notes}</div>
                                    )}
                                  </div>
                                )}
                              </TableCell>
                              <TableCell className="text-right">
                                <Button
                                  size="sm"
                                  variant="outline"
                                  onClick={() => handleEditContact(contact.id)}
                                >
                                  Edit
                                </Button>
                              </TableCell>
                            </TableRow>
                          );
                        })}
                      </TableBody>
                    </Table>
                  </div>
                </div>
              ) : (
                <Alert className="bg-green-50 dark:bg-green-950 border-green-200 dark:border-green-800">
                  <CheckCircle2 className="h-4 w-4 text-green-600 dark:text-green-400" />
                  <AlertDescription className="text-green-600 dark:text-green-400">
                    All phone numbers are valid! Your contact list is ready for campaigns.
                  </AlertDescription>
                </Alert>
              )}

              <div className="flex justify-end gap-2">
                <Button variant="outline" onClick={onClose}>
                  Close
                </Button>
                {result.invalid > 0 && (
                  <Button onClick={handleValidate}>
                    Re-validate
                  </Button>
                )}
              </div>
            </>
          )}
        </div>
      </DialogContent>
    </Dialog>
  );
}
