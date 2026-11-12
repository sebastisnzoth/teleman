import { useState, useEffect, useCallback, useRef } from 'react';
import { router } from '@inertiajs/react';

export interface UserNumber {
  id: number;
  number: string;
  formatted_number: string;
  friendly_name: string;
  capabilities: {
    voice: boolean;
    sms: boolean;
    mms: boolean;
  };
  source?: 'twilio_direct' | 'sip_trunk';
}

export interface UseUserNumbersReturn {
  numbers: UserNumber[];
  selectedNumber: UserNumber | null;
  selectNumber: (number: UserNumber) => void;
  loading: boolean;
  error: string | null;
  refresh: () => Promise<void>;
}

export function useUserNumbers(): UseUserNumbersReturn {
  const [numbers, setNumbers] = useState<UserNumber[]>([]);
  const [selectedNumber, setSelectedNumber] = useState<UserNumber | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const fetchNumbers = useCallback(async () => {
    try {
      setLoading(true);
      setError(null);

      // The /softphone/api/my-numbers route has auth middleware,
      // so the server handles unauthenticated requests.
      const response = await window.axios.get('/softphone/api/my-numbers');

      if (response.data && response.data.numbers) {
        setNumbers(response.data.numbers);

        const saved = localStorage.getItem('selected_phone_number');
        const savedNumber = saved ? response.data.numbers.find((n: UserNumber) => n.number === saved) : null;

        if (savedNumber) {
          setSelectedNumber(savedNumber);
        } else if (response.data.numbers.length > 0) {
          const firstNumber = response.data.numbers[0];
          setSelectedNumber(firstNumber);
          localStorage.setItem('selected_phone_number', firstNumber.number);
        } else {
          setSelectedNumber(null);
          localStorage.removeItem('selected_phone_number');
        }
      }
    } catch (err) {
      console.error('Failed to fetch user numbers:', err);
      setError('Failed to load your phone numbers');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchNumbers();

    // Re-fetch on Inertia page navigation
    const unsubscribe = router.on('navigate', () => {
      fetchNumbers();
    });

    return () => unsubscribe();
  }, [fetchNumbers]);

  const selectNumber = (number: UserNumber) => {
    setSelectedNumber(number);
    localStorage.setItem('selected_phone_number', number.number);
  };

  const refresh = async () => {
    await fetchNumbers();
  };

  return {
    numbers,
    selectedNumber,
    selectNumber,
    loading,
    error,
    refresh,
  };
}
