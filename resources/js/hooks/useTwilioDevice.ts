import { useState, useEffect, useCallback, useRef } from 'react';
import { router } from '@inertiajs/react';
import { Device, Call as TwilioCall } from '@twilio/voice-sdk';

// In Inertia v2 the Router class no longer exposes a .page property, and
// SoftphoneProvider sits *outside* the Inertia <App> so usePage() cannot be
// used either.  Read auth data from the data-page attribute that the server
// embeds in the HTML root element, and keep it in sync via navigate events.
function getInitialAuthUser(): any {
  try {
    const el = document.getElementById('app');
    if (el?.dataset.page) {
      return (JSON.parse(el.dataset.page) as any)?.props?.auth?.user ?? null;
    }
  } catch {
    // ignore parse errors
  }
  return null;
}

export type DeviceState = 'offline' | 'registering' | 'registered' | 'error';

export interface UseTwilioDeviceReturn {
  device: Device | null;
  deviceState: DeviceState;
  error: string | null;
  incomingCall: TwilioCall | null;
  makeCall: (phoneNumber: string, params?: Record<string, string>) => Promise<TwilioCall | null>;
  acceptIncomingCall: () => void;
  rejectIncomingCall: () => void;
  disconnect: () => void;
  reinitialize: () => Promise<void>;
}

// Global device instance to persist across component remounts
let globalDevice: Device | null = null;
let globalDeviceState: DeviceState = 'offline';
let deviceInitializationPromise: Promise<Device> | null = null;

export function useTwilioDevice(): UseTwilioDeviceReturn {
  const [device, setDevice] = useState<Device | null>(globalDevice);
  const [deviceState, setDeviceState] = useState<DeviceState>(globalDeviceState);
  const [error, setError] = useState<string | null>(null);
  const [incomingCall, setIncomingCall] = useState<TwilioCall | null>(null);
  const deviceRef = useRef<Device | null>(globalDevice);
  const isInitializing = useRef(false);

  // Auth user — read from DOM on mount, kept in sync via navigate events.
  const [authUser, setAuthUser] = useState<any>(getInitialAuthUser);
  const authUserRef = useRef<any>(authUser);
  authUserRef.current = authUser;

  // Update global state when local state changes
  useEffect(() => {
    globalDevice = device;
    globalDeviceState = deviceState;
  }, [device, deviceState]);

  // Initialize Twilio Device
  const initializeDevice = useCallback(async () => {
    // If already initializing, wait for that to complete
    if (deviceInitializationPromise) {
      try {
        const existingDevice = await deviceInitializationPromise;
        if (existingDevice) {
          setDevice(existingDevice);
          deviceRef.current = existingDevice;
          setDeviceState(globalDeviceState);
          return;
        }
        // Previous init resolved with null (e.g. auth wasn't ready) — retry
        deviceInitializationPromise = null;
      } catch (error) {
        // If previous initialization failed, continue with new initialization
        deviceInitializationPromise = null;
      }
    }

    // If device already exists and is registered, reuse it
    if (globalDevice && globalDeviceState === 'registered') {
      console.log('Reusing existing Twilio Device');
      setDevice(globalDevice);
      deviceRef.current = globalDevice;
      setDeviceState('registered');
      return;
    }

    // Prevent multiple simultaneous initializations
    if (isInitializing.current) {
      console.log('Device initialization already in progress');
      return;
    }

    isInitializing.current = true;

    // Create initialization promise for other components to wait on
    deviceInitializationPromise = (async () => {
      try {
        setDeviceState('registering');
        setError(null);

        // Don't initialize if user is not authenticated.
        // authUserRef is populated from the DOM data-page attribute (initial load)
        // and kept in sync via navigate events below.
        if (!authUserRef.current) {
          console.log('User not authenticated, skipping Twilio device initialization');
          setDeviceState('offline');
          globalDeviceState = 'offline';
          isInitializing.current = false;
          deviceInitializationPromise = null;
          return null as unknown as Device;
        }

        // First check if Twilio is configured
        const statusResponse = await window.axios.get('/api/twilio/status');
        
        if (!statusResponse.data || !statusResponse.data.configured) {
          console.log('Twilio is not configured, skipping device initialization');
          setDeviceState('offline');
          globalDeviceState = 'offline';
          isInitializing.current = false;
          deviceInitializationPromise = null;
          return null as unknown as Device;
        }

        // Fetch access token from backend using axios (with CSRF)
        const response = await window.axios.get('/api/twilio/token');

        if (!response.data || !response.data.token) {
          throw new Error('No token received from server');
        }

        // Create new Twilio Device with audio constraints
        const newDevice = new Device(response.data.token, {
          logLevel: import.meta.env.DEV ? 1 : 0,
          // Add audio constraints to help with browser compatibility
          codecPreferences: ['opus', 'pcmu'],
          // Enable edge locations for better connectivity
          edge: ['ashburn', 'dublin', 'singapore'],
          // Audio constraints for better compatibility
          allowIncomingWhileBusy: false,
        });

        // Set up event listeners
        newDevice.on('registered', () => {
          console.log('Twilio Device registered');
          setDeviceState('registered');
          globalDeviceState = 'registered';
        });

        newDevice.on('error', (error: Error) => {
          console.error('Twilio Device error:', error);
          
          // Provide more helpful error messages
          let errorMessage = error.message;
          if (error.message.includes('31402')) {
            errorMessage = 'Microphone access denied or unavailable. Please check your browser settings and allow microphone access.';
          } else if (error.message.includes('permission')) {
            errorMessage = 'Please allow microphone access in your browser settings.';
          } else if (error.message.includes('31005')) {
            errorMessage = 'Connection error. Please check your internet connection and try again.';
          } else if (error.message.includes('31000')) {
            errorMessage = 'Cannot make call at this time. Please try again later.';
          }
          
          setError(errorMessage);
          setDeviceState('error');
          globalDeviceState = 'error';
        });

        newDevice.on('unregistered', () => {
          console.log('Twilio Device unregistered');
          setDeviceState('offline');
          globalDeviceState = 'offline';
        });

        newDevice.on('incoming', (call: TwilioCall) => {
          console.log('Incoming call received!', call.parameters);
          setIncomingCall(call);
          
          // Handle call rejection or end
          call.on('disconnect', () => {
            setIncomingCall(null);
          });
          
          call.on('cancel', () => {
            setIncomingCall(null);
          });
          
          call.on('reject', () => {
            setIncomingCall(null);
          });
        });

        newDevice.on('tokenWillExpire', async () => {
          console.log('Token will expire, refreshing...');
          try {
            const refreshResponse = await window.axios.post('/api/twilio/token/refresh');

            if (refreshResponse.data && refreshResponse.data.token) {
              newDevice.updateToken(refreshResponse.data.token);
              console.log('Token refreshed successfully');
            }
          } catch (error) {
            console.error('Failed to refresh token:', error);
          }
        });

        // Register the device
        await newDevice.register();

        setDevice(newDevice);
        deviceRef.current = newDevice;
        globalDevice = newDevice;
        globalDeviceState = 'registered';
        isInitializing.current = false;
        
        return newDevice;
      } catch (err) {
        const errorMessage = err instanceof Error ? err.message : 'Failed to initialize device';
        console.error('Device initialization error:', err);
        setError(errorMessage);
        setDeviceState('error');
        globalDeviceState = 'error';
        isInitializing.current = false;
        deviceInitializationPromise = null;
        throw err;
      }
    })();

    try {
      await deviceInitializationPromise;
    } catch (err) {
      const errorMessage = err instanceof Error ? err.message : 'Failed to initialize device';
      console.error('Device initialization error:', err);
      setError(errorMessage);
      setDeviceState('error');
    }
  }, []);

  // Make outbound call
  const makeCall = useCallback(
    async (phoneNumber: string, params: Record<string, string> = {}): Promise<TwilioCall | null> => {
      if (!device || deviceState !== 'registered') {
        const errorMsg = 'Device not ready. Please wait for registration.';
        console.error(errorMsg, { deviceState });
        setError(errorMsg);
        return null;
      }

      try {
        console.log('Connecting call with params:', { phoneNumber, params });
        const callParams = {
          To: phoneNumber,
          ...params,
        };

        const call = await device.connect({ params: callParams });
        console.log('Call connected successfully');
        return call;
      } catch (err) {
        const errorMessage = err instanceof Error ? err.message : 'Failed to make call';
        console.error('Failed to connect call:', err);
        setError(errorMessage);
        return null;
      }
    },
    [device, deviceState]
  );

  // Accept incoming call
  const acceptIncomingCall = useCallback(() => {
    if (incomingCall) {
      console.log('Accepting incoming call');
      incomingCall.accept();
    }
  }, [incomingCall]);

  // Reject incoming call
  const rejectIncomingCall = useCallback(() => {
    if (incomingCall) {
      console.log('Rejecting incoming call');
      incomingCall.reject();
      setIncomingCall(null);
    }
  }, [incomingCall]);

  // Disconnect device
  const disconnect = useCallback(() => {
    if (deviceRef.current) {
      deviceRef.current.disconnectAll();
      deviceRef.current.unregister();
      deviceRef.current.destroy();
      deviceRef.current = null;
      globalDevice = null;
      setDevice(null);
      setDeviceState('offline');
      globalDeviceState = 'offline';
    }
  }, []);

  // Reinitialize device (for restart button)
  const reinitialize = useCallback(async () => {
    console.log('Reinitializing Twilio Device...');
    disconnect();
    deviceInitializationPromise = null;
    await new Promise(resolve => setTimeout(resolve, 500)); // Wait for cleanup
    await initializeDevice();
  }, [disconnect, initializeDevice]);

  // Initialize on mount only if device doesn't exist
  useEffect(() => {
    const tryInit = () => {
      if (!globalDevice || globalDeviceState === 'offline' || globalDeviceState === 'error') {
        initializeDevice();
      } else {
        // Reuse existing device
        setDevice(globalDevice);
        deviceRef.current = globalDevice;
        setDeviceState(globalDeviceState);
      }
    };

    tryInit();

    // Update auth state and re-try after any Inertia navigation.
    // event.detail.page.props contains the full props for the new page.
    const unsubscribe = router.on('navigate', (event: any) => {
      const props = (event?.detail?.page?.props ?? {}) as any;
      const user = props?.auth?.user ?? null;
      setAuthUser(user);
      authUserRef.current = user;

      if (!globalDevice || globalDeviceState === 'offline') {
        tryInit();
      }
    });

    // Don't cleanup on unmount to keep device alive during navigation
    // Cleanup only when user explicitly disconnects or on window unload
    const handleUnload = () => {
      disconnect();
    };

    window.addEventListener('beforeunload', handleUnload);

    return () => {
      unsubscribe();
      window.removeEventListener('beforeunload', handleUnload);
      // Don't disconnect on component unmount - keep device alive
    };
  }, []);

  // Safety net: if authUser arrives after the initial tryInit (e.g. the DOM
  // data-page attribute was unavailable on the very first render), retry now.
  useEffect(() => {
    if (authUser && (!globalDevice || globalDeviceState === 'offline')) {
      // Clear any promise that previously resolved to null (no auth)
      deviceInitializationPromise = null;
      initializeDevice();
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [authUser]);

  return {
    device,
    deviceState,
    error,
    incomingCall,
    makeCall,
    acceptIncomingCall,
    rejectIncomingCall,
    disconnect,
    reinitialize,
  };
}
