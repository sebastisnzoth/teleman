import { useTranslation } from 'react-i18next';
import { Users, PhoneForwarded } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

interface QueueEntry {
  id: number;
  caller_number: string;
  position: number;
  wait_seconds: number;
  queued_at: string;
}

interface QueueIndicatorProps {
  queueCount: number;
  queueEntries: QueueEntry[];
  onDequeue: () => void;
  isLoading: boolean;
  isInCall: boolean;
}

function formatWaitTime(seconds: number): string {
  if (seconds < 60) return `${seconds}s`;
  const minutes = Math.floor(seconds / 60);
  const secs = seconds % 60;
  return `${minutes}m ${secs}s`;
}

export function QueueIndicator({ queueCount, queueEntries, onDequeue, isLoading, isInCall }: QueueIndicatorProps) {
  const { t } = useTranslation();

  if (queueCount === 0) return null;

  return (
    <div className="border-t px-3 py-2 space-y-2">
      {/* Queue badge */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <div className="relative">
            <Users className="h-4 w-4 text-orange-500" />
            <span className="absolute -top-1 -right-1 flex h-3 w-3">
              <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-orange-400 opacity-75" />
              <span className="relative inline-flex rounded-full h-3 w-3 bg-orange-500" />
            </span>
          </div>
          <span className="text-xs font-medium text-orange-600 dark:text-orange-400">
            {t('softphone.queue.callers_waiting', { count: queueCount })}
          </span>
        </div>

        {!isInCall && (
          <Button
            size="sm"
            variant="outline"
            onClick={onDequeue}
            disabled={isLoading}
            className="h-6 text-xs gap-1"
          >
            <PhoneForwarded className="h-3 w-3" />
            {t('softphone.queue.dequeue')}
          </Button>
        )}
      </div>

      {/* Caller list */}
      {queueEntries.length > 0 && (
        <div className="space-y-1 max-h-24 overflow-y-auto">
          {queueEntries.map((entry) => (
            <div
              key={entry.id}
              className="flex items-center justify-between text-xs text-muted-foreground px-1"
            >
              <span className="font-mono">{entry.caller_number}</span>
              <span>{formatWaitTime(entry.wait_seconds)}</span>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
