<?php

declare(strict_types=1);

namespace App\Services\Telephony\Twilio;

use App\Contracts\Telephony\PhoneNumberProviderInterface;
use Exception;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

/**
 * Twilio phone number operations, behind the provider-agnostic interface.
 */
class TwilioPhoneNumberProvider implements PhoneNumberProviderInterface
{
    private ?Client $client = null;

    public function initialize(array $credentials): static
    {
        $this->client = new Client(
            $credentials['account_sid'],
            $credentials['auth_token']
        );

        return $this;
    }

    public function searchAvailableNumbers(string $countryCode, array $options = []): array
    {
        try {
            $this->ensureInitialized();

            $type = $options['type'] ?? 'local';
            $params = [];

            if (! empty($options['area_code'])) {
                $params['areaCode'] = $options['area_code'];
            }
            if (! empty($options['contains'])) {
                $params['contains'] = $options['contains'];
            }

            $params['limit'] = $options['limit'] ?? 20;

            $numbers = match ($type) {
                'local' => $this->client->availablePhoneNumbers($countryCode)->local->read($params),
                'toll_free' => $this->client->availablePhoneNumbers($countryCode)->tollFree->read($params),
                'mobile' => $this->client->availablePhoneNumbers($countryCode)->mobile->read($params),
                default => $this->client->availablePhoneNumbers($countryCode)->local->read($params),
            };

            return array_map(fn ($n) => [
                'number' => $n->phoneNumber,
                'friendly_name' => $n->friendlyName,
                'locality' => $n->locality ?? null,
                'region' => $n->region ?? null,
                'country' => $countryCode,
                'capabilities' => [
                    'voice' => $n->capabilities['voice'] ?? false,
                    'sms' => $n->capabilities['SMS'] ?? false,
                    'mms' => $n->capabilities['MMS'] ?? false,
                ],
                'type' => $type,
            ], $numbers);
        } catch (Exception $e) {
            Log::error('Twilio: Failed to search numbers', ['error' => $e->getMessage()]);

            return [];
        }
    }

    public function purchaseNumber(string $phoneNumber, array $options = []): array
    {
        try {
            $this->ensureInitialized();

            $params = ['phoneNumber' => $phoneNumber];

            if (! empty($options['voice_url'])) {
                $params['voiceUrl'] = $options['voice_url'];
            }
            if (! empty($options['sms_url'])) {
                $params['smsUrl'] = $options['sms_url'];
            }

            $number = $this->client->incomingPhoneNumbers->create($params);

            return [
                'success' => true,
                'number' => $number->phoneNumber,
                'number_sid' => $number->sid,
                'error' => null,
            ];
        } catch (Exception $e) {
            Log::error('Twilio: Failed to purchase number', [
                'number' => $phoneNumber,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'number' => $phoneNumber,
                'number_sid' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function releaseNumber(string $numberSid): bool
    {
        try {
            $this->ensureInitialized();
            $this->client->incomingPhoneNumbers($numberSid)->delete();

            return true;
        } catch (Exception $e) {
            Log::error('Twilio: Failed to release number', [
                'sid' => $numberSid,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function listNumbers(): array
    {
        try {
            $this->ensureInitialized();

            $numbers = $this->client->incomingPhoneNumbers->read();

            return array_map(fn ($n) => [
                'number' => $n->phoneNumber,
                'number_sid' => $n->sid,
                'friendly_name' => $n->friendlyName,
                'capabilities' => [
                    'voice' => $n->capabilities['voice'] ?? false,
                    'sms' => $n->capabilities['SMS'] ?? false,
                    'mms' => $n->capabilities['MMS'] ?? false,
                ],
            ], $numbers);
        } catch (Exception $e) {
            Log::error('Twilio: Failed to list numbers', ['error' => $e->getMessage()]);

            return [];
        }
    }

    public function getOwnedNumber(string $number): ?array
    {
        try {
            $this->ensureInitialized();

            $numbers = $this->client->incomingPhoneNumbers->read(['phoneNumber' => $number], 1);

            if (empty($numbers)) {
                return null;
            }

            $n = $numbers[0];

            return [
                'number' => $n->phoneNumber,
                'number_sid' => $n->sid,
                'capabilities' => [
                    'voice' => $n->capabilities['voice'] ?? false,
                    'sms' => $n->capabilities['SMS'] ?? false,
                    'mms' => $n->capabilities['MMS'] ?? false,
                ],
            ];
        } catch (Exception $e) {
            Log::error('Twilio: Failed to look up owned number', [
                'number' => $number,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function updateNumberConfig(string $numberSid, array $config): bool
    {
        try {
            $this->ensureInitialized();
            $this->client->incomingPhoneNumbers($numberSid)->update($config);

            return true;
        } catch (Exception $e) {
            Log::error('Twilio: Failed to update number config', [
                'sid' => $numberSid,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function getName(): string
    {
        return 'twilio';
    }

    private function ensureInitialized(): void
    {
        if (! $this->client) {
            throw new Exception('Twilio phone number provider not initialized.');
        }
    }
}
