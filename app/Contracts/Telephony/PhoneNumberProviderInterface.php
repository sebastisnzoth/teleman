<?php

declare(strict_types=1);

namespace App\Contracts\Telephony;

/**
 * Provider-agnostic interface for phone number operations.
 */
interface PhoneNumberProviderInterface
{
    /**
     * Initialize provider with credentials.
     */
    public function initialize(array $credentials): static;

    /**
     * Search available phone numbers for purchase.
     *
     * @return array List of available numbers
     */
    public function searchAvailableNumbers(string $countryCode, array $options = []): array;

    /**
     * Purchase/provision a phone number.
     *
     * @return array{success: bool, number: ?string, number_sid: ?string, error: ?string}
     */
    public function purchaseNumber(string $phoneNumber, array $options = []): array;

    /**
     * Release/delete a phone number.
     */
    public function releaseNumber(string $numberSid): bool;

    /**
     * List owned phone numbers on the account.
     *
     * @return array List of phone numbers with capabilities
     */
    public function listNumbers(): array;

    /**
     * Look up a single number this account already owns, by identifier —
     * the O(1) alternative to paging through listNumbers() to answer
     * "do I already own this one", which every implementation should
     * prefer for that question rather than searching a full listing.
     *
     * Returns null both when this account does not own the number and on
     * any lookup failure — callers cannot distinguish "not owned" from
     * "could not check" from this return value alone.
     *
     * @return array{number: string, number_sid: string, capabilities: array{voice: bool, sms: bool, mms: bool}}|null
     */
    public function getOwnedNumber(string $number): ?array;

    /**
     * Update phone number configuration (voice URL, SMS URL, etc.).
     */
    public function updateNumberConfig(string $numberSid, array $config): bool;

    /**
     * Get the provider name.
     */
    public function getName(): string;
}
