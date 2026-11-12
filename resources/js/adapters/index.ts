/**
 * Adapter barrel – factory function that returns the correct
 * VoiceDeviceAdapter implementation for a given provider name.
 */
import type { TelephonyProviderName, VoiceDeviceAdapter } from '@/types/telephony';
import { TwilioDeviceAdapter } from './TwilioDeviceAdapter';
import { PlivoDeviceAdapter } from './PlivoDeviceAdapter';

/**
 * Create a VoiceDeviceAdapter for the specified provider.
 */
export function createAdapter(provider: TelephonyProviderName): VoiceDeviceAdapter {
  switch (provider) {
    case 'twilio':
      return new TwilioDeviceAdapter();
    case 'plivo':
      return new PlivoDeviceAdapter();
    default:
      throw new Error(`Unknown telephony provider: ${provider}`);
  }
}

export { TwilioDeviceAdapter } from './TwilioDeviceAdapter';
export { PlivoDeviceAdapter } from './PlivoDeviceAdapter';
