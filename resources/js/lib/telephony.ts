/**
 * Access to the telephony provider lock shared with every authenticated
 * Inertia page by HandleInertiaRequests::share() (the `telephony` prop) —
 * which provider this installation is locked to, and which Twilio-only
 * features are hidden for it.
 *
 * For Inertia pages only: useTelephony() reads the prop via usePage(),
 * which needs an Inertia page context. Two places do not have one — the
 * embeddable widget build (resources/js/widget.tsx), which mounts via
 * createRoot with no Inertia at all, and any provider mounted *outside*
 * Inertia's <App> (SoftphoneProvider in app.tsx is). See
 * useVoiceDevice.ts's resolveProvider() for how those read the same
 * prop without a context.
 */
import { usePage } from '@inertiajs/react';
import type { SharedData } from '@/types';
import type { TelephonyProviderName, TelephonySharedProp } from '@/types/telephony';

// The shape lives in @/types/telephony so SharedData can declare the
// prop without importing from this module (which imports SharedData).
// Re-exported here because this is where callers look for it.
export type { TelephonyProviderName, TelephonySharedProp };

/**
 * Mirrors the fallback HandleInertiaRequests::share() serves when
 * telephonyProps() throws (e.g. mid-install, before any migration has
 * run) — kept in sync with that default by hand, the same way the
 * frontend and backend already agree independently on every other shared
 * prop's shape in this app. Used only as a last resort if `telephony` is
 * ever missing from props at runtime despite the type saying otherwise.
 */
const DEFAULT_TELEPHONY: TelephonySharedProp = {
  provider: 'twilio',
  locked: false,
  hidden: [],
};

/** Read the telephony provider lock from the shared Inertia page props. */
export function useTelephony(): TelephonySharedProp {
  const { telephony } = usePage<SharedData>().props;
  return telephony ?? DEFAULT_TELEPHONY;
}

/**
 * The provider's name as an admin should read it. Mirrors
 * App\Enums\TelephonyProvider::label(), which is the server's answer to
 * the same question (and the one Admin/Telephony/Index gets as a
 * providerLabel prop) - the pages that only carry the shared `telephony`
 * prop have no such prop to read, so the mapping lives here too rather
 * than being spelled out inline on each of them.
 *
 * Admin-facing only. A customer must never be shown a provider's brand
 * name; an admin chose the provider, so naming it to them is accurate.
 */
export function providerLabel(provider: TelephonyProviderName): string {
  const labels: Record<TelephonyProviderName, string> = {
    twilio: 'Twilio',
    plivo: 'Plivo',
  };

  return labels[provider];
}

/**
 * Whether `feature` is hidden for the locked provider. Takes the `hidden`
 * list straight from useTelephony() — App\Enums\TelephonyProvider is the
 * only place that decides what's hidden; this never re-derives it.
 */
export function isHidden(feature: string, hidden: string[]): boolean {
  return hidden.includes(feature);
}
