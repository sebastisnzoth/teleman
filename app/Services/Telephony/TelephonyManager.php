<?php

declare(strict_types=1);

namespace App\Services\Telephony;

use App\Contracts\Telephony\AudioStreamProviderInterface;
use App\Contracts\Telephony\CallXmlInterface;
use App\Contracts\Telephony\PhoneNumberProviderInterface;
use App\Contracts\Telephony\PricingProviderInterface;
use App\Contracts\Telephony\SmsProviderInterface;
use App\Contracts\Telephony\VerificationProviderInterface;
use App\Contracts\Telephony\VoiceProviderInterface;
use App\Contracts\Telephony\WebhookNormalizerInterface;
use App\Contracts\Telephony\WebRTCTokenProviderInterface;
use App\Enums\TelephonyProvider;
use App\Services\Telephony\Plivo\PlivoAudioStreamProvider;
use App\Services\Telephony\Plivo\PlivoCallXmlGenerator;
use App\Services\Telephony\Plivo\PlivoPhoneNumberProvider;
use App\Services\Telephony\Plivo\PlivoPricingProvider;
use App\Services\Telephony\Plivo\PlivoSMSProvider;
use App\Services\Telephony\Plivo\PlivoVerificationProvider;
use App\Services\Telephony\Plivo\PlivoVoiceProvider;
use App\Services\Telephony\Plivo\PlivoWebhookNormalizer;
use App\Services\Telephony\Plivo\PlivoWebRTCTokenProvider;
use App\Services\Telephony\Twilio\TwilioAudioStreamProvider;
use App\Services\Telephony\Twilio\TwilioCallXmlGenerator;
use App\Services\Telephony\Twilio\TwilioPhoneNumberProvider;
use App\Services\Telephony\Twilio\TwilioPricingProvider;
use App\Services\Telephony\Twilio\TwilioSmsProvider;
use App\Services\Telephony\Twilio\TwilioVerificationProvider;
use App\Services\Telephony\Twilio\TwilioVoiceProvider;
use App\Services\Telephony\Twilio\TwilioWebhookNormalizer;
use App\Services\Telephony\Twilio\TwilioWebRTCTokenProvider;

/**
 * The single place the rest of the application asks "which telephony
 * provider is this installation locked to, and give me its implementation
 * of X". There is exactly one active provider at a time (see
 * TelephonyLock) — this is not a multi-provider router.
 *
 * provider() resolves TelephonyLock's lock, falling back to
 * config('telephony.default') before any lock exists (e.g. during
 * install), fresh on every call — TelephonyLock's own 60-second cache is
 * the only caching layer for *which* provider is active, deliberately:
 * this class is bound as a singleton and can be held by a long-running
 * process (a queue worker), so nothing here may depend on a caller
 * remembering to invalidate anything after a lock change. Every other
 * accessor below resolves the concrete implementation for that same
 * provider, lazily, through the container — the classes in the registry
 * need not exist until the accessor that needs them is actually called.
 * Every one of them hands back an *uninitialised* provider, and the six
 * credential-bearing capabilities are built fresh on every call: callers
 * must initialize() them per tenant, per use. See flush().
 *
 * webhookNormalizer() is the one accessor that takes an explicit
 * provider: which normalizer to use is driven by which webhook URL was
 * hit, not by the lock.
 */
final class TelephonyManager
{
    /**
     * provider value => capability => concrete implementation class.
     *
     * @var array<string, array<string, class-string>>
     */
    private const REGISTRY = [
        'twilio' => [
            'voice' => TwilioVoiceProvider::class,
            'sms' => TwilioSmsProvider::class,
            'callXml' => TwilioCallXmlGenerator::class,
            'phoneNumbers' => TwilioPhoneNumberProvider::class,
            'webrtcToken' => TwilioWebRTCTokenProvider::class,
            'webhookNormalizer' => TwilioWebhookNormalizer::class,
            'audioStream' => TwilioAudioStreamProvider::class,
            'verification' => TwilioVerificationProvider::class,
            'pricing' => TwilioPricingProvider::class,
        ],
        'plivo' => [
            'voice' => PlivoVoiceProvider::class,
            'sms' => PlivoSMSProvider::class,
            'callXml' => PlivoCallXmlGenerator::class,
            'phoneNumbers' => PlivoPhoneNumberProvider::class,
            'webrtcToken' => PlivoWebRTCTokenProvider::class,
            'webhookNormalizer' => PlivoWebhookNormalizer::class,
            'audioStream' => PlivoAudioStreamProvider::class,
            'verification' => PlivoVerificationProvider::class,
            'pricing' => PlivoPricingProvider::class,
        ],
    ];

    /**
     * Capabilities that carry credentials — the ones whose interface
     * declares `initialize(array $credentials): static`. These are never
     * memoized: see flush() for why a shared, initialised instance is a
     * cross-tenant hazard in this app. Every other capability (callXml,
     * audioStream, webhookNormalizer) is stateless and is memoized in
     * $resolved below.
     *
     * @var array<int, string>
     */
    private const CREDENTIAL_BEARING = ['voice', 'sms', 'phoneNumbers', 'webrtcToken', 'verification', 'pricing'];

    /**
     * Resolved *stateless* implementation instances, keyed by
     * "<provider>.<capability>". Keying by provider too (not capability
     * alone) means a lock change naturally misses this cache instead of
     * returning an instance built for the provider that used to be active
     * — correct without anyone having to call flush().
     *
     * @var array<string, object>
     */
    private array $resolved = [];

    /**
     * Resolved webhook normalizers, keyed by provider value (the one
     * accessor not driven by the lock, so it is not keyed by capability
     * alone).
     *
     * @var array<string, WebhookNormalizerInterface>
     */
    private array $webhookNormalizers = [];

    public function __construct(private readonly TelephonyLock $lock) {}

    /**
     * The provider this installation is locked to, or the configured
     * default before any lock exists. Not memoized here — see the class
     * docblock for why: TelephonyLock::current() is already cached, and
     * that is the only cache this method relies on.
     *
     * tryFrom(), not from(), on the config value: `telephony.default`
     * comes from `TELEPHONY_PROVIDER` in a hand-edited .env on a buyer's
     * shared host, and can just as easily be absent entirely (an install
     * still serving a `bootstrap/cache/config.php` written before
     * config/telephony.php existed returns null for it). from() would
     * raise an uncaught ValueError/TypeError from the deepest layer of
     * the telephony stack for a one-character typo — while the shared
     * Inertia prop rescues the same mistake and renders the page — so
     * every backend telephony call would 500 on a page that loaded fine.
     * An unrecognised value falls back to Twilio, which is both the
     * product default (D1) and the only provider any existing
     * installation runs.
     */
    public function provider(): TelephonyProvider
    {
        return $this->lock->current()
            ?? TelephonyProvider::tryFrom((string) config('telephony.default'))
            ?? TelephonyProvider::TWILIO;
    }

    public function voice(): VoiceProviderInterface
    {
        return $this->resolve('voice');
    }

    public function sms(): SmsProviderInterface
    {
        return $this->resolve('sms');
    }

    public function callXml(): CallXmlInterface
    {
        return $this->resolve('callXml');
    }

    public function phoneNumbers(): PhoneNumberProviderInterface
    {
        return $this->resolve('phoneNumbers');
    }

    public function webrtcToken(): WebRTCTokenProviderInterface
    {
        return $this->resolve('webrtcToken');
    }

    public function audioStream(): AudioStreamProviderInterface
    {
        return $this->resolve('audioStream');
    }

    public function verification(): VerificationProviderInterface
    {
        return $this->resolve('verification');
    }

    public function pricing(): PricingProviderInterface
    {
        return $this->resolve('pricing');
    }

    /**
     * Which webhook URL was hit determines the provider here — unlike
     * every other accessor above, this one is not driven by the lock.
     */
    public function webhookNormalizer(TelephonyProvider $provider): WebhookNormalizerInterface
    {
        return $this->webhookNormalizers[$provider->value] ??= app()->make(
            self::REGISTRY[$provider->value]['webhookNormalizer']
        );
    }

    /**
     * Drop every memoized stateless implementation instance, forcing the
     * next call to callXml(), audioStream() or webhookNormalizer() to
     * build a fresh one. voice(), sms(), phoneNumbers(), webrtcToken(),
     * verification() and pricing() are not memoized at all, so flush() has
     * nothing to do for them.
     *
     * Which *provider* the accessors resolve to never depends on this
     * being called — see provider() and the $resolved docblock above.
     *
     * What every accessor returns is an **uninitialised, credential-free**
     * instance. Six of the nine capabilities are credential-bearing
     * (VoiceProviderInterface, SmsProviderInterface,
     * PhoneNumberProviderInterface, WebRTCTokenProviderInterface,
     * VerificationProviderInterface and PricingProviderInterface each
     * declare `initialize(array $credentials): static`), and this app is
     * multi-tenant — MakeCampaignCallJob already resolves per-user Twilio
     * credentials with initializeForUser($campaign->user). So:
     *
     *  - Every caller must call initialize() with the credentials of the
     *    tenant it is acting for, every time it uses one of these
     *    instances. Never assume an accessor hands back something already
     *    initialised: nothing here initialises anything.
     *  - An instance must never be shared across tenants, or held across
     *    jobs. This manager is a container singleton and a `queue:work`
     *    process keeps it alive for its whole `--max-time` window, so an
     *    instance initialised for user A would otherwise still be there
     *    when the next job for user B asked for the same capability.
     *    That is why the credential-bearing ones are handed out fresh on
     *    every call rather than memoized — the type system cannot express
     *    "initialise me first", so the object's lifetime does it instead.
     */
    public function flush(): void
    {
        $this->resolved = [];
        $this->webhookNormalizers = [];
    }

    /**
     * The implementation of $capability for the active provider, always
     * uninitialised. A credential-bearing capability is built fresh every
     * call and never memoized; a stateless one is memoized in $resolved.
     * See flush() for why the distinction exists.
     */
    private function resolve(string $capability): object
    {
        $provider = $this->provider()->value;
        $implementation = self::REGISTRY[$provider][$capability];

        if (in_array($capability, self::CREDENTIAL_BEARING, true)) {
            return app()->make($implementation);
        }

        return $this->resolved["{$provider}.{$capability}"] ??= app()->make($implementation);
    }
}
