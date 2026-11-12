import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { AlertTriangle, CheckCircle, XCircle } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { ScrollArea } from '@/components/ui/scroll-area';

interface ValidationIssue {
  phone: string;
  error: string;
}

interface ValidationModalProps {
  open: boolean;
  onClose: () => void;
  validCount: number;
  invalidNumbers: ValidationIssue[];
  onSendValid: () => void;
  onFixNumbers: () => void;
}

export default function ValidationModal({
  open,
  onClose,
  validCount,
  invalidNumbers,
  onSendValid,
  onFixNumbers,
}: ValidationModalProps) {
  const invalidCount = invalidNumbers.length;
  const hasValidNumbers = validCount > 0;

  return (
    <Dialog open={open} onOpenChange={onClose}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <AlertTriangle className="h-5 w-5 text-yellow-600 dark:text-yellow-500" />
            Phone Number Validation
          </DialogTitle>
          <DialogDescription>
            We found some issues with the phone numbers you entered
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          {/* Summary */}
          <div className="grid grid-cols-2 gap-4">
            <div className="rounded-lg border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-950/30 p-4">
              <div className="flex items-center gap-2 text-green-700 dark:text-green-400">
                <CheckCircle className="h-5 w-5" />
                <span className="font-semibold">Valid Numbers</span>
              </div>
              <p className="text-3xl font-bold text-green-900 dark:text-green-300 mt-2">
                {validCount}
              </p>
              <p className="text-sm text-green-600 dark:text-green-500 mt-1">
                Ready to send
              </p>
            </div>

            <div className="rounded-lg border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950/30 p-4">
              <div className="flex items-center gap-2 text-red-700 dark:text-red-400">
                <XCircle className="h-5 w-5" />
                <span className="font-semibold">Invalid Numbers</span>
              </div>
              <p className="text-3xl font-bold text-red-900 dark:text-red-300 mt-2">
                {invalidCount}
              </p>
              <p className="text-sm text-red-600 dark:text-red-500 mt-1">
                Need attention
              </p>
            </div>
          </div>

          {/* Invalid Numbers List */}
          {invalidCount > 0 && (
            <div className="space-y-2">
              <h4 className="font-semibold text-sm text-foreground">Invalid Phone Numbers:</h4>
              <ScrollArea className="h-[200px] rounded-md border border-border bg-muted/50 p-3">
                <div className="space-y-2">
                  {invalidNumbers.map((issue, index) => (
                    <div
                      key={index}
                      className="flex items-start justify-between gap-3 rounded-md bg-background p-3 border border-red-200 dark:border-red-800"
                    >
                      <div className="flex-1">
                        <p className="font-mono text-sm text-foreground font-semibold">
                          {issue.phone}
                        </p>
                        <p className="text-xs text-red-600 dark:text-red-400 mt-1">
                          {issue.error}
                        </p>
                      </div>
                      <Badge variant="destructive" className="shrink-0">
                        Invalid
                      </Badge>
                    </div>
                  ))}
                </div>
              </ScrollArea>
            </div>
          )}

          {/* Validation Rules */}
          <div className="rounded-md bg-blue-50 dark:bg-blue-950/30 border border-blue-200 dark:border-blue-800 p-3">
            <p className="text-sm font-semibold text-blue-900 dark:text-blue-300 mb-2">
              📋 Phone Number Format Requirements:
            </p>
            <ul className="text-xs text-blue-700 dark:text-blue-400 space-y-1 ml-4 list-disc">
              <li>Must start with + (E.164 format)</li>
              <li>Must include country code (e.g., +1 for US, +44 for UK)</li>
              <li>Must contain 10-15 digits after the +</li>
              <li>Can only contain numbers (no spaces, dashes, or letters)</li>
              <li>Example: +12345678900</li>
            </ul>
          </div>
        </div>

        <DialogFooter className="gap-2 sm:gap-0">
          <Button variant="outline" onClick={onFixNumbers}>
            Fix Invalid Numbers
          </Button>
          {hasValidNumbers && (
            <Button onClick={onSendValid} className="bg-green-600 hover:bg-green-700">
              <CheckCircle className="mr-2 h-4 w-4" />
              Send to {validCount} Valid {validCount === 1 ? 'Number' : 'Numbers'}
            </Button>
          )}
          {!hasValidNumbers && (
            <Button onClick={onClose} variant="default">
              Close
            </Button>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
