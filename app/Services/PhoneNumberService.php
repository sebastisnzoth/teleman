<?php

namespace App\Services;

use App\Contracts\Telephony\PhoneNumberProviderInterface;
use App\Enums\TelephonyProvider;
use App\Helpers\PhoneNumberHelper;
use App\Models\PhoneNumber;
use App\Models\TelephonyProviderConfig;
use App\Models\TwilioGlobalConfig;
use App\Services\Telephony\Plivo\PlivoNumberFormat;
use App\Services\Telephony\TelephonyManager;
use Twilio\Rest\Client as TwilioClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PhoneNumberService
{
    protected $twilio;
    protected $pricingCache = [];

    /**
     * Search/purchase/configure/release now route through this for a
     * Plivo-locked installation — see the provider-branching methods
     * below. The Twilio branches were left exactly as they were before
     * this class knew about Plivo (see each method's own docblock for
     * why), so $telephony is unused on that path.
     */
    public function __construct(private readonly TelephonyManager $telephony)
    {
        // Get Twilio credentials from global config
        try {
            $config = TwilioGlobalConfig::active();

            if ($config) {
                $this->twilio = new TwilioClient(
                    $config->account_sid,
                    $config->getDecryptedAuthToken()
                );
            }
        } catch (\Exception $e) {
            // Config table doesn't exist yet or no config, skip initialization
            $this->twilio = null;
        }
    }

    /**
     * Get phone number pricing from Twilio for a specific country
     * Caches results for 24 hours to reduce API calls
     */
    protected function getPhoneNumberPricing(string $countryCode): ?float
    {
        if (!$this->twilio) {
            return null; // No pricing available without Twilio config
        }

        $cacheKey = "twilio_pricing_{$countryCode}";
        
        // Check memory cache first
        if (isset($this->pricingCache[$countryCode])) {
            return $this->pricingCache[$countryCode];
        }

        // Check Laravel cache (24 hours)
        if (Cache::has($cacheKey)) {
            $price = Cache::get($cacheKey);
            $this->pricingCache[$countryCode] = $price;
            return $price;
        }

        try {
            // Get credentials for HTTP request
            $config = TwilioGlobalConfig::active();
            if (!$config) {
                return 1.00;
            }

            // Fetch pricing from Twilio using HTTP (SDK method doesn't work reliably)
            $response = Http::withBasicAuth(
                $config->account_sid,
                $config->getDecryptedAuthToken()
            )->get("https://pricing.twilio.com/v1/PhoneNumbers/Countries/{$countryCode}");

            if (!$response->successful()) {
                throw new \Exception("API returned status {$response->status()}");
            }

            $data = $response->json();
            $phoneNumberPrices = $data['phone_number_prices'] ?? [];
            $monthlyPrice = null;
            
            if (!empty($phoneNumberPrices)) {
                // Get the first available price (usually local numbers)
                foreach ($phoneNumberPrices as $priceData) {
                    if (isset($priceData['base_price'])) {
                        $monthlyPrice = (float) $priceData['base_price'];
                        break;
                    }
                }
            }

            // If no specific pricing found, use default
            $price = $monthlyPrice ?? 1.00;

            // Cache for 24 hours
            Cache::put($cacheKey, $price, now()->addHours(24));
            $this->pricingCache[$countryCode] = $price;

            Log::info('Fetched Twilio pricing', [
                'country' => $countryCode,
                'monthly_price' => $price,
            ]);

            return $price;
        } catch (\Exception $e) {
            Log::warning('Failed to fetch Twilio pricing - no price set', [
                'country' => $countryCode,
                'error' => $e->getMessage(),
            ]);
            
            // Don't cache null prices, allow retry
            return null;
        }
    }

    /**
     * Search numbers available to buy right now, through the locked
     * provider, and cache them locally as browsable 'available' rows.
     *
     * Twilio branch is the exact code that existed before this class knew
     * about Plivo (only normalizeCapabilities() now wraps its already-
     * boolean capability values — a value-preserving no-op for real
     * booleans, kept here so both providers pass through the same "what
     * does capable actually mean" trust boundary before a customer ever
     * sees it — see normalizeCapabilities()).
     */
    public function syncAvailableNumbers($countryCode = 'US', $areaCode = null, $limit = 50)
    {
        if ($this->telephony->provider() === TelephonyProvider::PLIVO) {
            return $this->syncAvailableNumbersFromPlivo($countryCode, $areaCode, (int) $limit);
        }

        if (!$this->twilio) {
            throw new \Exception('Twilio not configured. Please configure Twilio in Settings → Twilio.');
        }

        try {
            $options = ['limit' => $limit];

            // Add area code filter if provided
            if ($areaCode) {
                $options['areaCode'] = $areaCode;
            }

            $availableNumbers = $this->twilio->availablePhoneNumbers($countryCode)
                ->local
                ->read($options);

            // Fetch pricing for this country once
            $monthlyPrice = $this->getPhoneNumberPricing($countryCode);

            Log::info('Syncing available numbers with pricing', [
                'country' => $countryCode,
                'monthly_price' => $monthlyPrice,
                'number_count' => count($availableNumbers),
            ]);

            $count = 0;
            $synced = [];

            foreach ($availableNumbers as $twilioNumber) {
                $phoneNumber = PhoneNumber::updateOrCreate(
                    ['number' => $twilioNumber->phoneNumber],
                    [
                        'friendly_name' => $twilioNumber->friendlyName ?? $twilioNumber->phoneNumber,
                        'country_code' => $countryCode,
                        'area_code' => PhoneNumberHelper::extractAreaCode($twilioNumber->phoneNumber),
                        'status' => 'available',
                        'capabilities' => $this->normalizeCapabilities([
                            'voice' => $twilioNumber->capabilities->voice ?? false,
                            'sms' => $twilioNumber->capabilities->sms ?? false,
                            'mms' => $twilioNumber->capabilities->mms ?? false,
                        ]),
                        'monthly_cost' => $monthlyPrice,
                    ]
                );
                $synced[] = $phoneNumber;
                $count++;
            }

            return $synced;
        } catch (\Exception $e) {
            throw new \Exception("Failed to sync Twilio numbers: " . $e->getMessage());
        }
    }

    /**
     * Plivo's mirror of the Twilio branch above: browse what is available
     * to buy right now and cache it locally as 'available' rows. No
     * provider identifier is written yet — exactly like the Twilio
     * branch, sync only browses; purchaseNumber() is what actually buys
     * a number and records who owns it.
     *
     * Plivo has no wholesale per-country pricing lookup wired up anywhere
     * in this codebase (unlike Twilio's pricing.twilio.com call above),
     * so monthly_cost falls back to the same $1.00 default
     * purchaseNumberFromPlivo() uses — not a live guarantee, matching how
     * Twilio's own purchaseNumber() below already hardcodes the same
     * default rather than calling getPhoneNumberPricing() itself.
     *
     * @return array<int, PhoneNumber>
     */
    private function syncAvailableNumbersFromPlivo(string $countryCode, ?string $areaCode, int $limit): array
    {
        $provider = $this->telephony->phoneNumbers()
            ->initialize($this->resolveProviderCredentials(TelephonyProvider::PLIVO));

        $options = ['limit' => $limit];
        if ($areaCode) {
            $options['area_code'] = $areaCode;
        }

        $results = $provider->searchAvailableNumbers($countryCode, $options);

        Log::info('Syncing available Plivo numbers', [
            'country' => $countryCode,
            'number_count' => count($results),
        ]);

        $synced = [];

        foreach ($results as $result) {
            $rawNumber = $result['number'] ?? null;

            if (!$rawNumber) {
                continue;
            }

            // Plivo's own API represents numbers without a leading '+'
            // (confirmed against the installed SDK's own resource
            // classes) — normalised to E.164 here, at the point a number
            // from Plivo first enters this application, so every
            // downstream consumer (this row, the admin's own validation
            // regex requiring a '+', the adoption lookup in
            // purchaseNumberFromPlivo()) sees the same format this app
            // already assumes everywhere else. See toE164()/toPlivoFormat().
            $number = $this->toE164($rawNumber);

            $synced[] = PhoneNumber::updateOrCreate(
                ['number' => $number],
                [
                    'friendly_name' => $this->toE164($result['friendly_name'] ?? $rawNumber),
                    'country_code' => $countryCode,
                    'area_code' => PhoneNumberHelper::extractAreaCode($number),
                    'status' => 'available',
                    'capabilities' => $this->normalizeCapabilities($result['capabilities'] ?? []),
                    'monthly_cost' => 1.00,
                ]
            );
        }

        return $synced;
    }

    /**
     * Purchase a number from the locked provider and mark as available
     * (assignNumber() below is the separate step that hands it to a
     * customer).
     *
     * Twilio branch is the exact code that existed before this class knew
     * about Plivo — left untouched rather than routed through
     * TelephonyManager::phoneNumbers() too, because TwilioPhoneNumberProvider
     * ::purchaseNumber()'s shape does not carry a friendly name through to
     * Twilio at creation time or the real per-number capabilities Twilio's
     * own create() response includes, and reproducing those losslessly
     * through the interface was judged not worth the risk to "zero
     * behaviour change for Twilio" — mirrors InitiateCallAction's own,
     * identically-reasoned choice to leave its Twilio API-call branch off
     * the abstraction (see that class's docblock).
     */
    public function purchaseNumber($phoneNumberOrString, $friendlyName = null)
    {
        // Handle both PhoneNumber model and string
        $numberString = is_string($phoneNumberOrString) ? $phoneNumberOrString : $phoneNumberOrString->number;

        if ($this->telephony->provider() === TelephonyProvider::PLIVO) {
            return $this->purchaseNumberFromPlivo($numberString, $friendlyName);
        }

        if (!$this->twilio) {
            throw new \Exception('Twilio not configured.');
        }

        try {
            $purchasedNumber = $this->twilio->incomingPhoneNumbers->create([
                'phoneNumber' => $numberString,
                'friendlyName' => $friendlyName,
            ]);

            // Create or update the phone number record
            $phoneNumber = PhoneNumber::updateOrCreate(
                ['number' => $numberString],
                [
                    'twilio_sid' => $purchasedNumber->sid,
                    'formatted_number' => PhoneNumberHelper::format($numberString),
                    'friendly_name' => $friendlyName ?? $numberString,
                    'country_code' => 'US', // Default, can be enhanced
                    'area_code' => PhoneNumberHelper::extractAreaCode($numberString),
                    'status' => 'available',
                    'capabilities' => [
                        'voice' => $purchasedNumber->capabilities->voice ?? false,
                        'sms' => $purchasedNumber->capabilities->sms ?? false,
                        'mms' => $purchasedNumber->capabilities->mms ?? false,
                    ],
                    'monthly_cost' => 1.00, // Default Twilio cost
                ]
            );

            return $phoneNumber;
        } catch (\Exception $e) {
            throw new \Exception("Failed to purchase number: " . $e->getMessage());
        }
    }

    /**
     * Purchase a number from Plivo and attach it to this installation's
     * Application in the same request — a Plivo number not attached to an
     * Application cannot receive calls or messages here (see
     * PlivoApplicationProvisioner), so purchase is refused up front,
     * before Plivo is ever contacted, when the Application has not been
     * provisioned. Buying and attaching cannot half-succeed against Plivo
     * itself: both happen in the single phoneNumbers->buy() call
     * (app_id — see PlivoPhoneNumberProvider), so there is no window
     * where Plivo has taken payment for a number this installation
     * cannot route.
     *
     * What *can* still leave Plivo holding payment for a number this app
     * has no record of is everything *after* that call succeeds: the
     * local write just below, or — realistically the more likely
     * trigger — a caller further up the stack (e.g. a credit-affordability
     * check) throwing inside the same database transaction this method is
     * running in, discarding the row this method returns along with
     * everything else in that transaction. Three things make that
     * recoverable rather than a silent, permanent loss:
     *
     *  1. findOwnedPlivoNumber() below, checked before every purchase
     *     attempt: a retry after a rolled-back purchase adopts the
     *     already-owned number instead of asking Plivo to sell it again
     *     (which Plivo refuses outright, with no path back through this
     *     app before this fix).
     *  2. The Log::error() immediately after a successful buy(), before
     *     any local write is attempted. Logging is not part of the
     *     database transaction a caller may be running this inside, so
     *     this line survives a rollback that the local row does not —
     *     it is what makes a stranded purchase discoverable at all when
     *     the local save itself did not throw.
     *  3. The catch block below, for the narrower case where the local
     *     write itself is what fails.
     *
     * $numberString arrives here in this app's own E.164 format (with a
     * leading '+') — everything Plivo-facing below converts to and from
     * Plivo's own format at the point of each call; see
     * toPlivoFormat()/toE164().
     */
    private function purchaseNumberFromPlivo(string $numberString, ?string $friendlyName): PhoneNumber
    {
        $appId = $this->resolvePlivoAppId();

        if (!$appId) {
            throw new \Exception('Plivo application is not provisioned for this platform. Ask an administrator to verify Plivo credentials in Settings → Telephony.');
        }

        $provider = $this->telephony->phoneNumbers()
            ->initialize($this->resolveProviderCredentials(TelephonyProvider::PLIVO));

        $owned = $this->findOwnedPlivoNumber($provider, $numberString);

        if ($owned !== null) {
            Log::info('Plivo already owns this number; adopting it instead of buying it again', [
                'number' => $numberString,
                'number_sid' => $owned['number_sid'],
            ]);

            return $this->recordPlivoNumber($numberString, $owned['number_sid'], $friendlyName, $provider, $owned['capabilities']);
        }

        $result = $provider->purchaseNumber($this->toPlivoFormat($numberString), ['app_id' => $appId]);

        if (!$result['success']) {
            throw new \Exception('Failed to purchase number: '.($result['error'] ?? 'Unknown error'));
        }

        $providerNumberSid = $this->toE164((string) $result['number_sid']);

        // Durable and outside any transaction a caller may be running
        // this method inside — see point 2 in this method's own
        // docblock. This is the only trace of the purchase left if a
        // later step (in this class or a caller further up the stack)
        // rolls the whole thing back.
        Log::error('Plivo purchased a phone number', [
            'number' => $numberString,
            'number_sid' => $providerNumberSid,
        ]);

        return $this->recordPlivoNumber($numberString, $providerNumberSid, $friendlyName, $provider);
    }

    /**
     * Whether this Plivo account already owns $numberString, and its real
     * capabilities if so — checked via the O(1) single-number lookup
     * (PhoneNumberProviderInterface::getOwnedNumber()) rather than paging
     * through listNumbers(), which caps at 20 results per page with
     * nothing on this class following the next link: on an admin pool
     * serving many businesses, a listing-based check would be blind to
     * every owned number past the first page.
     *
     * @return array{number_sid: string, capabilities: array{voice: bool, sms: bool, mms: bool}}|null
     */
    private function findOwnedPlivoNumber(PhoneNumberProviderInterface $provider, string $numberString): ?array
    {
        $owned = $provider->getOwnedNumber($this->toPlivoFormat($numberString));

        if ($owned === null) {
            return null;
        }

        return [
            'number_sid' => $this->toE164((string) ($owned['number_sid'] ?? $owned['number'] ?? $numberString)),
            'capabilities' => $this->normalizeCapabilities($owned['capabilities'] ?? []),
        ];
    }

    /**
     * Write (or adopt) a Plivo number's local record. Shared by the
     * fresh-purchase and already-owned-adoption paths in
     * purchaseNumberFromPlivo() above, so the bookkeeping — including the
     * capabilities fallback below — only exists once.
     *
     * $ownedCapabilities carries real capabilities from an adoption lookup
     * (findOwnedPlivoNumber()), when this write is adopting an
     * already-owned number. Always used when given — an owned number's
     * real, provider-reported capabilities are strictly better than a
     * guess, including for a row that already has different data
     * recorded, since an adoption is the one moment this app actually
     * hears back from Plivo about what the number can do.
     *
     * @param  array{voice: bool, sms: bool, mms: bool}|null  $ownedCapabilities
     */
    private function recordPlivoNumber(string $numberString, string $providerNumberSid, ?string $friendlyName, PhoneNumberProviderInterface $provider, ?array $ownedCapabilities = null): PhoneNumber
    {
        $attributes = [
            'provider' => TelephonyProvider::PLIVO->value,
            'provider_number_sid' => $providerNumberSid,
            'source' => 'plivo_direct',
            'formatted_number' => PhoneNumberHelper::format($numberString),
            'friendly_name' => $friendlyName ?? $numberString,
            'country_code' => 'US',
            'area_code' => PhoneNumberHelper::extractAreaCode($numberString),
            'status' => 'available',
            'monthly_cost' => 1.00,
        ];

        if ($ownedCapabilities !== null) {
            $attributes['capabilities'] = $ownedCapabilities;
        } elseif (!PhoneNumber::where('number', $numberString)->exists()) {
            // Plivo's buy() response carries no capability data at all,
            // unlike Twilio's create() response (which the untouched
            // Twilio branch of purchaseNumber() already persists) — so a
            // brand-new row, never seen by syncAvailableNumbersFromPlivo()
            // first, would otherwise keep capabilities permanently null,
            // and the SMS capability gate (SmsController) would report it
            // unsupported forever. Filled in with the same
            // unconfirmed-means-false default normalizeCapabilities()
            // applies everywhere else, and only for a genuinely new row:
            // an existing one (almost always already carrying real data
            // from an earlier sync) is left alone rather than overwritten
            // with a worse guess.
            $attributes['capabilities'] = $this->normalizeCapabilities([]);
        }

        try {
            $phoneNumber = PhoneNumber::updateOrCreate(['number' => $numberString], $attributes);
        } catch (\Throwable $e) {
            // Plivo has already bought and attached the number (see
            // purchaseNumberFromPlivo()'s docblock). Losing the local row
            // here would strand a number this installation is already
            // paying for with no record of it anywhere in this app —
            // worse than a clearly-labelled failure, so this says exactly
            // what happened rather than a generic "purchase failed" that
            // would send an admin looking in the wrong place, or buying
            // it again.
            Log::error('Plivo purchased a number but the local record could not be saved', [
                'number' => $numberString,
                'number_sid' => $providerNumberSid,
                'error' => $e->getMessage(),
            ]);

            throw new \Exception(sprintf(
                'Plivo purchased %s successfully, but saving it locally failed (%s). '
                .'The number is live on your Plivo account — contact support before buying it again.',
                $numberString,
                $e->getMessage(),
            ));
        }

        // Best-effort only: Plivo's buy() has no alias parameter, so the
        // label is applied in a follow-up call. updateNumberConfig()
        // never throws (it catches internally and returns false), and a
        // missing alias is cosmetic — not a reason to fail a purchase
        // Plivo has already completed and this app has already recorded.
        if ($friendlyName && !$provider->updateNumberConfig($this->toPlivoFormat($numberString), ['alias' => $friendlyName])) {
            Log::warning('Failed to set the Plivo alias after purchase', ['number' => $numberString]);
        }

        return $phoneNumber;
    }

    /**
     * Assign a phone number to a user
     */
    public function assignNumber(PhoneNumber $phoneNumber, $user)
    {
        // Check if number is available
        if ($phoneNumber->status !== 'available') {
            throw new \Exception('This phone number is not available for assignment.');
        }

        // Update the phone number
        $phoneNumber->update([
            'user_id' => $user->id,
            'status' => 'assigned',
            'assigned_at' => now(),
        ]);

        return $phoneNumber->fresh();
    }

    /**
     * Configure phone number for WebRTC (Twilio) / attach it to this
     * installation's Application (Plivo).
     *
     * Twilio branch unchanged. $twimlAppSid is meaningless on the Plivo
     * branch and is ignored there — see configureForPlivoApplication().
     */
    public function configureForWebRTC(PhoneNumber $phoneNumber, $twimlAppSid)
    {
        if ($this->telephony->provider() === TelephonyProvider::PLIVO) {
            return $this->configureForPlivoApplication($phoneNumber);
        }

        if (!$this->twilio) {
            throw new \Exception('Twilio not configured.');
        }

        if (!$phoneNumber->twilio_sid) {
            throw new \Exception('Phone number does not have a Twilio SID.');
        }

        try {
            $this->twilio->incomingPhoneNumbers($phoneNumber->twilio_sid)
                ->update([
                    'voiceApplicationSid' => $twimlAppSid,
                ]);

            return true;
        } catch (\Exception $e) {
            throw new \Exception("Failed to configure number for WebRTC: " . $e->getMessage());
        }
    }

    /**
     * Plivo's analogue of the Twilio branch above: (re-)attach this
     * number to the installation's one Application id, so a number that
     * somehow ended up without one — bought before the Application
     * existed, or via a path that skipped it — gets fixed by whatever
     * already calls configureForWebRTC() today (see
     * Admin\PhoneNumberController::assignNumber()'s self-healing "Step 3",
     * which does the same for Twilio). The Application id is this
     * installation's own single, already-provisioned value
     * (PlivoApplicationProvisioner), resolved internally rather than
     * threaded through every caller — mirrors how InitiateCallAction
     * resolves Plivo credentials itself instead of taking them as a
     * parameter.
     */
    private function configureForPlivoApplication(PhoneNumber $phoneNumber): bool
    {
        $appId = $this->resolvePlivoAppId();
        $identifier = $phoneNumber->provider_number_sid ?: $phoneNumber->number;

        if (!$appId) {
            throw new \Exception('Plivo application is not provisioned for this platform. Ask an administrator to verify Plivo credentials in Settings → Telephony.');
        }

        if (!$identifier) {
            throw new \Exception('Phone number does not have a Plivo identifier.');
        }

        $provider = $this->telephony->phoneNumbers()
            ->initialize($this->resolveProviderCredentials(TelephonyProvider::PLIVO));

        if (!$provider->updateNumberConfig($this->toPlivoFormat($identifier), ['app_id' => $appId])) {
            throw new \Exception('Failed to configure number for the Plivo application.');
        }

        return true;
    }

    /**
     * Release a number back to the locked provider.
     *
     * Twilio branch unchanged: a provider-side failure here still throws
     * and leaves the local row untouched, exactly as before. See
     * releasePlivoNumber() for why the Plivo branch deliberately does not
     * mirror that.
     */
    public function releaseNumber(PhoneNumber $phoneNumber)
    {
        if ($this->telephony->provider() === TelephonyProvider::PLIVO) {
            return $this->releasePlivoNumber($phoneNumber);
        }

        if (!$this->twilio) {
            throw new \Exception('Twilio not configured.');
        }

        try {
            if ($phoneNumber->twilio_sid) {
                $this->twilio->incomingPhoneNumbers($phoneNumber->twilio_sid)->delete();
            }

            $phoneNumber->update([
                'status' => 'released',
                'twilio_sid' => null,
                'user_id' => null,
                'released_at' => now(),
            ]);

            return true;
        } catch (\Exception $e) {
            throw new \Exception("Failed to release number: " . $e->getMessage());
        }
    }

    /**
     * Release a Plivo number back to the provider.
     *
     * Deliberately does not mirror the Twilio branch's "throw and leave
     * the local row untouched" behaviour on failure. A release attempt is
     * not refused just because Plivo is unreachable, its credentials have
     * since changed (e.g. after a telephony reset), or Plivo rejects the
     * identifier outright (already released by someone else, moved
     * accounts, or simply stale) — PlivoPhoneNumberProvider::releaseNumber()
     * collapses every one of those into a plain false, with no way from
     * here to tell a genuine outage from Plivo correctly saying "this
     * number is not yours". Refusing to clear the local row in any of
     * those cases would leave a number a customer or admin has already
     * asked this installation to give up sitting in "assigned" — and
     * still being charged for — indefinitely, with no way to clear it
     * through this app at all. So the local release always proceeds, and
     * the provider-side outcome is only logged, giving an admin who needs
     * to reconcile a still-billing-at-Plivo case a line to find rather
     * than silently hiding it. This mirrors the same philosophy
     * ResetTelephonyProviderAction already applies at a larger scale: it
     * neutralises local state unconditionally and documents, rather than
     * guarantees, what happens at the provider.
     */
    private function releasePlivoNumber(PhoneNumber $phoneNumber): bool
    {
        $identifier = $phoneNumber->provider_number_sid ?: $phoneNumber->number;

        try {
            $credentials = $this->resolveProviderCredentials(TelephonyProvider::PLIVO);

            if (!$this->telephony->phoneNumbers()->initialize($credentials)->releaseNumber($this->toPlivoFormat($identifier))) {
                Log::warning('Plivo did not confirm release of the number; releasing the local record anyway.', [
                    'number' => $phoneNumber->number,
                    'identifier' => $identifier,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Could not reach Plivo to release the number; releasing the local record anyway.', [
                'number' => $phoneNumber->number,
                'error' => $e->getMessage(),
            ]);
        }

        $phoneNumber->update([
            'status' => 'released',
            'provider_number_sid' => null,
            'user_id' => null,
            'released_at' => now(),
        ]);

        return true;
    }

    /**
     * Revoke/unassign number from user (keeps number in inventory)
     */
    public function revokeNumber(PhoneNumber $phoneNumber)
    {
        // Don't throw exception if not assigned, just ensure it's cleared
        // This allows idempotent calls (e.g. from failed charge jobs)
        
        $phoneNumber->update([
            'status' => 'available',
            'user_id' => null,
            'assigned_at' => null,
        ]);

        return true;
    }

    /**
     * Get available numbers filtered
     */
    public function getAvailableNumbers($countryCode = null, $areaCode = null, $hasVoice = null, $hasSms = null)
    {
        $query = PhoneNumber::available();

        if ($countryCode) {
            $query->forCountry($countryCode);
        }

        if ($areaCode) {
            $query->where('area_code', $areaCode);
        }

        if ($hasVoice !== null) {
            $query->whereRaw("JSON_EXTRACT(capabilities, '$.voice') = ?", [$hasVoice ? 'true' : 'false']);
        }

        if ($hasSms !== null) {
            $query->whereRaw("JSON_EXTRACT(capabilities, '$.sms') = ?", [$hasSms ? 'true' : 'false']);
        }

        return $query->orderBy('number');
    }

    /**
     * Get enabled countries from Twilio geo-permissions
     */
    public function getEnabledCountries()
    {
        if (!$this->twilio) {
            throw new \Exception('Twilio not configured.');
        }

        try {
            // Get list of all available countries with their permissions
            $availableCountries = $this->twilio->voice->v1->dialingPermissions
                ->countries->read();
            
            $countries = [];
            foreach ($availableCountries as $country) {
                // Only include enabled countries
                $isEnabled = $country->lowRiskNumbersEnabled || 
                            $country->highRiskSpecialNumbersEnabled || 
                            $country->highRiskTollfraudNumbersEnabled;
                
                if ($isEnabled) {
                    $countries[] = [
                        'code' => $country->isoCode,
                        'name' => $country->name,
                        'continent' => $country->continent ?? 'Unknown',
                    ];
                }
            }
            
            // Sort by name
            usort($countries, function($a, $b) {
                return strcmp($a['name'], $b['name']);
            });
            
            return $countries;
        } catch (\Exception $e) {
            // If geo-permissions fail, return common countries
            return [
                ['code' => 'US', 'name' => 'United States', 'continent' => 'North America'],
                ['code' => 'GB', 'name' => 'United Kingdom', 'continent' => 'Europe'],
                ['code' => 'CA', 'name' => 'Canada', 'continent' => 'North America'],
                ['code' => 'AU', 'name' => 'Australia', 'continent' => 'Oceania'],
            ];
        }
    }

    /**
     * Get numbers assigned to a specific user
     */
    public function getUserNumbers($userId)
    {
        return PhoneNumber::where('user_id', $userId)
            ->where('status', 'assigned');
    }

    /**
     * Get number statistics
     */
    public function getStatistics()
    {
        return [
            'total' => PhoneNumber::count(),
            'available' => PhoneNumber::available()->count(),
            'assigned' => PhoneNumber::assigned()->count(),
            'requested' => PhoneNumber::requested()->count(),
            'released' => PhoneNumber::where('status', 'released')->count(),
            'total_monthly_cost' => PhoneNumber::assigned()->sum('monthly_cost') ?? 0,
        ];
    }

    /**
     * Get available numbers statistics
     */
    public function getAvailableStats()
    {
        $available = PhoneNumber::available();

        return [
            'total_available' => $available->count(),
            'voice_capable' => (clone $available)->whereRaw("JSON_EXTRACT(capabilities, '$.voice') = true")->count(),
            'sms_capable' => (clone $available)->whereRaw("JSON_EXTRACT(capabilities, '$.sms') = true")->count(),
            'average_cost' => round($available->avg('monthly_cost') ?? 0, 2),
        ];
    }

    /**
     * Credentials for the locked provider's phoneNumbers() capability.
     * Plivo: this installation's one configured account
     * (TelephonyProviderConfig, name 'plivo'), read-only — mirrors
     * InitiateCallAction/EndCallAction's own resolver. Twilio: the exact
     * message every existing Twilio-only method on this class already
     * throws, unchanged.
     *
     * @return array<string, string>
     */
    private function resolveProviderCredentials(TelephonyProvider $provider): array
    {
        if ($provider === TelephonyProvider::PLIVO) {
            $config = TelephonyProviderConfig::where('name', 'plivo')->first();
            $authId = $config?->credential('auth_id');
            $authToken = $config?->credential('auth_token');

            if (!$authId || !$authToken) {
                throw new \Exception('Plivo is not configured for this platform. Ask an administrator to configure it in Settings → Telephony.');
            }

            return ['auth_id' => $authId, 'auth_token' => $authToken];
        }

        $config = TwilioGlobalConfig::active();

        if (!$config) {
            throw new \Exception('Twilio not configured.');
        }

        return [
            'account_sid' => $config->account_sid,
            'auth_token' => $config->getDecryptedAuthToken(),
        ];
    }

    /**
     * The single Plivo Application every purchased number must be
     * attached to, or inbound calls/messages never reach this
     * installation — see PlivoApplicationProvisioner. Read-only: this
     * class never provisions the Application itself, only uses the id it
     * already stored.
     */
    private function resolvePlivoAppId(): ?string
    {
        return TelephonyProviderConfig::where('name', 'plivo')->first()?->app_id;
    }

    /**
     * Coerce a provider's capability flags to real booleans before they
     * are ever persisted or shown to a customer.
     *
     * Twilio's SDK already hands back PHP booleans, so this is a
     * value-preserving no-op on that branch. Plivo's own SDK documents
     * its voice/sms/mms fields as compared against the literal string
     * 'true' rather than a real boolean; trusting a value like that as
     * PHP-truthy would silently report every number as fully capable,
     * because the literal string 'false' is truthy in PHP. This is the
     * one seam both providers' search/list results pass through before
     * reaching a customer-facing screen (SMS::SmsController already gates
     * real SMS sending on capabilities['sms']), so "this number supports
     * X" is never asserted on anything looser than an explicit true.
     *
     * @return array{voice: bool, sms: bool, mms: bool}
     */
    private function normalizeCapabilities(array $capabilities): array
    {
        return [
            'voice' => $this->toBool($capabilities['voice'] ?? false),
            'sms' => $this->toBool($capabilities['sms'] ?? false),
            'mms' => $this->toBool($capabilities['mms'] ?? false),
        ];
    }

    private function toBool(mixed $value): bool
    {
        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['true', '1'], true);
        }

        return (bool) $value;
    }

    /**
     * Plivo's own API represents phone numbers without a leading '+'
     * (its search, list, get and buy responses all agree on this,
     * confirmed against the installed SDK's resource classes), while the
     * rest of this application stores and compares E.164 with one —
     * PhoneNumberHelper and every phone-number validation regex require
     * it, and the admin endpoint that assigns a number validates its
     * input the same way. This pair is the one boundary where that
     * difference is resolved: every number this class receives from
     * Plivo is normalised to E.164 the moment it enters here (toE164()),
     * and every number this class hands back to Plivo is converted to
     * Plivo's own format immediately before the call (toPlivoFormat()).
     * Nothing downstream of this class — the local phone_numbers table,
     * the adoption comparison, an admin typing a number into a form —
     * ever has to know Plivo's own convention differs.
     *
     * Delegates to PlivoNumberFormat (extracted later, once a second
     * consumer — PlivoWebhookNormalizer/SmsWebhookController — needed the
     * identical conversion and could not reach these private methods) so
     * the two do not drift: they briefly disagreed on an empty string
     * (this method used to turn '' into the bare '+'; PlivoNumberFormat
     * leaves it '') before this delegation made there only one answer.
     */
    private function toE164(string $number): string
    {
        return PlivoNumberFormat::toE164($number);
    }

    private function toPlivoFormat(string $number): string
    {
        return PlivoNumberFormat::toPlivoFormat($number);
    }
}
