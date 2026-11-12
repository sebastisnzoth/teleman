<?php

declare(strict_types=1);

namespace App\Services\Telephony\Plivo;

use App\Models\TelephonyProviderConfig;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Plivo\Exceptions\PlivoResponseException;
use Plivo\RestClient;
use Throwable;

/**
 * Provisions the single Plivo Application this installation's numbers and
 * endpoints attach to — Plivo's equivalent of a Twilio TwiML App.
 *
 * This installation configures exactly one Plivo account for the whole
 * product — customers never configure telephony themselves — so there is
 * exactly one Application to point at it, not one per customer.
 * initialize() takes credentials as plain data, mirroring PlivoVoiceProvider.
 *
 * Two public entry points, deliberately kept separate:
 *
 *  - ensure() is the pure mechanism: given whatever id is already stored
 *    (or null), decide create vs. update vs. stale-id-replace, and return
 *    the result. It knows nothing about TelephonyProviderConfig and
 *    persists nothing — safe to unit-test against the SDK alone, which is
 *    most of what this class's own test file does.
 *  - ensureFor() is what every real caller should use. It wraps ensure()
 *    with the atomicity a bare read-then-write does not have — see
 *    "Concurrency" below — and is the one that persists the result.
 *
 * Idempotency is keyed off the caller-supplied "existing app id" — the id
 * already stored in telephony_providers.app_id, if any — not a name or a
 * listing. An id is the only identifier that survives a rename and cannot
 * collide with an unrelated Application the same Plivo account happens to
 * hold.
 *
 * ensure() always re-applies the current callback URLs to whatever
 * Application it ends up with. Reusing an existing one is therefore not
 * only "don't create a duplicate" — it also self-heals a stale URL (a
 * buyer moving off a temporary domain onto their real one is the expected
 * case) every time an admin re-runs verification, with no separate "did
 * the URL change" check to get wrong. ensureFor() additionally snapshots
 * those URLs into telephony_providers.settings so the admin page can
 * notice, without a live call to Plivo, when they have drifted from what
 * this installation would provision right now — see
 * TelephonySettingsController::plivoProps().
 *
 * Detecting a stale stored id: Plivo\Resources\Application\
 * ApplicationInterface::get() does not check its decoded response for an
 * "error" key the way create()/update() do, so a request for a deleted
 * app_id does not reliably throw — Plivo's 404 body has no `app_id` key,
 * and the SDK's Application constructor reads response fields with plain
 * array access, which is one PHP warning away from silently building a
 * mostly-null Application object instead of failing loudly (verified
 * against the installed vendor/plivo/plivo-php v4.71 — the Call and
 * Recording resources have the same class of issue; see
 * PlivoVoiceProvider's own docblock for that sibling case). update(), by
 * contrast, explicitly checks the decoded response for an "error" key and
 * throws a well-formed PlivoResponseException carrying the real HTTP
 * status code. So this class never calls get() at all: it tries update()
 * first and treats a 404 specifically as "the stored id is stale",
 * falling back to create(). Any other failure (a network problem, a
 * genuine permissions issue, Plivo returning a 5xx) is a real
 * provisioning failure and is surfaced as one rather than being swallowed
 * into creating a spare Application.
 *
 * Concurrency: verify() reads a stored id, may call Plivo, then writes a
 * (possibly new) id back — a bare read-then-write of that shape is not
 * safe under two overlapping requests. Two admins (or two tabs, or a
 * retried slow request) pressing Verify close together can both observe
 * no stored id, both create an Application, and the second write
 * silently orphans the first at Plivo, with nothing in this installation
 * ever pointing at it again. ensureFor() closes that window with
 * Cache::lock() — the same mechanism TelephonyLock uses for its own
 * lock()/release(), rather than the row-lock-in-a-transaction pattern
 * CreditService uses for its atomicity requirement: the critical section
 * here spans a real network call to Plivo, and holding a database row
 * lock across that would tie up a connection for as long as Plivo takes
 * to answer, which is fine for CreditService's in-process arithmetic and
 * not fine here. The mutex has its own key, independent of
 * TelephonyLock::MUTEX_KEY — a different critical section, which must not
 * contend with unrelated provider lock/unlock traffic.
 */
class PlivoApplicationProvisioner
{
    /**
     * Cache key for the mutex guarding ensureFor()'s read-provision-persist
     * sequence. See the class docblock's "Concurrency" section.
     */
    public const MUTEX_KEY = 'telephony.plivo.application.mutex';

    /**
     * How long the mutex may be held before it auto-expires — a safety net
     * if the holder crashes mid-request. Longer than TelephonyLock's own
     * 10 seconds because the critical section here includes a real Plivo
     * API round trip, not just database work.
     */
    private const MUTEX_TTL_SECONDS = 30;

    /**
     * How long a caller waits to acquire the mutex before giving up. Long
     * enough that an ordinary double-click, or two admins pressing Verify
     * within a couple of seconds of each other, simply resolves in order
     * (the second call sees the first one's id and updates it) instead of
     * bailing out with "try again".
     */
    private const MUTEX_WAIT_SECONDS = 5;

    private ?RestClient $client = null;

    public function initialize(array $credentials): static
    {
        $this->client = new RestClient(
            $credentials['auth_id'] ?? null,
            $credentials['auth_token'] ?? null,
        );

        return $this;
    }

    /**
     * Ensure exactly one Plivo Application exists for this installation,
     * pointed at its current answer/hangup/message URLs, and return its
     * id. Does not touch the database — see ensureFor(), which every real
     * caller should use instead.
     *
     * Never throws — every failure, including calling this before
     * initialize(), comes back as the failure shape below so a caller can
     * flash a legible reason instead of a stack trace. Catches Throwable,
     * not just Exception: the installed SDK's ApplicationInterface::update()
     * passes its decoded error value into a string-typed exception
     * constructor parameter without casting it first, so a response
     * shaped as anything other than a string there — an array, per that
     * same exception class's own getErrorMessage() explicitly anticipating
     * that shape — raises a TypeError, not an Exception, while trying to
     * report the very failure this method exists to report legibly.
     *
     * @param  string|null  $existingAppId  The id already stored in
     *                                      telephony_providers.app_id, if any.
     * @return array{success: bool, app_id: ?string, error: ?string}
     */
    public function ensure(?string $existingAppId): array
    {
        try {
            $this->ensureInitialized();

            $args = $this->applicationArgs();

            if ($existingAppId !== null) {
                try {
                    $this->client->applications->update($existingAppId, $args);

                    return ['success' => true, 'app_id' => $existingAppId, 'error' => null];
                } catch (PlivoResponseException $e) {
                    if ($e->getStatusCode() !== 404) {
                        throw $e;
                    }

                    // The stored id no longer resolves to anything at
                    // Plivo — most likely deleted directly in their
                    // console. Fall through and create a replacement;
                    // the caller overwrites the stale id with the new one.
                }
            }

            $response = $this->client->applications->create($this->applicationName(), $args);
            $appId = $response->getAppId();

            if (! is_string($appId) || $appId === '') {
                throw new \RuntimeException('Plivo did not return an Application id.');
            }

            return ['success' => true, 'app_id' => $appId, 'error' => null];
        } catch (Throwable $e) {
            // Never log $args or the credentials. The URLs are not
            // secret, but there is no reason a log line should ever be
            // the place someone double-checks whether that is still true.
            Log::error('Plivo: failed to provision the Application', ['error' => $e->getMessage()]);

            return ['success' => false, 'app_id' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * The atomic, persisting version of ensure() — see the class
     * docblock's "Concurrency" section for why a bare call to ensure()
     * around a read and a write is not safe, and why this uses a cache
     * mutex rather than a database row lock.
     *
     * Re-reads $config's app_id fresh inside the lock, rather than
     * trusting whatever the caller already had in memory: that fresh read
     * is what lets the second of two overlapping callers see the first
     * one's result and update() the same Application instead of creating
     * another. On success, also persists a snapshot of the URLs just
     * applied into $config->settings — see currentUrls().
     *
     * Never throws: a contended mutex comes back as the same failure
     * shape ensure() already promises, not a LockTimeoutException the
     * caller has to know to catch separately.
     *
     * @return array{success: bool, app_id: ?string, error: ?string}
     */
    public function ensureFor(TelephonyProviderConfig $config): array
    {
        try {
            return Cache::lock(self::MUTEX_KEY, self::MUTEX_TTL_SECONDS)->block(
                self::MUTEX_WAIT_SECONDS,
                function () use ($config) {
                    $result = $this->ensure($config->fresh()?->app_id);

                    if ($result['success'] === true) {
                        $config->update([
                            'app_id' => $result['app_id'],
                            'settings' => ['provisioned_urls' => $this->currentUrls()],
                        ]);
                    }

                    return $result;
                }
            );
        } catch (LockTimeoutException) {
            return [
                'success' => false,
                'app_id' => null,
                'error' => 'Another request is already provisioning the Plivo Application. Please try again in a moment.',
            ];
        }
    }

    /**
     * The three URLs Plivo calls back to, built from this application's
     * own route helpers — never string-concatenated against
     * config('app.url') or an admin-entered base, both of which go subtly
     * wrong the moment an installation lives at a subdirectory or behind
     * a proxy. Computed fresh on every call, deliberately: see the class
     * docblock on why that is also how a changed domain gets fixed, not
     * just how a fresh install gets its first correct value.
     *
     * Public, and deliberately the single source both applicationArgs()
     * and TelephonySettingsController::plivoProps() read from — so the
     * three route names are spelled once, not duplicated between what
     * gets provisioned and what a later page render compares it against.
     *
     * @return array{answer_url: string, hangup_url: string, message_url: string}
     */
    public function currentUrls(): array
    {
        return [
            // Where a call lands when it is answered.
            'answer_url' => route('plivo-xml.inbound.call'),
            // Plivo's name for "the call's outcome": requested when the
            // call this Application answered hangs up. No {call_id} in
            // the path — an Application's URLs are fixed at the
            // application level, the same reason webhooks.plivo.call-status
            // exists as a standalone route alongside the per-call
            // webhooks.plivo.call.status.
            'hangup_url' => route('webhooks.plivo.call-status'),
            // Inbound SMS to a number attached to this Application.
            'message_url' => route('sms.webhook.plivo.incoming'),
        ];
    }

    /**
     * @return array{answer_url: string, answer_method: string, hangup_url: string, hangup_method: string, message_url: string, message_method: string}
     */
    private function applicationArgs(): array
    {
        $urls = $this->currentUrls();

        return [
            'answer_url' => $urls['answer_url'],
            'answer_method' => 'POST',
            'hangup_url' => $urls['hangup_url'],
            'hangup_method' => 'POST',
            'message_url' => $urls['message_url'],
            'message_method' => 'POST',
        ];
    }

    /**
     * A generic, brand-neutral label for Plivo's own console. This is a
     * white-label product — nothing here may hardcode the vendor's own
     * product name; config('app.name') is the buyer's brand.
     *
     * Plivo accepts letters, numbers, hyphens and underscores in an
     * Application name — no spaces — and a buyer's brand is arbitrary text.
     * Sending it as typed is why an installation branded "The Voice Ora"
     * was told `Invalid Application name` on every verify, and met it only
     * as inbound calls and messages that could not be set up. The brand is
     * folded into Plivo's alphabet here, the same way PlivoEndpointProvisioner
     * already folds it for an Endpoint alias.
     *
     * A brand written entirely outside that alphabet folds away to nothing,
     * so the generic label stands in rather than sending an empty name.
     *
     * Renaming is safe for an installation that already has an Application:
     * it is found by the id in telephony_providers.app_id, never by name.
     */
    private function applicationName(): string
    {
        $brand = trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) config('app.name')), '-');

        return Str::limit(($brand === '' ? 'Telephony' : $brand.'-Telephony'), 60, '');
    }

    private function ensureInitialized(): void
    {
        if (! $this->client) {
            throw new \RuntimeException('Plivo application provisioner not initialized. Call initialize() first.');
        }
    }
}
