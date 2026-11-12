/**
 * Provider-agnostic telephony types for the softphone.
 *
 * These abstract away the differences between the Twilio Voice SDK, the
 * Plivo Browser SDK, and any future provider so the voice-device hook and
 * the softphone UI only have to speak one language.
 *
 * An installation is locked to exactly ONE telephony provider, chosen
 * once at install: there is no per-call, per-campaign or per-user
 * provider choice, no "active providers" list, and no method anywhere
 * that takes a provider override. Switching provider is a destructive
 * admin reset, never a toggle. Nothing in this file should ever grow a
 * type that implies otherwise.
 */

// ─── Provider Identity ───────────────────────────

export type TelephonyProviderName = 'twilio' | 'plivo';

/**
 * The `telephony` prop every authenticated Inertia page carries, shared
 * by App\Http\Middleware\HandleInertiaRequests::share(). This is the
 * frontend's authority on which provider is in force; nothing on the
 * client picks one.
 */
export interface TelephonySharedProp {
  /** Which provider (twilio/plivo) this installation is locked to. */
  provider: TelephonyProviderName;
  /** Whether the installation has actually been locked to `provider` yet. */
  locked: boolean;
  /** Feature keys hidden from the UI because `provider` doesn't support them. */
  hidden: string[];
}

// ─── Device Lifecycle ────────────────────────────

export type DeviceStatus = 'offline' | 'registering' | 'registered' | 'error';

// ─── Call Abstraction ────────────────────────────

export type CallStatus = 'idle' | 'dialing' | 'ringing' | 'connected' | 'ended' | 'error';

/**
 * Provider-agnostic handle to an active call. Wraps the native Twilio
 * Call / Plivo call object and exposes a uniform interface.
 */
export interface VoiceCall {
  /** Provider-specific call ID (Twilio CallSid / Plivo CallUUID) */
  readonly id: string | null;
  /**
   * This application's own Call record id, when the provider carried it
   * on the call — Twilio hands back the `call_id` parameter the
   * softphone sent, Plivo the `X-PH-CallId` header.
   *
   * Null whenever the provider carried no such value, which includes
   * every call this application did not itself originate. Read it
   * alongside `id`, never instead of it: a caller that needs *some*
   * identifier wants `appCallId ?? id`.
   */
  readonly appCallId: string | null;
  /**
   * The calling and called numbers as the provider reported them, for a
   * call the softphone did not place itself (an inbound one, where they
   * are the only place those numbers exist on the client).
   *
   * These exist because the softphone contexts need them and a Twilio
   * `Call.parameters` object has no equivalent on any other provider.
   * Either can be null: not every provider populates both on every call.
   */
  readonly fromNumber: string | null;
  readonly toNumber: string | null;
  /** Which provider is handling this call */
  readonly provider: TelephonyProviderName;
  /** Current status as it changes via events */
  status: CallStatus;

  // ── Control ──
  accept(): void;
  reject(): void;
  hangup(): void;
  mute(shouldMute: boolean): void;
  isMuted(): boolean;
  sendDigits(digits: string): void;

  // ── Events ──
  on(event: string, handler: (...args: unknown[]) => void): void;

  /** Remove all event listeners (cleanup) */
  removeAllListeners(): void;

  /** Access to the underlying native SDK call object (escape hatch) */
  readonly nativeCall: unknown;
}

// ─── Device Adapter Interface ────────────────────

/**
 * Every provider must implement this interface so the hooks can
 * initialise / destroy / makeCall / acceptCall in a uniform way.
 */
export interface VoiceDeviceAdapter {
  readonly provider: TelephonyProviderName;

  /**
   * Whether this provider's SDK raises a warning of its own before the
   * token it registered with expires — emitting the adapter's
   * `tokenExpiring` event early enough to swap in a fresh one.
   *
   * False means the SDK says nothing until the token has already died
   * and the device has silently deregistered, so the consumer must
   * schedule its own refresh from the token's stated lifetime. Consumers
   * branch on this rather than on `provider`: it is a statement about
   * the SDK, not about the vendor.
   */
  readonly signalsTokenExpiry: boolean;

  /**
   * Create the native device/client and register it with the platform.
   * Resolves once the device is ready to make/receive calls.
   */
  initialize(token: string, options?: Record<string, unknown>): Promise<void>;

  /** Gracefully shut down the device and clean up resources. */
  destroy(): void;

  /** Whether the device is currently registered and ready. */
  isRegistered(): boolean;

  /** Update the auth token without re-initialising the device. */
  updateToken(token: string): Promise<void>;

  /**
   * Whether a call is currently dialling, ringing or connected.
   *
   * A token refresh is not free on every provider — one of them tears the
   * registration down and rebuilds it, and refuses to do even that during
   * a call — so anything refreshing on a clock has to be able to ask.
   */
  hasActiveCall(): boolean;

  /**
   * Initiate an outgoing call.
   * Returns a VoiceCall wrapper around the native call object.
   */
  makeCall(to: string, params?: Record<string, string>): Promise<VoiceCall>;

  // ── Events the adapter must emit ──

  on(event: string, handler: (...args: unknown[]) => void): void;

  /** Remove all event listeners. */
  removeAllListeners(): void;
}
