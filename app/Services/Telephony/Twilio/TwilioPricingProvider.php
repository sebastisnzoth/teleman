<?php

declare(strict_types=1);

namespace App\Services\Telephony\Twilio;

use App\Contracts\Telephony\PricingProviderInterface;
use App\Services\TwilioPricingService;
use Exception;

/**
 * Twilio pricing: TwilioPricingService, unchanged, behind the
 * provider-agnostic interface.
 *
 * Every request, page and price this reads is the service's, exactly as
 * the pricing command read them before it became provider-aware — which
 * is the point: PricingProviderTest pins that command's Twilio behaviour
 * and none of it may move. This class only adapts the service to the
 * interface. Twilio's page cursor is its `next_page_url`, handed on as
 * `next_page` untouched.
 *
 * The match below deliberately has no default arm, as the command's own
 * did: an unknown service type has always failed with UnhandledMatchError
 * before any request, rather than quietly fetching nothing (the service's
 * own fetchPricing() would do the latter).
 */
final class TwilioPricingProvider implements PricingProviderInterface
{
    private ?TwilioPricingService $service = null;

    /**
     * Builds the service straight away, so an account that is missing
     * fails here — before the caller prints or fetches anything — with the
     * exact error the pricing command has always given.
     *
     * Through the container, not `new`: this is full source that buyers
     * customise, and one who has bound their own TwilioPricingService must
     * not be quietly bypassed. The account is always passed explicitly, so
     * the service acts on exactly what the caller resolved, never on a
     * lookup of its own.
     */
    public function initialize(array $credentials): static
    {
        $this->service = app()->make(TwilioPricingService::class, [
            'accountSid' => (string) ($credentials['account_sid'] ?? ''),
            'authToken' => (string) ($credentials['auth_token'] ?? ''),
        ]);

        return $this;
    }

    public function fetchPricing(string $serviceType, ?string $page = null): array
    {
        $service = $this->service
            ?? throw new Exception('Twilio pricing provider not initialized. Call initialize() first.');

        $result = match ($serviceType) {
            'call' => $service->fetchVoicePricing($page),
            'sms' => $service->fetchSmsPricing($page),
            'phone_number' => $service->fetchPhoneNumberPricing($page),
        };

        return [
            'pricing' => $result['pricing'],
            'next_page' => $result['next_page_url'],
        ];
    }

    public function getName(): string
    {
        return 'twilio';
    }
}
