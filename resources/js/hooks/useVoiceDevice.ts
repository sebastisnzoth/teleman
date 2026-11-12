/**
 * useVoiceDevice – the browser softphone's device, for whichever
 * telephony provider this installation is locked to.
 *
 * Which provider that is, is never chosen here and never sent to the
 * server: an installation is locked to one provider and the backend is
 * the authority on which. resolveTelephony() below reads that lock; see
 * its own comment.
 *
 * The device is a module-level singleton on purpose. It survives React
 * remounts and page-to-page navigation — a softphone that deregistered
 * every time an agent opened another page would miss inbound calls — so
 * nothing here tears it down on unmount. Two consequences that are easy
 * to get wrong:
 *
 *  1. A surviving device belongs to whoever registered it. The server
 *     refuses a call whose browser identity does not own it, so a second
 *     person signing in to the same browser tab would place calls that
 *     fail with nothing on screen to explain why. The navigate handler
 *     below watches the signed-in user id for exactly that, and
 *     re-registers when it changes. Impersonation, and leaving it, are
 *     ordinary client-side visits and are caught the same way.
 *  2. Tokens expire. Some SDKs say so in advance and some do not (see
 *     VoiceDeviceAdapter.signalsTokenExpiry), so this hook keeps its own
 *     clock for the ones that do not — otherwise the softphone quietly
 *     deregisters part-way through a shift.
 *
 * UseVoiceDeviceReturn's field names, types and signatures match the
 * Twilio-only hook this replaces field for field; `device` and
 * `incomingCall` hold the provider-agnostic VoiceDeviceAdapter /
 * VoiceCall rather than the Twilio SDK's own Device / Call, since a
 * provider-agnostic hook cannot honestly return one provider's concrete
 * types.
 */
import { useState, useEffect, useCallback, useRef } from 'react';
import { router } from '@inertiajs/react';
import { createAdapter } from '@/adapters';
import type {
  VoiceDeviceAdapter,
  VoiceCall,
  TelephonyProviderName,
  DeviceStatus,
  TelephonySharedProp,
} from '@/types/telephony';

export type UseVoiceDeviceReturn = {
  device: VoiceDeviceAdapter | null;
  deviceState: DeviceStatus;
  error: string | null;
  incomingCall: VoiceCall | null;
  makeCall: (phoneNumber: string, params?: Record<string, string>) => Promise<VoiceCall | null>;
  acceptIncomingCall: () => void;
  rejectIncomingCall: () => void;
  disconnect: () => void;
  reinitialize: () => Promise<void>;
  /**
   * The features the locked provider cannot honour, straight from
   * App\Enums\TelephonyProvider by way of /api/telephony/status. Read it
   * with isHidden() from @/lib/telephony. Surfaced here for the
   * embeddable widget build, which has no Inertia page and so cannot
   * reach the shared `telephony` prop every other surface uses.
   */
  hiddenFeatures: string[];
};

// ─── Global singletons (survive React remounts) ──

let globalAdapter: VoiceDeviceAdapter | null = null;
let globalDeviceState: DeviceStatus = 'offline';
let globalHiddenFeatures: string[] = [];
let adapterInitPromise: Promise<void> | null = null;

/** The user the live adapter registered as, so a change can be spotted. */
let registeredUserId: string | null = null;

let tokenRefreshTimer: ReturnType<typeof setTimeout> | null = null;
let lastTokenRefreshAt = 0;

/** How long before expiry to swap a token out. */
const TOKEN_REFRESH_MARGIN_SECONDS = 120;

/** Assumed token lifetime when the server does not state one. */
const DEFAULT_TOKEN_LIFETIME_SECONDS = 3600;

/** Floor on the scheduled delay, so a short-lived token cannot spin. */
const MIN_TOKEN_REFRESH_DELAY_MS = 30_000;

/** How soon to try again after a refresh could not be done. */
const TOKEN_REFRESH_RETRY_MS = 60_000;

/** The closest together two self-scheduled refreshes may run. */
const MIN_TOKEN_REFRESH_INTERVAL_MS = 60_000;

/**
 * GET one of the telephony endpoints as the signed-in user.
 *
 * window.axios where the page has it: the main app's bootstrap carries
 * the CSRF handling and the session-expiry redirect every other request
 * in this app goes through, and the softphone should behave like the
 * rest of the page. The embeddable widget build has no bootstrap and so
 * no window.axios — resources/js/types/index.d.ts declares the property's
 * *type*, which proves nothing about it existing at runtime — so fetch
 * covers that build. Both endpoints are session-authenticated GETs, so
 * neither path needs a CSRF token.
 */
async function getJson<T>(url: string): Promise<T> {
  if (typeof window.axios?.get === 'function') {
    const response = await window.axios.get(url);

    return response.data as T;
  }

  const response = await fetch(url, {
    credentials: 'same-origin',
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
  });

  if (!response.ok) {
    throw new Error(`Request to ${url} failed with status ${response.status}`);
  }

  return (await response.json()) as T;
}

type IssuedToken = { token: string; expiresIn: number | null };

/**
 * Fetch login material for the softphone.
 *
 * No `provider` parameter, by design. The installation is locked to one
 * provider server-side and this endpoint mints live SIP/WebRTC
 * credentials, so which provider it mints for is decided from the lock,
 * never from something the client asked for.
 */
async function fetchToken(): Promise<IssuedToken> {
  const data = await getJson<{ token?: string; expires_in?: number }>('/api/telephony/token');

  if (!data?.token) throw new Error('No token received from server');

  return {
    token: data.token,
    expiresIn: typeof data.expires_in === 'number' ? data.expires_in : null,
  };
}

/**
 * The shared `telephony` Inertia prop, read from the server-rendered
 * page payload rather than through usePage().
 *
 * usePage() needs an Inertia page context, and the softphone does not
 * have one: SoftphoneProvider wraps <App /> in app.tsx, so it mounts
 * *outside* the context it would need — the same reason the auth user is
 * read off this attribute below. The attribute itself is Inertia's own
 * server-rendered page object and carries the identical prop, so this is
 * the shared prop, just reached from outside. It is absent in the
 * embeddable widget build, which mounts via createRoot with no Inertia
 * at all.
 *
 * This is no longer the primary source of anything. /api/telephony/status
 * answers both questions the device needs — see resolveTelephony() — and
 * this stands in for exactly one field, `provider`, and only when that
 * endpoint did not name one. It is never consulted for `configured`: the
 * prop says which provider is locked, not whether it has credentials.
 */
function sharedTelephonyProp(): TelephonySharedProp | null {
  const telephony = (pageProps() as { telephony?: TelephonySharedProp } | null)?.telephony;

  return telephony?.provider ? telephony : null;
}

/** The server-rendered page props, or null where there is no Inertia page. */
function pageProps(): Record<string, unknown> | null {
  try {
    const page = document.getElementById('app')?.dataset.page;
    if (!page) return null;

    return (JSON.parse(page) as { props?: Record<string, unknown> })?.props ?? null;
  } catch {
    return null;
  }
}

type AuthProps = { auth?: { user?: { id?: number | string } | null } | null };

/**
 * The signed-in user's id from a set of page props: a string, null for
 * nobody, and undefined when those props say nothing either way.
 *
 * The third case is not pedantry. A partial Inertia visit carries only
 * the props it asked for, so "this payload did not mention auth" has to
 * stay distinguishable from "nobody is signed in" — reading the first as
 * the second would tear a working device down mid-session.
 */
function authUserId(props: Record<string, unknown> | null | undefined): string | null | undefined {
  if (!props || !('auth' in props)) return undefined;

  const id = (props as AuthProps).auth?.user?.id;

  return id === undefined || id === null ? null : String(id);
}

/**
 * Which provider this installation is locked to, and whether it can
 * actually register a device.
 *
 * `configured` comes from the status endpoint rather than from the
 * shared prop's `locked`, even though the prop is already on the page
 * and costs no request.
 *
 * `locked` only says an admin has chosen a provider. It does not say the
 * provider has credentials, and the installer locks one before any are
 * entered — so gating on it would have a locked-but-unconfigured
 * installation attempt to register, take a refusal from the token
 * endpoint, and show the user an error where it previously sat quietly
 * offline. `configured` is also what the hook this replaces gated on,
 * and for Twilio the endpoint evaluates the identical expression, so a
 * working installation sees no change at all.
 *
 * The client never *chooses* a provider: the endpoint's answer and the
 * shared prop are both ways of reading the server's lock, the prop
 * standing in only if the endpoint did not name one.
 */
async function resolveTelephony(): Promise<{
  configured: boolean;
  provider: TelephonyProviderName;
  hidden: string[];
}> {
  const shared = sharedTelephonyProp();
  const status = await getJson<{ provider?: string; configured?: boolean; hidden?: string[] }>(
    '/api/telephony/status',
  );

  return {
    configured: !!status?.configured,
    provider: (status?.provider as TelephonyProviderName | undefined) ?? shared?.provider ?? 'twilio',
    // Which controls this installation's provider cannot honour. The
    // embeddable widget build has no Inertia page and so no shared
    // `telephony` prop to read it from — the endpoint is its only source.
    hidden: status?.hidden ?? shared?.hidden ?? [],
  };
}

/**
 * What to show the user for a device error.
 *
 * The codes are the Twilio Voice SDK's, kept verbatim from the
 * Twilio-only hook this replaces so those installations keep reading the
 * wording they always have. They are matched on the message text, so
 * another provider's errors fall through to their own message untouched.
 */
function describeDeviceError(error: unknown): string {
  const message = error instanceof Error ? error.message : String(error);

  if (message.includes('31402')) {
    return 'Microphone access denied or unavailable. Please check your browser settings and allow microphone access.';
  }

  if (message.includes('permission')) {
    return 'Please allow microphone access in your browser settings.';
  }

  if (message.includes('31005')) {
    return 'Connection error. Please check your internet connection and try again.';
  }

  if (message.includes('31000')) {
    return 'Cannot make call at this time. Please try again later.';
  }

  return message;
}

// ─── Token refresh ─────────────────────────────

function clearTokenRefresh(): void {
  if (tokenRefreshTimer) {
    clearTimeout(tokenRefreshTimer);
    tokenRefreshTimer = null;
  }
}

function scheduleTokenRefresh(adapter: VoiceDeviceAdapter, delayMs: number): void {
  clearTokenRefresh();

  tokenRefreshTimer = setTimeout(() => {
    tokenRefreshTimer = null;
    void refreshToken(adapter);
  }, delayMs);
}

/** When to refresh a token that lives for `expiresIn` seconds. */
function refreshDelayMs(expiresIn: number | null): number {
  const lifetime = expiresIn && expiresIn > 0 ? expiresIn : DEFAULT_TOKEN_LIFETIME_SECONDS;

  return Math.max((lifetime - TOKEN_REFRESH_MARGIN_SECONDS) * 1000, MIN_TOKEN_REFRESH_DELAY_MS);
}

/**
 * Swap in a freshly minted token.
 *
 * Called from the adapter's own `tokenExpiring` signal where the SDK
 * raises one, and from this hook's clock where it does not. Only the
 * second case — where nothing but this timer is keeping the registration
 * alive — is held back by the two guards below, so a provider whose SDK
 * manages its own token lifecycle refreshes exactly as immediately as it
 * always has.
 */
async function refreshToken(adapter: VoiceDeviceAdapter): Promise<void> {
  // A timer belonging to an adapter that has since been replaced.
  if (adapter !== globalAdapter) return;

  const selfScheduled = !adapter.signalsTokenExpiry;

  // Re-registering is not free for these: the SDK rebuilds the
  // registration, and refuses outright while a call is up — which would
  // surface as a device error in the middle of that call. Wait for the
  // line to clear instead.
  if (selfScheduled && adapter.hasActiveCall()) {
    scheduleTokenRefresh(adapter, TOKEN_REFRESH_RETRY_MS);

    return;
  }

  // A reconnecting socket asks for a fresh token too, and its own
  // re-registration can announce itself as another reconnect. Refusing to
  // refresh twice in quick succession keeps that from becoming a loop.
  const sinceLast = Date.now() - lastTokenRefreshAt;

  if (selfScheduled && lastTokenRefreshAt > 0 && sinceLast < MIN_TOKEN_REFRESH_INTERVAL_MS) {
    scheduleTokenRefresh(adapter, MIN_TOKEN_REFRESH_INTERVAL_MS - sinceLast);

    return;
  }

  try {
    const issued = await fetchToken();
    await adapter.updateToken(issued.token);
    lastTokenRefreshAt = Date.now();
    console.log('[useVoiceDevice] Token refreshed');

    if (selfScheduled) {
      scheduleTokenRefresh(adapter, refreshDelayMs(issued.expiresIn));
    }
  } catch (e) {
    console.error('[useVoiceDevice] Token refresh failed:', e);

    if (selfScheduled) {
      scheduleTokenRefresh(adapter, TOKEN_REFRESH_RETRY_MS);
    }
  }
}

export function useVoiceDevice(): UseVoiceDeviceReturn {
  const [device, setDevice] = useState<VoiceDeviceAdapter | null>(globalAdapter);
  const [deviceState, setDeviceState] = useState<DeviceStatus>(globalDeviceState);
  const [error, setError] = useState<string | null>(null);
  const [incomingCall, setIncomingCall] = useState<VoiceCall | null>(null);
  const [hiddenFeatures, setHiddenFeatures] = useState<string[]>(
    () => globalHiddenFeatures.length > 0 ? globalHiddenFeatures : (sharedTelephonyProp()?.hidden ?? []),
  );
  const deviceRef = useRef<VoiceDeviceAdapter | null>(globalAdapter);
  const isInitializing = useRef(false);

  // Who is signed in — read from the DOM on mount, kept in sync by the
  // navigate listener below.
  const [userId, setUserId] = useState<string | null>(() => authUserId(pageProps()) ?? null);
  const userIdRef = useRef<string | null>(userId);
  userIdRef.current = userId;

  // Sync globals
  useEffect(() => {
    globalAdapter = device;
    globalDeviceState = deviceState;
  }, [device, deviceState]);

  // ── Initialise ─────────────────────────────────

  const initializeDevice = useCallback(async () => {
    // Wait for in-flight init
    if (adapterInitPromise) {
      try {
        await adapterInitPromise;
        setDevice(globalAdapter);
        deviceRef.current = globalAdapter;
        setDeviceState(globalDeviceState);
        return;
      } catch {
        adapterInitPromise = null;
      }
    }

    // Reuse if already good
    if (globalAdapter && globalDeviceState === 'registered') {
      setDevice(globalAdapter);
      deviceRef.current = globalAdapter;
      setDeviceState('registered');
      return;
    }

    if (isInitializing.current) return;
    isInitializing.current = true;

    let signedOut = false;

    adapterInitPromise = (async () => {
      try {
        setDeviceState('registering');
        setError(null);

        // 1. Nobody signed in, nothing to register. The token endpoint is
        //    session-authenticated, and the widget build — which has no
        //    Inertia page to read a user from — stops here.
        if (!userIdRef.current) {
          console.log('[useVoiceDevice] No authenticated user, skipping init');
          setDeviceState('offline');
          globalDeviceState = 'offline';
          isInitializing.current = false;
          // Deliberately NOT cleared here. This branch runs synchronously,
          // before the assignment that stores this very promise, so clearing
          // it now would be undone a moment later — leaving a settled promise
          // that every later attempt awaits instead of retrying, so signing
          // back in would never register a device. Cleared after the
          // assignment instead.
          signedOut = true;
          return;
        }

        // 2. Read which provider the installation is locked to
        const status = await resolveTelephony();

        // Before the configured check: which controls the provider can
        // honour is the same answer whether or not it has credentials
        // yet, and the widget renders either way.
        globalHiddenFeatures = status.hidden;
        setHiddenFeatures(status.hidden);

        if (!status.configured) {
          console.log('[useVoiceDevice] Telephony not configured, skipping init');
          setDeviceState('offline');
          globalDeviceState = 'offline';
          isInitializing.current = false;
          adapterInitPromise = null;
          return;
        }

        const providerName = status.provider;

        // 3. Get token / credentials
        const issued = await fetchToken();

        // 4. Create adapter
        const newAdapter = createAdapter(providerName);

        // Wire adapter events
        newAdapter.on('registered', () => {
          console.log(`[useVoiceDevice] ${providerName} registered`);
          setDeviceState('registered');
          globalDeviceState = 'registered';
        });

        newAdapter.on('unregistered', () => {
          console.log(`[useVoiceDevice] ${providerName} unregistered`);
          setDeviceState('offline');
          globalDeviceState = 'offline';
        });

        newAdapter.on('error', (err: unknown) => {
          console.error(`[useVoiceDevice] ${providerName} error:`, err);
          setError(describeDeviceError(err));
          setDeviceState('error');
          globalDeviceState = 'error';
        });

        newAdapter.on('incoming', (call: unknown) => {
          const voiceCall = call as VoiceCall;
          console.log(`[useVoiceDevice] Incoming call from ${providerName}`);
          setIncomingCall(voiceCall);

          // Auto-clear when the incoming call ends
          voiceCall.on('disconnected', () => setIncomingCall(null));
          voiceCall.on('cancelled', () => setIncomingCall(null));
          voiceCall.on('rejected', () => setIncomingCall(null));
        });

        newAdapter.on('tokenExpiring', () => {
          console.log('[useVoiceDevice] Token refresh requested');
          void refreshToken(newAdapter);
        });

        // 5. Initialise (register)
        await newAdapter.initialize(issued.token);

        setDevice(newAdapter);
        deviceRef.current = newAdapter;
        globalAdapter = newAdapter;
        globalDeviceState = 'registered';
        registeredUserId = userIdRef.current;
        lastTokenRefreshAt = Date.now();
        isInitializing.current = false;

        // 6. Keep the registration alive for an SDK that will not warn us
        //    before its token dies.
        clearTokenRefresh();

        if (!newAdapter.signalsTokenExpiry) {
          scheduleTokenRefresh(newAdapter, refreshDelayMs(issued.expiresIn));
        }
      } catch (err) {
        const msg = err instanceof Error ? err.message : 'Failed to initialize voice device';
        console.error('[useVoiceDevice] Init error:', err);
        setError(msg);
        setDeviceState('error');
        globalDeviceState = 'error';
        isInitializing.current = false;
        adapterInitPromise = null;
        throw err;
      }
    })();

    // See the sign-out branch above: it cannot clear this itself, because it
    // runs before the assignment that stores the promise.
    if (signedOut) {
      adapterInitPromise = null;
      return;
    }

    try {
      await adapterInitPromise;
    } catch (err) {
      const msg = err instanceof Error ? err.message : 'Init failed';
      setError(msg);
      setDeviceState('error');
    }
  }, []);

  // ── Make call ──────────────────────────────────

  const makeCall = useCallback(
    async (phoneNumber: string, params: Record<string, string> = {}): Promise<VoiceCall | null> => {
      if (!device || deviceState !== 'registered') {
        setError('Device not ready. Please wait for registration.');
        return null;
      }
      try {
        return await device.makeCall(phoneNumber, params);
      } catch (err) {
        const msg = err instanceof Error ? err.message : 'Failed to make call';
        setError(msg);
        return null;
      }
    },
    [device, deviceState],
  );

  // ── Incoming call helpers ──────────────────────

  const acceptIncomingCall = useCallback(() => {
    incomingCall?.accept();
  }, [incomingCall]);

  const rejectIncomingCall = useCallback(() => {
    incomingCall?.reject();
    setIncomingCall(null);
  }, [incomingCall]);

  // ── Disconnect ─────────────────────────────────

  const disconnect = useCallback(() => {
    clearTokenRefresh();
    registeredUserId = null;

    if (deviceRef.current) {
      deviceRef.current.destroy();
      deviceRef.current = null;
      globalAdapter = null;
      setDevice(null);
      setDeviceState('offline');
      globalDeviceState = 'offline';
    }
  }, []);

  // ── Reinitialize ───────────────────────────────

  const reinitialize = useCallback(async () => {
    console.log('[useVoiceDevice] Reinitializing...');
    disconnect();
    adapterInitPromise = null;
    await new Promise((resolve) => setTimeout(resolve, 500));
    await initializeDevice();
  }, [disconnect, initializeDevice]);

  // ── Mount / unmount ────────────────────────────

  useEffect(() => {
    const tryInit = () => {
      if (!globalAdapter || globalDeviceState === 'offline' || globalDeviceState === 'error') {
        initializeDevice();
      } else {
        setDevice(globalAdapter);
        deviceRef.current = globalAdapter;
        setDeviceState(globalDeviceState);
      }
    };

    tryInit();

    // Every Inertia visit carries the props of the page it landed on.
    const unsubscribe = router.on('navigate', (event) => {
      const nextUserId = authUserId(event?.detail?.page?.props);

      if (nextUserId !== undefined) {
        setUserId(nextUserId);
        userIdRef.current = nextUserId;

        // A device outlives navigation, so this is where a sign-out, a
        // sign-in as somebody else, or stepping in or out of
        // impersonation becomes visible. The server refuses a call whose
        // browser identity does not own it, and says nothing to the
        // browser about why, so the registration has to follow the user.
        if (globalAdapter && registeredUserId !== null && nextUserId !== registeredUserId) {
          console.log('[useVoiceDevice] Signed-in user changed, re-registering');
          void reinitialize();
          return;
        }
      }

      if (!globalAdapter || globalDeviceState === 'offline') {
        tryInit();
      }
    });

    const handleUnload = () => disconnect();
    window.addEventListener('beforeunload', handleUnload);

    return () => {
      unsubscribe();
      window.removeEventListener('beforeunload', handleUnload);
      // Don't disconnect on component unmount — keep device alive during navigation
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Safety net: if the user arrives after the first attempt (the page
  // attribute was not readable on the very first render), retry now.
  useEffect(() => {
    if (userId && (!globalAdapter || globalDeviceState === 'offline')) {
      adapterInitPromise = null;
      initializeDevice();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [userId]);

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
    hiddenFeatures,
  };
}
