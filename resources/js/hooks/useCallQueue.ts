import { useState, useEffect, useCallback, useRef } from 'react';

interface QueueEntry {
  id: number;
  caller_number: string;
  position: number;
  wait_seconds: number;
  queued_at: string;
}

interface QueueStatus {
  waiting_count: number;
  queue_name: string;
  entries: QueueEntry[];
}

interface UseCallQueueReturn {
  queueCount: number;
  queueEntries: QueueEntry[];
  queueName: string;
  isLoading: boolean;
  dequeueNext: () => Promise<void>;
  refreshStatus: () => Promise<void>;
}

export function useCallQueue(isInCall: boolean): UseCallQueueReturn {
  const [status, setStatus] = useState<QueueStatus>({
    waiting_count: 0,
    queue_name: '',
    entries: [],
  });
  const [isLoading, setIsLoading] = useState(false);
  const pollInterval = useRef<ReturnType<typeof setInterval> | null>(null);

  const fetchStatus = useCallback(async () => {
    try {
      const response = await window.axios.get('/call-queue/status');
      setStatus(response.data);
    } catch (error) {
      // Silently fail — queue status is non-critical
      console.debug('Failed to fetch queue status:', error);
    }
  }, []);

  // Poll every 5 seconds while in a call
  useEffect(() => {
    if (isInCall) {
      fetchStatus();
      pollInterval.current = setInterval(fetchStatus, 5000);
    } else {
      // One final fetch when call ends to check if anyone is waiting
      fetchStatus();
    }

    return () => {
      if (pollInterval.current) {
        clearInterval(pollInterval.current);
        pollInterval.current = null;
      }
    };
  }, [isInCall, fetchStatus]);

  const dequeueNext = useCallback(async () => {
    setIsLoading(true);
    try {
      await window.axios.post('/call-queue/dequeue');
      // Refresh status after dequeue
      await fetchStatus();
    } catch (error: any) {
      console.warn('Failed to dequeue:', error?.response?.data?.message || error.message);
    } finally {
      setIsLoading(false);
    }
  }, [fetchStatus]);

  return {
    queueCount: status.waiting_count,
    queueEntries: status.entries,
    queueName: status.queue_name,
    isLoading,
    dequeueNext,
    refreshStatus: fetchStatus,
  };
}
