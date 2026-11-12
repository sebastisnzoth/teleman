import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { CheckCircle, XCircle, AlertTriangle } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { ScrollArea } from '@/components/ui/scroll-area';

interface SendResultsModalProps {
  open: boolean;
  onClose: () => void;
  successCount: number;
  skippedNumbers: Array<{ phone: string; reason: string }>;
}

export default function SendResultsModal({
  open,
  onClose,
  successCount,
  skippedNumbers,
}: SendResultsModalProps) {
  const skippedCount = skippedNumbers.length;
  const hasSkipped = skippedCount > 0;

  return (
    <Dialog open={open} onOpenChange={onClose}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            {hasSkipped ? (
              <AlertTriangle className="h-5 w-5 text-yellow-600 dark:text-yellow-500" />
            ) : (
              <CheckCircle className="h-5 w-5 text-green-600 dark:text-green-500" />
            )}
            SMS Send Results
          </DialogTitle>
          <DialogDescription>
            {hasSkipped
              ? 'Messages queued successfully, but some numbers were skipped'
              : 'All messages have been queued successfully'}
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          {/* Summary */}
          <div className="grid grid-cols-2 gap-4">
            <div className="rounded-lg border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-950/30 p-4">
              <div className="flex items-center gap-2 text-green-700 dark:text-green-400">
                <CheckCircle className="h-5 w-5" />
                <span className="font-semibold">Queued</span>
              </div>
              <p className="text-3xl font-bold text-green-900 dark:text-green-300 mt-2">
                {successCount}
              </p>
              <p className="text-sm text-green-600 dark:text-green-500 mt-1">
                Messages will be sent shortly
              </p>
            </div>

            {hasSkipped && (
              <div className="rounded-lg border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950/30 p-4">
                <div className="flex items-center gap-2 text-red-700 dark:text-red-400">
                  <XCircle className="h-5 w-5" />
                  <span className="font-semibold">Skipped</span>
                </div>
                <p className="text-3xl font-bold text-red-900 dark:text-red-300 mt-2">
                  {skippedCount}
                </p>
                <p className="text-sm text-red-600 dark:text-red-500 mt-1">
                  Invalid numbers
                </p>
              </div>
            )}
          </div>

          {/* Skipped Numbers List */}
          {hasSkipped && (
            <div className="space-y-2">
              <h4 className="font-semibold text-sm text-foreground">Skipped Numbers:</h4>
              <ScrollArea className="h-[200px] rounded-md border border-border bg-muted/50 p-3">
                <div className="space-y-2">
                  {skippedNumbers.map((item, index) => (
                    <div
                      key={index}
                      className="flex items-start justify-between gap-3 rounded-md bg-background p-3 border border-red-200 dark:border-red-800"
                    >
                      <div className="flex-1">
                        <p className="font-mono text-sm text-foreground font-semibold">
                          {item.phone}
                        </p>
                        <p className="text-xs text-red-600 dark:text-red-400 mt-1">
                          {item.reason}
                        </p>
                      </div>
                      <Badge variant="destructive" className="shrink-0">
                        Skipped
                      </Badge>
                    </div>
                  ))}
                </div>
              </ScrollArea>
            </div>
          )}

          {/* Success Message */}
          {!hasSkipped && (
            <div className="rounded-md bg-green-50 dark:bg-green-950/30 border border-green-200 dark:border-green-800 p-4 text-center">
              <CheckCircle className="h-12 w-12 text-green-600 dark:text-green-400 mx-auto mb-2" />
              <p className="text-green-900 dark:text-green-300 font-semibold">
                All messages queued successfully!
              </p>
              <p className="text-sm text-green-700 dark:text-green-500 mt-1">
                Your SMS messages will be sent within the next few minutes
              </p>
            </div>
          )}
        </div>

        <div className="flex justify-end">
          <Button onClick={onClose}>Close</Button>
        </div>
      </DialogContent>
    </Dialog>
  );
}
