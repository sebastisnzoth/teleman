<?php

declare(strict_types=1);

namespace App\Services\Telephony\Plivo;

use App\Contracts\Telephony\PhoneNumberProviderInterface;
use Exception;
use Illuminate\Support\Facades\Log;
use Plivo\RestClient;

/**
 * Plivo phone number operations, behind the provider-agnostic interface.
 *
 * Ported from an earlier prototype with its REST call shapes corrected
 * against the installed plivo/plivo-php SDK (v4.71):
 *
 *  - Searching and buying available numbers is the PhoneNumberInterface
 *    ($client->phoneNumbers, not $client->numbers or $client->phonenumbers
 *    — those property names don't resolve on RestClient at all and would
 *    fatal with "Unknown resource"). The prototype's $client->phonenumbers
 *    ->search() and $client->numbers->buy() both used the wrong resource;
 *    the real methods are $client->phoneNumbers->getList($countryIso, ...)
 *    and $client->phoneNumbers->buy($phoneNumber, $appId, ...) — the
 *    latter takes the buyer's args positionally, not as one array.
 *  - Listing, updating and releasing numbers ALREADY rented on the
 *    account is the separate NumberInterface ($client->numbers). Its
 *    list method is getList(), not list() — the prototype's
 *    $client->numbers->list() would fatal ("Call to undefined method").
 *    update() and delete() were already correct in the prototype.
 */
class PlivoPhoneNumberProvider implements PhoneNumberProviderInterface
{
    /** Plivo's own maximum for one page of the account's numbers. */
    private const LIST_PAGE_SIZE = 20;

    /** Ceiling on listNumbers()'s walk — 1,000 numbers, then it stops. */
    private const MAX_LIST_PAGES = 50;

    private ?RestClient $client = null;

    public function initialize(array $credentials): static
    {
        $this->client = new RestClient(
            $credentials['auth_id'] ?? null,
            $credentials['auth_token'] ?? null
        );

        return $this;
    }

    public function searchAvailableNumbers(string $countryCode, array $options = []): array
    {
        try {
            $this->ensureInitialized();

            $type = $options['type'] ?? 'local';
            $params = [];

            if ($type === 'toll_free') {
                $params['type'] = 'tollfree';
            }

            if (! empty($options['area_code'])) {
                $params['pattern'] = $options['area_code'];
            }

            $params['limit'] = $options['limit'] ?? 20;

            $numbers = $this->client->phoneNumbers->getList($countryCode, $params);

            return array_map(fn ($n) => [
                'number' => $n->number ?? '',
                'friendly_name' => $n->number ?? '',
                'locality' => $n->region ?? null,
                'region' => $n->region ?? null,
                'country' => $countryCode,
                'capabilities' => [
                    // A missing field is an unconfirmed capability, not a
                    // granted one — defaulting this to true would tell a
                    // customer a number supports voice when Plivo never
                    // actually said so.
                    'voice' => $n->voiceEnabled ?? false,
                    'sms' => $n->smsEnabled ?? false,
                    'mms' => $n->mmsEnabled ?? false,
                ],
                'type' => $type,
            ], iterator_to_array($numbers));
        } catch (Exception $e) {
            Log::error('Plivo: Failed to search numbers', ['error' => $e->getMessage()]);

            return [];
        }
    }

    public function purchaseNumber(string $phoneNumber, array $options = []): array
    {
        try {
            $this->ensureInitialized();

            // buy() throws PlivoResponseException on failure rather than
            // returning an error shape, so a caught exception below is the
            // failure path and reaching the return is the success path.
            $this->client->phoneNumbers->buy($phoneNumber, $options['app_id'] ?? null);

            return [
                'success' => true,
                'number' => $phoneNumber,
                'number_sid' => $phoneNumber, // Plivo uses the number itself as identifier.
                'error' => null,
            ];
        } catch (Exception $e) {
            Log::error('Plivo: Failed to purchase number', [
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
            $this->client->numbers->delete($numberSid);

            return true;
        } catch (Exception $e) {
            Log::error('Plivo: Failed to release number', [
                'number' => $numberSid,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Every number rented on the account, not merely the first page of
     * them: Plivo's Number list serves at most self::LIST_PAGE_SIZE per
     * request and pages with an offset, so one unpaged call silently
     * truncates any account holding more than that — and the screen this
     * feeds has no "next page" control of its own to make up for it.
     *
     * The walk ends on the first page that comes back shorter than a full
     * one (an empty page included, which is what an account holding an
     * exact multiple of the page size returns last). self::MAX_LIST_PAGES
     * is the backstop: if a page ever came back full no matter the offset,
     * this returns a truncated list rather than looping until the request
     * times out.
     */
    public function listNumbers(): array
    {
        try {
            $this->ensureInitialized();

            $numbers = [];

            for ($page = 0; $page < self::MAX_LIST_PAGES; $page++) {
                $batch = iterator_to_array($this->client->numbers->getList([
                    'limit' => self::LIST_PAGE_SIZE,
                    'offset' => $page * self::LIST_PAGE_SIZE,
                ]));

                $numbers = array_merge($numbers, $batch);

                if (count($batch) < self::LIST_PAGE_SIZE) {
                    break;
                }
            }

            return array_map(fn ($n) => [
                'number' => $n->number ?? '',
                'number_sid' => $n->number ?? '', // Plivo uses the number string, not a SID.
                'friendly_name' => $n->alias ?? ($n->number ?? ''),
                'capabilities' => [
                    // See searchAvailableNumbers()'s identical comment: an
                    // unconfirmed capability must never default to true.
                    'voice' => $n->voiceEnabled ?? false,
                    'sms' => $n->smsEnabled ?? false,
                    'mms' => $n->mmsEnabled ?? false,
                ],
            ], $numbers);
        } catch (Exception $e) {
            Log::error('Plivo: Failed to list numbers', ['error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * $client->numbers->get() — the single-number lookup, O(1) against
     * Plivo's own account rather than walking getList(), which caps at 20
     * numbers per request and so costs one request per 20 held on the
     * account. Callers are responsible for passing $number in
     * Plivo's own format (no leading '+') — this class does not
     * normalise, matching every other method here.
     */
    public function getOwnedNumber(string $number): ?array
    {
        try {
            $this->ensureInitialized();

            $n = $this->client->numbers->get($number);

            return [
                'number' => $n->number ?? '',
                'number_sid' => $n->number ?? '', // Plivo uses the number string, not a SID.
                'capabilities' => [
                    'voice' => $n->voiceEnabled ?? false,
                    'sms' => $n->smsEnabled ?? false,
                    'mms' => $n->mmsEnabled ?? false,
                ],
            ];
        } catch (Exception $e) {
            // A genuine 404 (not owned) and any other failure both land
            // here indistinguishably — the SDK's own validation/response
            // exceptions share no common non-Exception ancestor to tell
            // them apart by type, and every other method on this class
            // already collapses failure to a safe default rather than
            // throwing, so this matches that convention rather than
            // introducing a different one for just this method.
            Log::info('Plivo: number not found or lookup failed', [
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

            $params = [];
            if (! empty($config['app_id'])) {
                $params['app_id'] = $config['app_id'];
            }
            if (! empty($config['alias'])) {
                $params['alias'] = $config['alias'];
            }

            $this->client->numbers->update($numberSid, $params);

            return true;
        } catch (Exception $e) {
            Log::error('Plivo: Failed to update number config', [
                'number' => $numberSid,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function getName(): string
    {
        return 'plivo';
    }

    private function ensureInitialized(): void
    {
        if (! $this->client) {
            throw new Exception('Plivo phone number provider not initialized.');
        }
    }
}
