/**
 * Twilio Voice SDK adapter.
 *
 * Wraps @twilio/voice-sdk Device + Call objects behind the
 * provider-agnostic VoiceDeviceAdapter / VoiceCall interfaces.
 */
import { Device, Call } from '@twilio/voice-sdk';
import type { VoiceDeviceAdapter, VoiceCall, CallStatus } from '@/types/telephony';

// ─── VoiceCall wrapper ───────────────────────────

class TwilioVoiceCall implements VoiceCall {
  readonly provider = 'twilio' as const;
  status: CallStatus = 'dialing';

  constructor(private readonly call: Call) {}

  get id(): string | null {
    // Twilio exposes parameters.CallSid once the call is accepted
    return this.parameter('CallSid');
  }

  get appCallId(): string | null {
    return this.parameter('call_id');
  }

  get fromNumber(): string | null {
    return this.parameter('From');
  }

  get toNumber(): string | null {
    return this.parameter('To');
  }

  /**
   * One value out of the Twilio Call's own parameters.
   *
   * `parameters` is populated for an inbound call (From, To, CallSid and
   * whatever the TwiML passed alongside them), which is the only case any
   * caller reads these through.
   */
  private parameter(name: string): string | null {
    return (this.call.parameters as Record<string, string>)?.[name] ?? null;
  }

  accept(): void {
    this.call.accept();
  }

  reject(): void {
    this.call.reject();
  }

  hangup(): void {
    this.call.disconnect();
  }

  mute(shouldMute: boolean): void {
    this.call.mute(shouldMute);
  }

  isMuted(): boolean {
    return this.call.isMuted();
  }

  sendDigits(digits: string): void {
    this.call.sendDigits(digits);
  }

  // ── Events: translate Twilio events → VoiceCall events ──

  on(event: string, handler: (...args: unknown[]) => void): void {
    const map: Record<string, string> = {
      ringing: 'ringing',
      accepted: 'accept',
      disconnected: 'disconnect',
      cancelled: 'cancel',
      rejected: 'reject',
      error: 'error',
    };

    const twilioEvent = map[event] ?? event;
    this.call.on(twilioEvent, handler);
  }

  removeAllListeners(): void {
    this.call.removeAllListeners();
  }

  get nativeCall(): Call {
    return this.call;
  }
}

// ─── Device adapter ──────────────────────────────

type EventHandler = (...args: unknown[]) => void;

export class TwilioDeviceAdapter implements VoiceDeviceAdapter {
  readonly provider = 'twilio' as const;

  /**
   * The Voice SDK raises `tokenWillExpire` ahead of expiry, which
   * initialize() below forwards as `tokenExpiring`, so nothing else has
   * to keep a clock for it.
   */
  readonly signalsTokenExpiry = true;

  private device: Device | null = null;
  private listeners: Map<string, Set<EventHandler>> = new Map();

  isRegistered(): boolean {
    return this.device?.state === Device.State.Registered;
  }

  async initialize(token: string): Promise<void> {
    // Clean up previous device if any
    if (this.device) {
      this.destroy();
    }

    this.device = new Device(token, {
      logLevel: import.meta.env.DEV ? 1 : 0,
      codecPreferences: [Call.Codec.Opus, Call.Codec.PCMU],
      edge: ['ashburn', 'dublin', 'singapore'],
      allowIncomingWhileBusy: false,
    });

    // Wire up native events → adapter events
    this.device.on('registered', () => this.emit('registered'));
    this.device.on('unregistered', () => this.emit('unregistered'));
    this.device.on('error', (err: Error) => this.emit('error', err));

    this.device.on('tokenWillExpire', () => this.emit('tokenExpiring'));

    this.device.on('incoming', (nativeCall: Call) => {
      const wrapped = new TwilioVoiceCall(nativeCall);
      wrapped.status = 'ringing';
      this.emit('incoming', wrapped);
    });

    // Register the device and wait for it
    await this.device.register();
  }

  destroy(): void {
    if (this.device) {
      try {
        this.device.disconnectAll();
        this.device.unregister();
        this.device.destroy();
      } catch {
        // ignore errors during cleanup
      }
      this.device = null;
    }
    this.listeners.clear();
  }

  async updateToken(token: string): Promise<void> {
    this.device?.updateToken(token);
  }

  hasActiveCall(): boolean {
    return (this.device?.calls?.length ?? 0) > 0;
  }

  async makeCall(to: string, params?: Record<string, string>): Promise<VoiceCall> {
    if (!this.device) {
      throw new Error('Twilio device not initialised');
    }

    const nativeCall = await this.device.connect({
      params: { To: to, ...params },
    });

    return new TwilioVoiceCall(nativeCall);
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
    this.device?.removeAllListeners();
  }

  private emit(event: string, ...args: unknown[]): void {
    this.listeners.get(event)?.forEach((fn) => {
      try {
        fn(...args);
      } catch (e) {
        console.error(`[TwilioAdapter] Error in "${event}" handler:`, e);
      }
    });
  }
}
