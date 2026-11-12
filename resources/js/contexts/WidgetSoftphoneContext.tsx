import { createContext, useContext, useState, useEffect, ReactNode } from 'react';
import { useVoiceDevice } from '@/hooks/useVoiceDevice';
import { useCallState } from '@/hooks/useCallState';

interface PhoneNumber {
  id: number;
  number: string;
  formatted_number: string;
  friendly_name: string;
  capabilities: any;
  source?: 'twilio_direct' | 'sip_trunk';
}

interface WidgetSoftphoneContextType {
  // Device state
  deviceState: 'offline' | 'registering' | 'registered' | 'error';
  deviceError: string | null;
  
  // Call state
  callState: 'idle' | 'dialing' | 'ringing' | 'connected' | 'ended' | 'error';
  callMetadata: any;
  activeCall: any;
  duration: number;
  formattedDuration: string;
  
  // Phone number
  phoneNumber: string;
  setPhoneNumber: (number: string) => void;
  
  // Recording
  enableRecording: boolean;
  setEnableRecording: (enabled: boolean) => void;
  
  // Incoming call
  incomingCall: any;
  
  // User numbers
  selectedNumber: PhoneNumber | null;
  setSelectedNumber: (number: PhoneNumber | null) => void;
  userNumbers: PhoneNumber[];
  loadingNumbers: boolean;
  
  // Actions
  handleCall: () => Promise<void>;
  handleEndCall: () => void;
  handleAcceptCall: () => void;
  handleRejectCall: () => void;
  handleDigitClick: (digit: string) => void;
  toggleMute: () => void;
  toggleHold: () => void;
  
  // Widget UI state
  isWidgetExpanded: boolean;
  setIsWidgetExpanded: (expanded: boolean) => void;
  isWidgetVisible: boolean;
  setIsWidgetVisible: (visible: boolean) => void;
  
  // Error state
  callError: string | null;
  setCallError: (error: string | null) => void;
  
  // Configuration
  isConfigured: boolean;
  hasAssignedNumbers: boolean;
  /**
   * The features this installation's provider cannot honour, as
   * App\Enums\TelephonyProvider names them. Read with isHidden() from
   * @/lib/telephony. Every other surface takes this from the shared
   * `telephony` Inertia prop; this build has no Inertia page, so it comes
   * from /api/telephony/status by way of useVoiceDevice instead.
   */
  hiddenFeatures: string[];
  
  // Widget specific
  apiKey: string | null;
}

const WidgetSoftphoneContext = createContext<WidgetSoftphoneContextType | undefined>(undefined);

interface WidgetSoftphoneProviderProps {
  children: ReactNode;
  apiKey?: string;
}

export function WidgetSoftphoneProvider({ children, apiKey }: WidgetSoftphoneProviderProps) {
  // Initialize the voice device and call state
  const {
    deviceState,
    error: deviceError,
    makeCall,
    incomingCall,
    acceptIncomingCall,
    rejectIncomingCall,
    hiddenFeatures,
  } = useVoiceDevice();

  const {
    callState,
    callMetadata,
    activeCall,
    duration,
    formattedDuration,
    initiateCall,
    endCall,
    toggleMute,
    toggleHold,
    sendDTMF,
  } = useCallState();

  // Storage keys for persistence
  const STORAGE_PREFIX = 'widget_softphone_';
  const STORAGE_KEYS = {
    PHONE_NUMBER: `${STORAGE_PREFIX}phone_number`,
    SELECTED_NUMBER: `${STORAGE_PREFIX}selected_number`,
    WIDGET_EXPANDED: `${STORAGE_PREFIX}expanded`,
    WIDGET_VISIBLE: `${STORAGE_PREFIX}visible`,
    CALL_STATE: `${STORAGE_PREFIX}call_state`,
    ENABLE_RECORDING: `${STORAGE_PREFIX}enable_recording`,
  };

  // Helper functions for localStorage
  const getStoredValue = <T,>(key: string, defaultValue: T): T => {
    try {
      const item = localStorage.getItem(key);
      return item ? JSON.parse(item) : defaultValue;
    } catch (error) {
      console.error('Error reading from localStorage:', error);
      return defaultValue;
    }
  };

  const setStoredValue = <T,>(key: string, value: T) => {
    try {
      localStorage.setItem(key, JSON.stringify(value));
    } catch (error) {
      console.error('Error writing to localStorage:', error);
    }
  };

  // Local state with persistence
  const [phoneNumber, setPhoneNumber] = useState<string>(() => 
    getStoredValue(STORAGE_KEYS.PHONE_NUMBER, '')
  );
  const [enableRecording, setEnableRecording] = useState(() => 
    getStoredValue(STORAGE_KEYS.ENABLE_RECORDING, true)
  );
  const [callError, setCallError] = useState<string | null>(null);
  const [isWidgetExpanded, setIsWidgetExpanded] = useState(() => 
    getStoredValue(STORAGE_KEYS.WIDGET_EXPANDED, false)
  );
  const [isWidgetVisible, setIsWidgetVisible] = useState(() => 
    getStoredValue(STORAGE_KEYS.WIDGET_VISIBLE, true)
  );
  
  // Phone numbers state
  const [userNumbers, setUserNumbers] = useState<PhoneNumber[]>([]);
  const [selectedNumber, setSelectedNumber] = useState<PhoneNumber | null>(() => 
    getStoredValue(STORAGE_KEYS.SELECTED_NUMBER, null)
  );
  const [loadingNumbers, setLoadingNumbers] = useState(false);

  const [isConfigured, setIsConfigured] = useState(true);
  const [hasAssignedNumbers, setHasAssignedNumbers] = useState(true);

  // Persist state changes to localStorage
  useEffect(() => {
    setStoredValue(STORAGE_KEYS.PHONE_NUMBER, phoneNumber);
  }, [phoneNumber]);

  useEffect(() => {
    setStoredValue(STORAGE_KEYS.SELECTED_NUMBER, selectedNumber);
  }, [selectedNumber]);

  useEffect(() => {
    setStoredValue(STORAGE_KEYS.WIDGET_EXPANDED, isWidgetExpanded);
  }, [isWidgetExpanded]);

  useEffect(() => {
    setStoredValue(STORAGE_KEYS.WIDGET_VISIBLE, isWidgetVisible);
  }, [isWidgetVisible]);

  useEffect(() => {
    setStoredValue(STORAGE_KEYS.ENABLE_RECORDING, enableRecording);
  }, [enableRecording]);

  // Persist call state
  useEffect(() => {
    if (callState !== 'idle') {
      setStoredValue(STORAGE_KEYS.CALL_STATE, {
        callState,
        callMetadata,
        duration,
      });
    } else {
      // Clear call state when idle
      localStorage.removeItem(STORAGE_KEYS.CALL_STATE);
    }
  }, [callState, callMetadata, duration]);

  // Fetch phone numbers on mount if apiKey is provided
  useEffect(() => {
    if (apiKey) {
      fetchPhoneNumbers();
    }
  }, [apiKey]);

  const fetchPhoneNumbers = async () => {
    if (!apiKey) return;

    setLoadingNumbers(true);
    try {
      const response = await fetch(`${window.location.origin}/api/widget/phone-numbers`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          api_key: apiKey,
        }),
      });

      if (!response.ok) {
        throw new Error('Failed to fetch phone numbers');
      }

      const data = await response.json();
      const numbers = data.phone_numbers || [];
      
      setUserNumbers(numbers);
      setHasAssignedNumbers(numbers.length > 0);
      
      // Restore selected number from storage or auto-select first
      const storedNumber = getStoredValue<PhoneNumber | null>(STORAGE_KEYS.SELECTED_NUMBER, null);
      if (storedNumber && numbers.find(n => n.id === storedNumber.id)) {
        // Restore previously selected number if it still exists
        const matchedNumber = numbers.find(n => n.id === storedNumber.id);
        setSelectedNumber(matchedNumber || null);
      } else if (numbers.length > 0 && !selectedNumber) {
        // Auto-select first number if no stored selection
        setSelectedNumber(numbers[0]);
      }
    } catch (error) {
      console.error('Failed to fetch phone numbers:', error);
      setHasAssignedNumbers(false);
    } finally {
      setLoadingNumbers(false);
    }
  };

  // Auto-expand widget on incoming call or when call becomes active
  useEffect(() => {
    if (incomingCall || ['dialing', 'ringing', 'connected'].includes(callState)) {
      setIsWidgetExpanded(true);
    }
  }, [incomingCall, callState]);

  // Handle outbound call
  const handleCall = async () => {
    // Prevent duplicate calls
    if (callState !== 'idle') {
      console.warn('Cannot make call: already in call state:', callState);
      return;
    }

    if (!phoneNumber || phoneNumber.toString().replace(/\D/g, '').length < 10) {
      setCallError('Please enter a valid phone number (at least 10 digits)');
      return;
    }

    if (!selectedNumber) {
      setCallError('Please select a phone number to call from');
      return;
    }

    try {
      // Reset any previous errors
      setCallError(null);

      const fromNumber = selectedNumber.number;

      console.log('Initiating call...', { phoneNumber, fromNumber, enableRecording });

      // Create call record via widget API
      const response = await fetch(`${window.location.origin}/api/widget/call/initiate`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          api_key: apiKey,
          to_number: phoneNumber,
          from_number: fromNumber,
          enable_recording: enableRecording,
        }),
      });

      if (!response.ok) {
        throw new Error('Failed to create call record');
      }

      const data = await response.json();
      const callData = data.call;

      if (!callData || !callData.id) {
        throw new Error('Failed to create call record');
      }

      console.log('Placing call with call_id:', callData.id);

      // Now place the call with the call_id
      const voiceCall = await makeCall(phoneNumber, {
        call_id: callData.id.toString(),
        enableRecording: enableRecording.toString(),
      });

      if (voiceCall) {
        console.log('Call placed, initiating state...');
        initiateCall(voiceCall, phoneNumber, fromNumber, callData.id);
      } else {
        throw new Error('Failed to establish the call');
      }
    } catch (error: any) {
      console.error('Failed to initiate call:', error);
      const errorMessage = error.message || 'Failed to initiate call';
      setCallError(errorMessage);

      // Reset call state on error to allow retry
      setTimeout(() => setCallError(null), 5000);
    }
  };

  // Handle end call
  const handleEndCall = () => {
    console.log('Ending call...');
    endCall();
    setCallError(null);
    setPhoneNumber('');
  };

  // Handle incoming call acceptance
  const handleAcceptCall = async () => {
    if (!incomingCall) return;

    try {
      setCallError(null);

      console.log('Accepting incoming call', incomingCall.id);

      // The application's own call id where the provider carried one,
      // and the provider's id for the call otherwise.
      const incomingCallId = incomingCall.appCallId || incomingCall.id;

      initiateCall(
        incomingCall,
        incomingCall.fromNumber || 'Unknown',
        incomingCall.toNumber || selectedNumber?.number || '',
        incomingCallId ?? undefined
      );

      acceptIncomingCall();
    } catch (error) {
      console.error('Failed to accept incoming call:', error);
      setCallError('Failed to accept call');
    }
  };

  // Handle incoming call rejection
  const handleRejectCall = () => {
    if (!incomingCall) return;

    try {
      rejectIncomingCall();
    } catch (error) {
      console.error('Failed to reject incoming call:', error);
    }
  };

  // Handle digit click (DTMF or dialing)
  const handleDigitClick = (digit: string) => {
    if (callState === 'connected') {
      sendDTMF(digit);
    } else {
      setPhoneNumber((prev) => (prev || '') + digit);
    }
  };

  const value: WidgetSoftphoneContextType = {
    // Device state
    deviceState,
    deviceError,

    // Call state
    callState,
    callMetadata,
    activeCall,
    duration,
    formattedDuration,

    // Phone number
    phoneNumber,
    setPhoneNumber,

    // Recording
    enableRecording,
    setEnableRecording,

    // Incoming call
    incomingCall,

    // User numbers
    selectedNumber,
    setSelectedNumber,
    userNumbers,
    loadingNumbers,

    // Actions
    handleCall,
    handleEndCall,
    handleAcceptCall,
    handleRejectCall,
    handleDigitClick,
    toggleMute,
    toggleHold,

    // Widget UI state
    isWidgetExpanded,
    setIsWidgetExpanded,
    isWidgetVisible,
    setIsWidgetVisible,

    // Error state
    callError,
    setCallError,

    // Configuration
    isConfigured,
    hasAssignedNumbers,
    hiddenFeatures,
    
    // Widget specific
    apiKey: apiKey || null,
  };

  return (
    <WidgetSoftphoneContext.Provider value={value}>
      {children}
    </WidgetSoftphoneContext.Provider>
  );
}

export function useWidgetSoftphone() {
  const context = useContext(WidgetSoftphoneContext);
  if (context === undefined) {
    throw new Error('useWidgetSoftphone must be used within a WidgetSoftphoneProvider');
  }
  return context;
}
