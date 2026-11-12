<?php

declare(strict_types=1);

namespace App\Services\Telephony\Twilio;

use App\Contracts\Telephony\SmsProviderInterface;
use App\Models\TwilioCredential;
use App\Services\SMS\TwilioSMSProvider as LegacyTwilioSmsProvider;

/**
 * Twilio implementation of the provider-agnostic SmsProviderInterface.
 *
 * Every operation delegates to the existing App\Services\SMS\TwilioSMSProvider,
 * which implements the older, Twilio-only App\Services\SMS\SMSProviderInterface
 * — its send/sendBatch/getStatus/validateWebhook/parseIncoming/getName/supportsMMS
 * signatures already match the new interface exactly, so nothing but
 * initialize() needs adapting. Same reasoning as TwilioVoiceProvider::makeCall():
 * a fresh, transient TwilioCredential built from whatever initialize() was
 * given, handed to a fresh instance of the legacy class, so the actual
 * SMS-sending logic is the literal same code that ships today.
 *
 * Imported under an alias: App\Services\SMS\TwilioSMSProvider differs from
 * this class's own name only by case, and PHP class names are
 * case-insensitive — without the alias this file fatals with "cannot
 * redeclare class ... previously declared as local import".
 */
class TwilioSmsProvider implements SmsProviderInterface
{
    private readonly LegacyTwilioSmsProvider $legacy;

    public function __construct()
    {
        $this->legacy = new LegacyTwilioSmsProvider;
    }

    public function initialize(array $credentials): static
    {
        $this->legacy->initialize(new TwilioCredential([
            'account_sid' => $credentials['account_sid'] ?? '',
            'auth_token' => $credentials['auth_token'] ?? '',
        ]));

        return $this;
    }

    public function send(string $from, string $to, string $message, array $options = []): array
    {
        return $this->legacy->send($from, $to, $message, $options);
    }

    public function sendBatch(string $from, array $recipients, array $options = []): array
    {
        return $this->legacy->sendBatch($from, $recipients, $options);
    }

    public function getStatus(string $messageId): array
    {
        return $this->legacy->getStatus($messageId);
    }

    public function validateWebhook(array $payload, string $signature): bool
    {
        return $this->legacy->validateWebhook($payload, $signature);
    }

    public function parseIncoming(array $payload): array
    {
        return $this->legacy->parseIncoming($payload);
    }

    public function getName(): string
    {
        return $this->legacy->getName();
    }

    public function supportsMMS(): bool
    {
        return $this->legacy->supportsMMS();
    }
}
