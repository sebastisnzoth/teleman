<?php

declare(strict_types=1);

namespace App\Contracts\Telephony;

/**
 * Provider-agnostic reading of what a provider charges, for pricing_rules.
 *
 * Lives alongside SmsProviderInterface, VerificationProviderInterface,
 * etc. Implementations: TwilioPricingProvider, PlivoPricingProvider.
 *
 * Providers publish their prices in very different shapes — Twilio lists
 * countries a page at a time and prices each one at its own URL, Plivo
 * prices one country per request with no list at all — so each
 * implementation both pages through its own catalogue and normalises what
 * it finds into the one row shape pricing_rules already stores. The
 * paging cursor is opaque: whatever `next_page` one call returns is only
 * ever handed back, unchanged, to the next.
 */
interface PricingProviderInterface
{
    /**
     * Initialize the provider with credentials array.
     *
     * @param  array  $credentials  Provider-specific credentials
     */
    public function initialize(array $credentials): static;

    /**
     * One page of this provider's prices for one service type.
     *
     * Each row is one country: its ISO 3166-1 alpha-2 code, its name, the
     * provider's base cost, and the unit that cost is per — `per_minute`
     * for call, `per_sms` for sms, `per_month` for phone_number. A country
     * the provider could not price is left out of the page rather than
     * failing it.
     *
     * Nothing already fetched is ever thrown away. Where the provider
     * cannot be asked anything more — its account refused, its API
     * unreachable — the page carries whatever it did collect and
     * `next_page` is null, ending the run there, exactly as a failed page
     * has always ended the Twilio path's paging. Such a failure belongs in
     * the log, since a scheduled run's output goes nowhere.
     *
     * An unknown service type is not a page with nothing on it: it throws
     * UnhandledMatchError, before any request is made, as the pricing
     * command always has.
     *
     * @param  string  $serviceType  call, sms or phone_number
     * @param  string|null  $page  null for the first page; otherwise the `next_page` the previous call returned
     * @return array{pricing: array<int, array{country_code: string, country_name: ?string, base_cost: int|float, base_cost_unit: string}>, next_page: ?string}
     *
     * @throws \Exception
     */
    public function fetchPricing(string $serviceType, ?string $page = null): array;

    /**
     * Get the provider name identifier.
     */
    public function getName(): string;
}
