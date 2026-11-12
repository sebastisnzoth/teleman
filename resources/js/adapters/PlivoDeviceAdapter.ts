/**
 * Plivo Browser SDK adapter.
 *
 * Wraps plivo-browser-sdk behind the provider-agnostic
 * VoiceDeviceAdapter / VoiceCall interfaces.
 *
 * Registration is by access token, never by endpoint credentials:
 *   1. The backend returns a Plivo-signed access token naming the user's
 *      SIP Endpoint. The Endpoint's password stays on the server and
 *      must never reach the browser, so nothing here may go back to
 *      `client.login(username, password)`.
 *   2. The client calls `client.loginWithAccessToken(token)` (available
 *      since SDK 2.2.16; this package is 2.2.21).
 *   3. Calls are made via plivoClient.call(to, extraHeaders).
 *
 * That token expires, and this SDK gives no warning before it does: the
 * only signal is `onLogout("ACCESS_TOKEN_EXPIRED")`, emitted once the
 * device has already dropped off. `signalsTokenExpiry` is therefore
 * false, and the consumer schedules the refresh — see that flag on
 * VoiceDeviceAdapter, and `updateToken()` below for what a refresh
 * costs.
 *
 * `new Plivo(options)` (the package's default export) returns a thin
 * wrapper whose only member is `client: Client` — every SDK method
 * (`login`, `logout`, `call`, `answer`, `reject`, `hangup`, `mute`,
 * `unmute`, `sendDtmf`, `on`, …) lives one level down, on that nested
 * `Client`. `loadPlivoSdk()` below returns the constructor and
 * `initialize()` unwraps `.client` immediately so every other method in
 * this file calls the real `Client` directly, instead of repeating
 * `.client.client` at each call site.
 *
 * Every method call and every `onXxx` event handler this adapter wires
 * up has been audited in one pass, each cross-checked against both
 * `node_modules/plivo-browser-sdk/index.d.ts` (the package's own type
 * declarations) and `dist/plivobrowsersdk.js` (its bundled, un-minified
 * runtime, read directly for real argument counts, shapes and return
 * types — not inferred from a method's JSDoc, which twice already
 * turned out to describe something the runtime doesn't actually do).
 * Method calls (`login`, `logout`, `call`, `answer`, `reject`, `hangup`,
 * `mute`, `unmute`, `sendDtmf`) all match the declared `Client` type
 * exactly in both argument count and shape, confirmed against both
 * sources; `Client` is imported as the real type from
 * 'plivo-browser-sdk/client' (no `any` anywhere in this file) so the
 * compiler — not a comment — holds every call site to it.
 *
 * Every `onXxx` handler's real argument shape was established the same
 * way. Several are not what a reader would assume, and each is written
 * out below with the bundle evidence that settles it, because guessing
 * them wrong is silent — a handler with the wrong parameter shape still
 * runs, it just reads the wrong thing:
 *
 *  - `onIncomingCall`: real payload is FOUR arguments —
 *    `f.emit("onIncomingCall", i, e.extraHeaders, e.getCallInfo("local"), r)`.
 *    The third is a `CallInfo` object (from
 *    'plivo-browser-sdk/managers/callSession') carrying `callUUID:
 *    string` — the incoming call's real id. `X-PH-CallUUID` (this
 *    adapter's first attempt) is never set on an inbound call; `X-PH-`
 *    is this codebase's own convention for headers *we* attach to
 *    *outbound* INVITEs in `makeCall()` below. The handler is typed
 *    against `CallInfo`. The unused 4th argument, `r`, is the caller's
 *    SIP `display_name`; left undeclared since nothing here needs it.
 *  - `onMediaPermission`: real payload is always ONE object, never a
 *    boolean — every one of its four emit sites passes either
 *    `{status:"failure", error: <string>}` or `{status:"success",
 *    stream: <boolean>}` (`stream` is a boolean flag, not a
 *    `MediaStream`). The adapter's first attempt (`(permGranted:
 *    boolean) => { if (!permGranted) … }`) treated that object as a
 *    truthy boolean, so the negation was always false and the
 *    permission-denied branch could never run. Modelled locally as
 *    `MediaPermissionResult` (not exported by the package) and the
 *    handler now branches on `result.status === 'failure'`.
 *  - `onLoginFailed`, `onCallFailed`: both genuinely are `(cause:
 *    string, …)` — every emit site passes a string first argument.
 *    `onCallFailed` also gets a second `CallInfo` argument at both its
 *    emit sites, which this adapter doesn't need and doesn't declare
 *    (a shorter handler is a valid, non-throwing subtype of a longer
 *    emitted argument list — nothing here was wrong).
 *  - `onCallRemoteRinging`, `onCallAnswered`: one `CallInfo` each, and
 *    that argument is load-bearing — it is the ONLY place an outgoing
 *    call's CallUUID becomes available. `createOutgoingSession()` builds
 *    the session with no `callUUID`, and the SDK fills it in from the
 *    SIP `X-Calluuid` response header at exactly these two moments
 *    (`g._currentSession.setCallUUID(e.response.getHeader("X-Calluuid"))`
 *    in both handlers), so `client.getCallUUID()` read straight after
 *    `call()` is null every time. Both handlers pass it to
 *    `PlivoVoiceCall._setCallUUID()`.
 *  - `onIncomingCallCanceled`, `onIncomingCallIgnored`: both emit ONE
 *    `CallInfo`. They are distinct events from `onCallTerminated`, which
 *    does not fire when a caller hangs up before the agent answers — so
 *    an adapter that wires only `onCallTerminated` leaves a ringing call
 *    on screen forever. Both are wired below.
 *  - `onConnectionChange`: ONE `{state, reason}` object, where `state` is
 *    `"connected"` or `"disconnected"` (`ConnectionState` in the bundle).
 *    `reason` matters: a re-login stops the existing phone instance and
 *    emits a disconnect with reason `"Relogin"` before the new
 *    registration connects, so a handler that treats every
 *    disconnect→connect pair as a dropped socket would refresh the token
 *    it just refreshed, forever. See the handler below.
 *  - `onLogin`, `onLogout`, `onCallTerminated`: the adapter's handlers
 *    take no arguments and read nothing from what's emitted, so there is
 *    nothing to get wrong regardless of payload shape. For completeness:
 *    `onLogin` is always zero-argument; `onLogout` is emitted both
 *    zero-argument and with one string reason (e.g.
 *    `"ACCESS_TOKEN_EXPIRED"`) depending on call site; `onCallTerminated`
 *    gets a `{originator, reason}` object plus a `CallInfo`.
 *
 * To re-derive any of this: search
 * `node_modules/plivo-browser-sdk/dist/plivobrowsersdk.js` for
 * `emit("<eventName>"` and read the arguments at the emit site. The
 * bundle is webpack output on a handful of very long lines, so open a
 * window around the match rather than grepping for whole lines.
 *
 * What remains genuinely unverified — because no amount of reading
 * declarations or a bundle answers it, only a real phone call does — is
 * live-call behaviour against an actual Plivo account: that these events
 * fire in the order and at the times this adapter assumes, that the
 * login flow behaves the same against a live endpoint as it does reading
 * the source, and generally that this file works end to end. That is what
 * a live verification should check — not the argument shapes above, which
 * are settled.
 */
import type { VoiceDeviceAdapter, VoiceCall, CallStatus } from '@/types/telephony';
import type { Client, ExtraHeaders } from 'plivo-browser-sdk/client';
import type { CallInfo } from 'plivo-browser-sdk/managers/callSession';

/** The constructor plivo-browser-sdk's default export actually is. */
type PlivoConstructor = typeof import('plivo-browser-sdk').default;

/**
 * `onMediaPermission`'s real payload — not exported by the package, so
 * reconstructed from every `emit("onMediaPermission", …)` call site in
 * `dist/plivobrowsersdk.js` (see the file header, which also says how to
 * re-derive it): always this one object shape, never a boolean.
 */
type MediaPermissionResult = { status: 'success'; stream: boolean } | { status: 'failure'; error: string };

/**
 * `onConnectionChange`'s real payload — the SDK emits a copy of its own
 * `connectionInfo`, which the package does not export a type for. `state`
 * is `ConnectionState`'s "connected" / "disconnected"; `reason` is free
 * text ("registered", "unregistered", "Relogin", …).
 */
type ConnectionInfo = { state?: string; reason?: string };

/**
 * The custom SIP header prefix this application attaches its own values
 * to, and the one Plivo forwards to the answer URL as request
 * parameters. Plivo allows only letters and digits after it — no
 * underscore — and the browser SDK silently DROPS a header whose name
 * breaks that rule (`checkExtraHeaderKey` in the bundle logs "invalid
 * hdr" at debug level and moves on), so a bad name is not an error
 * anywhere: the call simply arrives without the value.
 */
const HEADER_PREFIX = 'X-PH-';

/**
 * App call parameter → the header name Plivo will actually carry.
 *
 * `call_id` is the one that matters: the server reads `X-PH-CallId`
 * (case-insensitively, digits only), and `X-PH-call_id` would be dropped
 * in the browser for its underscore, leaving the answer URL unable to
 * tell which call record it is being asked about.
 */
const HEADER_NAMES: Record<string, string> = {
  call_id: 'CallId',
};

/**
 * Whether Plivo will carry a header of this name at all.
 *
 * Letters and digits only, which is Plivo's documented rule, and at most
 * 19 characters, which is the SDK's — a longer name passes Plivo's rule
 * and is still dropped in the browser without a word, which is the exact
 * silent failure the name mapping above exists to prevent.
 */
function isLegalHeaderName(name: string): boolean {
  return /^[A-Za-z0-9]{1,19}$/.test(name);
}

/**
 * One `X-PH-` header off an inbound INVITE, matched without regard to
 * case — SIP header names are case-insensitive and nothing guarantees
 * the case they were sent in survives the trip.
 */
function readHeader(headers: ExtraHeaders | undefined, name: string): string | null {
  const wanted = `${HEADER_PREFIX}${name}`.toLowerCase();

  for (const [key, value] of Object.entries(headers ?? {})) {
    if (key.toLowerCase() === wanted) {
      return value;
    }
  }

  return null;
}

/** How long to wait for the SDK to report the outcome of a login. */
const LOGIN_TIMEOUT_MS = 15000;

// ─── VoiceCall wrapper ───────────────────────────

class PlivoVoiceCall implements VoiceCall {
  readonly provider = 'plivo' as const;
  status: CallStatus = 'dialing';
  private handlers: Map<string, Set<(...args: unknown[]) => void>> = new Map();
  private _isMuted = false;

  constructor(
    private readonly client: Client,
    private callUUID: string | null,
    private readonly details: { appCallId?: string | null; fromNumber?: string | null; toNumber?: string | null } = {},
  ) {}

  get id(): string | null {
    return this.callUUID;
  }

  get appCallId(): string | null {
    return this.details.appCallId ?? null;
  }

  get fromNumber(): string | null {
    return this.details.fromNumber ?? null;
  }

  get toNumber(): string | null {
    return this.details.toNumber ?? null;
  }

  /**
   * Fill in the call's id once the SDK knows it.
   *
   * An OUTGOING call has no CallUUID at the moment `client.call()`
   * returns: `createOutgoingSession()` builds its CallSession without a
   * `callUUID` option, so `client.getCallUUID()` is null until the SIP
   * response carries an `X-Calluuid` header — which the SDK reads on
   * ringing (`_currentSession.setCallUUID(...)`, then
   * `emit("onCallRemoteRinging", …)`) and again on answer. The adapter
   * calls this from those two handlers. Internal to this file; nothing
   * outside it may move a call's identity.
   */
  _setCallUUID(callUUID: string | null): void {
    if (callUUID) {
      this.callUUID = callUUID;
    }
  }

  accept(): void {
    // Both params are typed as required strings, but the SDK's own JSDoc
    // calls them "(Optional)", and they are — as long as this adapter
    // never turns on `allowMultipleIncomingCalls`, which it does not set
    // anywhere. In the bundled runtime, `answer()` resolves the call
    // through `getCurrentIncomingCall(uuid, client)`, which falls back to
    // `client.lastIncomingCall` for an unknown uuid and only returns null
    // for one when `allowMultipleIncomingCalls` is on; and it validates
    // the second argument through `checkIncomingCallAction(action)`,
    // which only rejects a value when that same option is on. Turning
    // that option on would make both arguments load-bearing.
    this.client.answer(this.callUUID ?? '', '');
  }

  reject(): void {
    // Marked ended here rather than waiting for an event: rejecting a
    // call that was never answered terminates a session the SDK does not
    // consider current, and its onCallTerminated emit is conditional on
    // exactly that — so nothing may arrive to say this call is over.
    // reject() returns false when it declined to act (no such call, or
    // one already answered, which must be hung up instead).
    if (this.client.reject(this.callUUID ?? '')) {
      this.status = 'ended';
    }
  }

  hangup(): void {
    this.client.hangup();
  }

  mute(shouldMute: boolean): void {
    if (shouldMute) {
      this.client.mute();
    } else {
      this.client.unmute();
    }
    this._isMuted = shouldMute;
  }

  isMuted(): boolean {
    return this._isMuted;
  }

  sendDigits(digits: string): void {
    this.client.sendDtmf(digits);
  }

  // ── Event proxy (events are emitted by PlivoDeviceAdapter) ──

  on(event: string, handler: (...args: unknown[]) => void): void {
    if (!this.handlers.has(event)) {
      this.handlers.set(event, new Set());
    }
    this.handlers.get(event)!.add(handler);
  }

  /** Called internally by the adapter to emit to the call wrapper */
  _emit(event: string, ...args: unknown[]): void {
    this.handlers.get(event)?.forEach((fn) => {
      try {
        fn(...args);
      } catch (e) {
        console.error(`[PlivoCall] Error in "${event}":`, e);
      }
    });
  }

  removeAllListeners(): void {
    this.handlers.clear();
  }

  get nativeCall(): Client {
    return this.client;
  }
}

// ─── Device adapter ──────────────────────────────

type EventHandler = (...args: unknown[]) => void;

export class PlivoDeviceAdapter implements VoiceDeviceAdapter {
  readonly provider = 'plivo' as const;

  /**
   * Nothing in this SDK warns that an access token is about to expire —
   * see the file header. The consumer has to run the clock itself.
   */
  readonly signalsTokenExpiry = false;

  private client: Client | null = null;
  private listeners: Map<string, Set<EventHandler>> = new Map();
  private _registered = false;

  /** Settled by the permanent onLogin/onLoginFailed pair — see awaitLogin(). */
  private pendingLogin: { resolve: () => void; reject: (error: Error) => void } | null = null;
  private activeCall: PlivoVoiceCall | null = null;

  /**
   * Last socket state seen from `onConnectionChange`, so a genuine drop
   * and recovery can be told apart from the disconnect a re-login causes
   * on its way to reconnecting.
   */
  private socketConnected = false;

  /** Whether the socket has gone down since it was last up. */
  private socketDropped = false;

  isRegistered(): boolean {
    return this._registered;
  }

  async initialize(token: string): Promise<void> {
    if (!token) {
      throw new Error('A Plivo access token is required');
    }

    if (this.client) {
      this.destroy();
    }

    // Dynamically import the Plivo SDK
    const PlivoBrowserSdk = await this.loadPlivoSdk();

    const plivoOptions = {
      debug: import.meta.env.DEV ? ('DEBUG' as const) : ('OFF' as const),
      permOnClick: true,
      enableTracking: true,
      closeProtection: false,
      maxAverageBitrate: 48000,
    };

    // The constructor returns a thin { client: Client } wrapper — see
    // the file header. Unwrap it once here so every method below calls
    // the real Client directly.
    this.client = new PlivoBrowserSdk(plivoOptions).client;

    // Wire up Plivo events → adapter events
    this.client.on('onLogin', () => {
      this._registered = true;
      this.settleLogin(null);
      this.emit('registered');
    });

    this.client.on('onLoginFailed', (cause: string) => {
      this._registered = false;
      this.settleLogin(new Error(`Plivo login failed: ${cause}`));
      this.emit('error', new Error(`Plivo login failed: ${cause}`));
    });

    this.client.on('onLogout', () => {
      this._registered = false;
      this.emit('unregistered');
    });

    // Incoming call. The SDK actually emits a 4th argument (the caller's
    // display name) that this handler has no use for — see the file
    // header for why it's safe to leave undeclared.
    this.client.on('onIncomingCall', (callerName: string, extraHeaders: ExtraHeaders, callInfo: CallInfo) => {
      const callUUID = callInfo.callUUID;
      // src/dest are the caller and called parties as Plivo reports them;
      // an app call id only exists if whatever dialled this endpoint
      // attached one, so it is usually absent on an inbound call.
      const wrapped = new PlivoVoiceCall(this.client as Client, callUUID, {
        appCallId: readHeader(extraHeaders, HEADER_NAMES.call_id),
        fromNumber: callInfo.src ?? null,
        toNumber: callInfo.dest ?? null,
      });
      wrapped.status = 'ringing';
      this.activeCall = wrapped;
      this.emit('incoming', wrapped);
    });

    // A dropped and recovered WebSocket re-registers with the token it
    // already has, which is no good if that token died while the socket
    // was down, so ask the consumer for a fresh one — the same request
    // the scheduled refresh makes. Only a real drop counts: a re-login
    // disconnects ("Relogin") on its way to connecting again, and
    // treating that as a drop would refresh forever.
    this.client.on('onConnectionChange', (info: ConnectionInfo) => {
      if (info?.state === 'connected') {
        const recovered = this.socketDropped;
        this.socketConnected = true;
        this.socketDropped = false;

        // Not on the first connection of a session — only on one that
        // followed a socket this adapter had already seen go down.
        if (recovered) {
          this.emit('tokenExpiring');
        }

        return;
      }

      const deliberate = String(info?.reason ?? '').toLowerCase() === 'relogin';
      this.socketDropped = this.socketConnected && !deliberate;
      this.socketConnected = false;
    });

    // Outgoing call events → forward to active call.
    // These two are also where an outgoing call's CallUUID first exists:
    // the SDK sets it from the SIP `X-Calluuid` header on ringing, and
    // again on answer, so both hand this adapter a CallInfo carrying it.
    this.client.on('onCallRemoteRinging', (callInfo: CallInfo) => {
      if (this.activeCall) {
        this.activeCall._setCallUUID(callInfo?.callUUID ?? null);
        this.activeCall.status = 'ringing';
        this.activeCall._emit('ringing');
      }
    });

    this.client.on('onCallAnswered', (callInfo: CallInfo) => {
      if (this.activeCall) {
        this.activeCall._setCallUUID(callInfo?.callUUID ?? null);
        this.activeCall.status = 'connected';
        this.activeCall._emit('accepted');
      }
    });

    this.client.on('onCallTerminated', () => {
      if (this.activeCall) {
        this.activeCall.status = 'ended';
        this.activeCall._emit('disconnected');
        this.activeCall = null;
      }
    });

    // A caller who hangs up while the agent's phone is still ringing.
    // The SDK emits this INSTEAD of onCallTerminated (which only fires
    // for a call that was actually up), so without it the softphone
    // keeps showing a ringing call that never clears — useVoiceDevice
    // clears its incoming-call state on 'cancelled'.
    this.client.on('onIncomingCallCanceled', () => {
      this.endActiveCall('cancelled');
    });

    // Same shape for a call this client ignored: no onCallTerminated,
    // so the ringing state has to be cleared from here too.
    this.client.on('onIncomingCallIgnored', () => {
      this.endActiveCall('cancelled');
    });

    this.client.on('onCallFailed', (cause: string) => {
      if (this.activeCall) {
        this.activeCall.status = 'error';
        this.activeCall._emit('error', new Error(`Call failed: ${cause}`));
        this.activeCall = null;
      }
    });

    this.client.on('onMediaPermission', (result: MediaPermissionResult) => {
      if (result.status === 'failure') {
        this.emit('error', new Error(`Microphone permission denied: ${result.error}`));
      }
    });

    const client = this.client;

    await this.awaitLogin(() => client.loginWithAccessToken(token));
  }

  /**
   * Run a login attempt and resolve only when the SDK confirms it.
   *
   * The confirmation comes through the one permanent onLogin/onLoginFailed
   * pair registered above rather than a fresh pair per attempt: the SDK
   * exposes no way to remove a listener, so a pair per attempt would
   * accumulate for the life of the client and fire against promises that
   * settled long ago.
   *
   * The attempt's own `false` return is still checked. It means the token
   * was refused outright — an unparseable one, or one carrying no endpoint
   * username — and only some of those paths also emit onLoginFailed, so
   * without the check the rest would sit here until the timeout.
   */
  private async awaitLogin(attempt: () => boolean | void): Promise<void> {
    await new Promise<void>((resolve, reject) => {
      const timeout = setTimeout(
        () => this.settleLogin(new Error('Plivo login timeout')),
        LOGIN_TIMEOUT_MS,
      );

      this.pendingLogin = {
        resolve: () => {
          clearTimeout(timeout);
          resolve();
        },
        reject: (error: Error) => {
          clearTimeout(timeout);
          reject(error);
        },
      };

      if (attempt() === false) {
        this.settleLogin(new Error('Plivo rejected the access token'));
      }
    });
  }

  private settleLogin(error: Error | null): void {
    const pending = this.pendingLogin;

    if (!pending) {
      return;
    }

    this.pendingLogin = null;

    if (error) {
      pending.reject(error);
    } else {
      pending.resolve();
    }
  }

  destroy(): void {
    if (this.client) {
      try {
        this.client.logout();
      } catch {
        // ignore
      }
      this.client = null;
    }
    this._registered = false;
    this.settleLogin(new Error('Plivo client was destroyed before it registered'));
    this.activeCall = null;
    this.socketConnected = false;
    this.socketDropped = false;
    this.listeners.clear();
  }

  /**
   * Register again with a freshly minted access token.
   *
   * Not the cheap in-place swap Twilio's `updateToken` is. For an
   * access-token client the SDK always re-registers: it stops the
   * existing phone instance, announces a disconnect with reason
   * "Relogin", and brings a new registration up in its place — so the
   * device is briefly unregistered and an inbound call arriving in that
   * window is lost. Worse, the SDK refuses outright while a call is up
   * ("Cannot login when there is an ongoing call", reported as
   * onLoginFailed), which would surface as a device error mid-call.
   * Whoever schedules the refresh must therefore keep it away from
   * active calls; `hasActiveCall()` is here for exactly that.
   *
   * Resolves only once the SDK confirms the new registration, so a caller
   * that records "refreshed" can trust that it was.
   */
  async updateToken(token: string): Promise<void> {
    if (!this.client || !token) return;

    const client = this.client;

    // A `true` return is not proof the new token took: the SDK also returns
    // true, having done nothing, when it is already connecting or
    // re-registering. Trusting it would record a refresh that never
    // happened and leave the device to deregister when the OLD token
    // expires — recoverable only by reloading the page. So wait for the
    // login the new token is supposed to produce.
    await this.awaitLogin(() => client.loginWithAccessToken(token));
  }

  /**
   * Whether a call is currently dialling, ringing or up.
   *
   * Read from the call's own status, not merely from holding a reference
   * to one: a call this adapter never sees end would otherwise hold off
   * a token refresh for good, and the token does not wait.
   */
  hasActiveCall(): boolean {
    const status = this.activeCall?.status;

    return status !== undefined && status !== 'ended' && status !== 'error';
  }

  async makeCall(to: string, params?: Record<string, string>): Promise<VoiceCall> {
    if (!this.client || !this._registered) {
      throw new Error('Plivo client not registered');
    }

    // Plivo carries extra data as custom SIP headers, under names it is
    // willing to forward — see HEADER_NAMES.
    const extraHeaders: ExtraHeaders = {};
    Object.entries(params ?? {}).forEach(([key, value]) => {
      const name = HEADER_NAMES[key] ?? key;

      if (!isLegalHeaderName(name)) {
        console.warn(`[PlivoAdapter] Dropping call parameter "${key}": Plivo header names allow only letters and digits.`);

        return;
      }

      extraHeaders[`${HEADER_PREFIX}${name}`] = value;
    });

    // call() returns a plain success boolean, and the call has no
    // CallUUID yet: the SDK creates the outgoing CallSession with no
    // callUUID and only fills it in from the SIP `X-Calluuid` header when
    // the far end rings, so getCallUUID() here would be null every time.
    // The returned VoiceCall's `id` is therefore null until
    // onCallRemoteRinging/onCallAnswered populate it — read it when you
    // need it, never at the moment of the call.
    if (this.client.call(to, extraHeaders) === false) {
      throw new Error('Plivo could not place the call');
    }

    const wrapped = new PlivoVoiceCall(this.client, null, {
      appCallId: params?.call_id ?? null,
      toNumber: to,
    });
    wrapped.status = 'dialing';
    this.activeCall = wrapped;

    return wrapped;
  }

  // ── Event emitter ──

  on(event: string, handler: EventHandler): void {
    if (!this.listeners.has(event)) {
      this.listeners.set(event, new Set());
    }
    this.listeners.get(event)!.add(handler);
  }

  removeAllListeners(): void {
    this.listeners.clear();
  }

  /** Emit a terminal event on the active call and let go of it. */
  private endActiveCall(event: 'cancelled' | 'disconnected'): void {
    if (!this.activeCall) {
      return;
    }

    this.activeCall.status = 'ended';
    this.activeCall._emit(event);
    this.activeCall = null;
  }

  private emit(event: string, ...args: unknown[]): void {
    this.listeners.get(event)?.forEach((fn) => {
      try {
        fn(...args);
      } catch (e) {
        console.error(`[PlivoAdapter] Error in "${event}":`, e);
      }
    });
  }

  /**
   * Dynamically import the Plivo Browser SDK.
   * Falls back to window.Plivo if UMD is loaded via script tag.
   */
  private async loadPlivoSdk(): Promise<PlivoConstructor> {
    // Check if already loaded globally
    const globalPlivo = (window as { Plivo?: PlivoConstructor }).Plivo;
    if (typeof globalPlivo !== 'undefined') {
      return globalPlivo;
    }

    try {
      const mod = await import('plivo-browser-sdk');
      return mod.default;
    } catch {
      throw new Error('Plivo Browser SDK not found. Install it with: npm install plivo-browser-sdk');
    }
  }
}
