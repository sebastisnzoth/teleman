import { createContext, useContext, useState, useEffect, useCallback, useRef, ReactNode } from 'react';
import { useVoiceDevice } from '@/hooks/useVoiceDevice';
import { useCallState } from '@/hooks/useCallState';
import { useUserNumbers } from '@/hooks/useUserNumbers';
import { useCallQueue } from '@/hooks/useCallQueue';
import { normalizeToE164 } from '@/lib/phone-validation';
import { router } from '@inertiajs/react';

interface SoftphoneContextType {
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
  selectedNumber: any;
  userNumbers: any[];

  // Actions
  handleCall: () => Promise<void>;
  handleEndCall: () => void;
  handleAcceptCall: () => void;
  handleRejectCall: () => void;
  handleDigitClick: (digit: string) => void;
  toggleMute: () => void;
  toggleHold: () => void;
  callContact: (phoneNumber: string, contactId: number, contactName?: string, campaignContactId?: number) => void;

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

  // Contact-aware calling
  activeContactId: number | null;
  activeContactName: string | null;

  // Post-call disposition
  showDispositionModal: boolean;
  setShowDispositionModal: (show: boolean) => void;
  lastCallId: string | null;
  onDispositionSavedCallback: (() => void) | null;
  setOnDispositionSavedCallback: (cb: (() => void) | null) => void;

  // Call queue
  queueCount: number;
  queueEntries: { id: number; caller_number: string; position: number; wait_seconds: number; queued_at: string }[];
  dequeueNext: () => Promise<void>;
  isQueueLoading: boolean;
}

const SoftphoneContext = createContext<SoftphoneContextType | undefined>(undefined);

export function SoftphoneProvider({ children }: { children: ReactNode }) {
  // Initialize the voice device and call state
  const {
    deviceState,
    error: deviceError,
    makeCall,
    incomingCall,
    acceptIncomingCall,
    rejectIncomingCall,
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

  const { selectedNumber, numbers: userNumbers } = useUserNumbers();

  // Call queue — polls while user is in a call
  const isInCall = ['dialing', 'ringing', 'connected'].includes(callState);
  const { queueCount, queueEntries, dequeueNext, isLoading: isQueueLoading } = useCallQueue(isInCall);

  // Local state
  const [phoneNumber, setPhoneNumber] = useState<string>('');
  const [enableRecording, setEnableRecording] = useState(true);
  const [callError, setCallError] = useState<string | null>(null);
  const [isWidgetExpanded, setIsWidgetExpanded] = useState(false);
  const [isWidgetVisible, setIsWidgetVisible] = useState(true);

  // Contact-aware calling state
  const [activeContactId, setActiveContactId] = useState<number | null>(null);
  const [activeContactName, setActiveContactName] = useState<string | null>(null);
  const [activeCampaignContactId, setActiveCampaignContactId] = useState<number | null>(null);

  // Post-call disposition state
  const [showDispositionModal, setShowDispositionModal] = useState(false);
  const [lastCallId, setLastCallId] = useState<string | null>(null);
  const [onDispositionSavedCallback, setOnDispositionSavedCallback] = useState<(() => void) | null>(null);
  const dispositionTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Configuration from shared Inertia props (provided by HandleInertiaRequests)
  const [isConfigured, setIsConfigured] = useState(true);
  const [hasAssignedNumbers, setHasAssignedNumbers] = useState(true);

  // Read softphone config from shared Inertia props on mount and navigation
  useEffect(() => {
    const syncConfig = () => {
      const pageProps = (router.page?.props || {}) as any;
      // Check shared softphone props from HandleInertiaRequests
      if (pageProps.softphone) {
        setIsConfigured(pageProps.softphone.isConfigured !== false);
        setHasAssignedNumbers(pageProps.softphone.hasAssignedNumbers !== false);
      }
    };

    syncConfig();

    // Re-sync on every Inertia navigation (props may change)
    const unsubscribe = router.on('navigate', syncConfig);
    return () => unsubscribe();
  }, []);

  // Auto-expand widget on incoming call or when call becomes active
  useEffect(() => {
    if (incomingCall || ['dialing', 'ringing', 'connected'].includes(callState)) {
      setIsWidgetExpanded(true);
    }
  }, [incomingCall, callState]);

  // Show disposition modal when call ends (only for contact-linked calls)
  // Timer is stored in a ref so it survives the ended→idle callState transition
  // (useCallState resets to 'idle' after 1000ms, but we need 1200ms for the modal)
  useEffect(() => {
    if (callState === 'ended' && lastCallId && activeContactId) {
      if (dispositionTimerRef.current) {
        clearTimeout(dispositionTimerRef.current);
      }
      dispositionTimerRef.current = setTimeout(() => {
        setShowDispositionModal(true);
        dispositionTimerRef.current = null;
      }, 1200);
    }
    // When call goes back to idle, clear contact state only if no modal showing and no pending timer
    if (callState === 'idle' && !showDispositionModal && !dispositionTimerRef.current) {
      setActiveContactId(null);
      setActiveContactName(null);
      setLastCallId(null);
    }
  }, [callState, lastCallId, activeContactId, showDispositionModal]);

  // Auto-dequeue: when call ends and there are callers waiting, trigger dequeue
  const prevCallState = useRef(callState);
  useEffect(() => {
    if (prevCallState.current === 'connected' && callState === 'ended' && queueCount > 0) {
      // Small delay to let the call fully tear down
      const timer = setTimeout(() => {
        console.log('Auto-dequeue: connecting next queued caller');
        dequeueNext();
      }, 2000);
      return () => clearTimeout(timer);
    }
    prevCallState.current = callState;
  }, [callState, queueCount, dequeueNext]);

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

    try {
      // Reset any previous errors
      setCallError(null);

      // Use selected number - must have a valid number to make calls
      const fromNumber = selectedNumber?.number;
      if (!fromNumber) {
        setCallError('No phone number selected. Please assign a phone number first.');
        return;
      }

      console.log('Initiating call...', { phoneNumber, fromNumber, enableRecording, source: selectedNumber?.source, contactId: activeContactId });

      // First create the call record in the database
      const response = await window.axios.post('/calls/initiate', {
        to_number: phoneNumber,
        from_number: fromNumber,
        number_source: selectedNumber?.source || 'twilio_direct',
        enable_recording: enableRecording,
        contact_id: activeContactId || undefined,
        campaign_contact_id: activeCampaignContactId || undefined,
      });

      console.log('Call initiate response:', response.data);

      const callData = response.data.call;

      if (!callData || !callData.id) {
        throw new Error('Failed to create call record');
      }

      // Store the call ID for disposition
      setLastCallId(callData.id.toString());

      console.log('Placing call with call_id:', callData.id);

      // Now place the call with the call_id
      const voiceCall = await makeCall(phoneNumber, {
        call_id: callData.id.toString(),
        enableRecording: enableRecording.toString(),
      });

      if (voiceCall) {
        console.log('Call placed, initiating state...');
        initiateCall(voiceCall, phoneNumber, callData.from_number, callData.id);
      } else {
        throw new Error('Failed to establish the call');
      }
    } catch (error: any) {
      console.error('Failed to initiate call:', error);
      const errorMessage = error.response?.data?.error || error.message || 'Failed to initiate call';
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
    // Clear phone number immediately to prepare for next call
    setPhoneNumber('');
  };

  // Click-to-call a specific contact
  const callContact = useCallback((phone: string, contactId: number, contactName?: string, campaignContactId?: number) => {
    setActiveContactId(contactId);
    setActiveContactName(contactName || null);
    setActiveCampaignContactId(campaignContactId || null);
    // Normalize phone to E.164 (default US). Prevents ambiguous numbers like
    // "2083421813" from being parsed as Egyptian (+20) by the phone input library.
    setPhoneNumber(normalizeToE164(phone, 'US'));
    setIsWidgetExpanded(true);
    // The user will click "Call" in the widget to initiate
  }, []);

  // Handle incoming call acceptance
  const handleAcceptCall = async () => {
    if (!incomingCall) return;

    try {
      setCallError(null);

      console.log('Accepting incoming call', incomingCall.id);

      // The application's own call id where the provider carried one,
      // and the provider's id for the call otherwise.
      const incomingCallId = incomingCall.appCallId || incomingCall.id;

      // Set up the call state FIRST for the incoming call
      initiateCall(
        incomingCall,
        incomingCall.fromNumber || 'Unknown',
        incomingCall.toNumber || selectedNumber?.number || '',
        incomingCallId ?? undefined
      );

      // Then accept the incoming call
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
      // Send DTMF if in call
      sendDTMF(digit);
    } else {
      // Add to phone number if not in call
      setPhoneNumber((prev) => (prev || '') + digit);
    }
  };

  const value: SoftphoneContextType = {
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
    userNumbers,

    // Actions
    handleCall,
    handleEndCall,
    handleAcceptCall,
    handleRejectCall,
    handleDigitClick,
    toggleMute,
    toggleHold,
    callContact,

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

    // Contact-aware calling
    activeContactId,
    activeContactName,

    // Post-call disposition
    showDispositionModal,
    setShowDispositionModal: (show: boolean) => {
      setShowDispositionModal(show);
      // Clear contact state when modal is closed
      if (!show) {
        setActiveContactId(null);
        setActiveContactName(null);
        setActiveCampaignContactId(null);
        setLastCallId(null);
      }
    },
    lastCallId,
    onDispositionSavedCallback,
    setOnDispositionSavedCallback,

    // Call queue
    queueCount,
    queueEntries,
    dequeueNext,
    isQueueLoading,
  };

  return (
    <SoftphoneContext.Provider value={value}>
      {children}
    </SoftphoneContext.Provider>
  );
}

export function useSoftphone() {
  const context = useContext(SoftphoneContext);
  if (context === undefined) {
    throw new Error('useSoftphone must be used within a SoftphoneProvider');
  }
  return context;
}
