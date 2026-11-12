<?php

declare(strict_types=1);

namespace App\Contracts\Telephony;

use App\Models\UserKycVerification;

/**
 * Provider-agnostic phone verification for KYC.
 *
 * Lives alongside SmsProviderInterface, VoiceProviderInterface, etc.
 * Implementations: TwilioVerificationProvider, PlivoVerificationProvider.
 *
 * Unlike the other capabilities, both halves of a verification act on the
 * same row of `user_kyc_verifications` — the outstanding code, when it
 * expires and how many attempts it has survived all live there — so the
 * record is the argument. Where the outstanding code is *kept* is the
 * providers' own business and differs between them: Twilio's Verify
 * service holds it remotely and this app never sees it, while a provider
 * with no verification product keeps a hash of it on the row.
 */
interface VerificationProviderInterface
{
    /**
     * Initialize the provider with credentials array.
     *
     * @param  array  $credentials  Provider-specific credentials
     */
    public function initialize(array $credentials): static;

    /**
     * Deliver a verification code to the record's phone number.
     *
     * Throws on failure, with a message safe to show the customer who
     * asked for the code — it reaches them as a flashed error.
     *
     * @throws \Exception
     */
    public function start(UserKycVerification $kyc): void;

    /**
     * Check a code the customer submitted.
     *
     * `error` is the message to show when `verified` is false, so that the
     * caller neither invents one nor has to know which provider answered.
     *
     * @return array{verified: bool, error: ?string}
     */
    public function check(UserKycVerification $kyc, string $code): array;

    /**
     * Get the provider name identifier.
     */
    public function getName(): string;
}
