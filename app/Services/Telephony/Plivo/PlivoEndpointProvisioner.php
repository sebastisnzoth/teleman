<?php

declare(strict_types=1);

namespace App\Services\Telephony\Plivo;

use App\Actions\Telephony\ResetTelephonyProviderAction;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Plivo\Exceptions\PlivoResponseException;
use Plivo\RestClient;
use Throwable;

/**
 * Provisions the one Plivo Endpoint each user's browser softphone logs in
 * with — Plivo's equivalent of the per-user identity a Twilio access token
 * carries.
 *
 * Twilio mints a short-lived signed token on every request and needs no
 * per-user state at the provider. Plivo's Browser SDK instead logs in with
 * a real, long-lived SIP Endpoint that must exist on the account first, so
 * each user needs exactly one, created once and reused. The installation
 * still configures only one Plivo account; every Endpoint lives on it.
 *
 * Shaped after PlivoApplicationProvisioner deliberately — read its class
 * docblock for the reasoning this class inherits: idempotency keyed off a
 * stored id rather than a name or a listing, self-healing on reuse, a 404
 * from update() read as "the stored id is stale", Throwable rather than
 * Exception, and a cache mutex rather than a row lock because the critical
 * section spans a network round trip.
 *
 * Two properties of the installed SDK (vendor/plivo/plivo-php) that are
 * specific to Endpoints and shape this class:
 *
 *  - EndpointInterface::create() returns EndpointCreateReponse (the
 *    library's own spelling), which carries only username, alias and
 *    endpointId. There is no SIP URI on it. The SIP URI exists only on the
 *    Endpoint object returned by get(), so provisioning is two requests.
 *  - Endpoint::__construct reads every response field with plain array
 *    access and then replaces its own properties array wholesale. The
 *    consequences are that a password can never be read back from Plivo,
 *    and that an error body produces a mostly-null object instead of an
 *    exception. So the generated password is stored locally at creation —
 *    it is unrecoverable otherwise — and a get() without a usable SIP URI
 *    is a failure, never something to persist.
 *
 * The SIP URI is stored exactly as Plivo returned it. It is not a phone
 * number, and PlivoNumberFormat must never touch it.
 *
 * Storage: one settings row per user, keyed under
 * ResetTelephonyProviderAction::PLIVO_ENDPOINT_SETTING_PREFIX, with the
 * whole record encrypted. The destructive provider reset deletes rows under
 * exactly that prefix; spelling the key any other way would leave live SIP
 * credentials behind after a reset.
 */
class PlivoEndpointProvisioner
{
    /**
     * Per-user mutex. Keyed by user rather than shared, so two different
     * users opening their softphones at the same moment never wait on one
     * another — only two loads for the same user can race to create a
     * duplicate Endpoint, so only those need to serialise.
     */
    public const MUTEX_KEY_PREFIX = 'telephony.plivo.endpoint.mutex.';

    /**
     * Endpoint usernames are built as "u<user id>x<random>", so the user an
     * Endpoint belongs to can be read back from a call arriving from it. The
     * separator is what makes that unambiguous — without one, "u12..." could
     * be user 1 or user 12. Plivo appends 12 digits to whatever it is asked
     * for, which leaves the prefix intact.
     */
    private const USERNAME_PREFIX = 'u';

    private const USERNAME_SEPARATOR = 'x';

    private const MUTEX_TTL_SECONDS = 30;

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
     * Ensure $user has exactly one working Endpoint on this installation's
     * Plivo account, attached to $appId when one is given, and return its
     * login credentials.
     *
     * Never throws: every failure, including calling this before
     * initialize() and a contended mutex, comes back as the failure shape.
     *
     * @return array{success: bool, endpoint_id: ?string, username: ?string, password: ?string, sip_uri: ?string, error: ?string}
     */
    public function ensureFor(User $user, ?string $appId): array
    {
        try {
            return Cache::lock(self::MUTEX_KEY_PREFIX.$user->id, self::MUTEX_TTL_SECONDS)->block(
                self::MUTEX_WAIT_SECONDS,
                fn () => $this->ensureLocked($user, $appId)
            );
        } catch (LockTimeoutException) {
            return $this->failure('Another request is already setting up this softphone. Please try again in a moment.');
        }
    }

    /**
     * The decrypted record stored for $user, or null when none exists or it
     * cannot be decrypted (an APP_KEY rotation, for instance) — in which
     * case the caller provisions a fresh Endpoint rather than failing.
     *
     * @return array{endpoint_id: string, username: string, password: string, sip_uri: string}|null
     */
    public function storedFor(User $user): ?array
    {
        $setting = Setting::where('key', self::settingKey($user))->first();

        if (! $setting || ! $setting->value) {
            return null;
        }

        try {
            $record = json_decode(Crypt::decryptString($setting->value), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        foreach (['endpoint_id', 'username', 'password', 'sip_uri'] as $key) {
            if (! is_string($record[$key] ?? null) || $record[$key] === '') {
                return null;
            }
        }

        return $record;
    }

    public static function settingKey(User $user): string
    {
        return ResetTelephonyProviderAction::PLIVO_ENDPOINT_SETTING_PREFIX.$user->id;
    }

    /**
     * Runs inside the per-user mutex. The stored record is read fresh here
     * rather than passed in: that fresh read is what lets the second of two
     * overlapping loads see the first one's Endpoint and reuse it.
     */
    private function ensureLocked(User $user, ?string $appId): array
    {
        try {
            if (! $this->client) {
                throw new \RuntimeException('Plivo endpoint provisioner not initialized. Call initialize() first.');
            }

            $stored = $this->storedFor($user);

            if ($stored !== null) {
                try {
                    // Re-applying the attachment on reuse is what repairs an
                    // Endpoint left pointing at an Application that has since
                    // been replaced. It is also the only request that can
                    // tell a stored id has been deleted at Plivo.
                    $this->client->endpoints->update($stored['endpoint_id'], $this->updateArgs($user, $appId));

                    return $this->success($stored);
                } catch (PlivoResponseException $e) {
                    if ($e->getStatusCode() !== 404) {
                        throw $e;
                    }

                    // Deleted directly in Plivo's console: provision a
                    // replacement below and overwrite the stale record.
                }
            }

            $username = $this->newUsername($user);
            $password = $this->newPassword();

            $created = $this->client->endpoints->create($username, $password, $this->alias($user), $appId);
            $endpointId = $created->getEndpointId();

            if (! is_string($endpointId) || $endpointId === '') {
                throw new \RuntimeException('Plivo did not return an Endpoint id.');
            }

            // Read directly rather than through ?? or isset(): Plivo's
            // Resource exposes fields only through __get() and defines no
            // __isset(), so isset() on this property reports false even
            // when it holds a value. A direct read is unambiguous; a
            // missing field throws, which the catch below reports.
            $endpoint = $this->client->endpoints->get($endpointId);
            $sipUri = $endpoint->sipUri;

            // Plivo does not create the username it was asked for: it appends
            // a 12-digit number and returns the result. That returned name is
            // the Endpoint's real identity — the subject a browser access
            // token must name, and the local part of the SIP URI — so the
            // requested name is discarded here rather than stored.
            $createdUsername = $endpoint->username;

            if (! is_string($sipUri) || $sipUri === '') {
                throw new \RuntimeException('Plivo did not return a SIP URI for the new Endpoint.');
            }

            if (! is_string($createdUsername) || $createdUsername === '') {
                throw new \RuntimeException('Plivo did not return a username for the new Endpoint.');
            }

            $record = [
                'endpoint_id' => $endpointId,
                'username' => $createdUsername,
                'password' => $password,
                'sip_uri' => $sipUri,
            ];

            Setting::updateOrCreate(
                ['key' => self::settingKey($user)],
                [
                    'user_id' => $user->id,
                    'value' => Crypt::encryptString(json_encode($record, JSON_THROW_ON_ERROR)),
                    'encrypted' => true,
                    'is_global' => false,
                ]
            );

            return $this->success($record);
        } catch (Throwable $e) {
            // Never log the record, the password or the account credentials.
            Log::error('Plivo: failed to provision a softphone Endpoint', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return $this->failure($e->getMessage());
        }
    }

    /**
     * Only sends app_id when there is one. Sending a null app_id would
     * detach the Endpoint from its Application, silently breaking calls
     * into the browser.
     */
    private function updateArgs(User $user, ?string $appId): array
    {
        $args = ['alias' => $this->alias($user)];

        if ($appId !== null && $appId !== '') {
            $args['app_id'] = $appId;
        }

        return $args;
    }

    /**
     * The username Plivo is ASKED for. It is not the one Plivo creates: it
     * appends a 12-digit number and returns that, which is what gets stored.
     *
     * Endpoint usernames are unique across the whole Plivo account, which may
     * be shared with other installations or survive a reinstall — so a bare
     * user id is not enough on its own. Alphanumeric only.
     */
    private function newUsername(User $user): string
    {
        return self::USERNAME_PREFIX.$user->id.self::USERNAME_SEPARATOR.Str::lower(Str::random(10));
    }

    /**
     * Which user an Endpoint belongs to, read from its username or from a SIP
     * URI naming it. Both forms are accepted because a browser call may
     * present either, and the 12 digits Plivo appends are tolerated.
     *
     * This identifies; it does not authenticate. A caller must still confirm
     * the value matches what is stored for that user, or anyone could name
     * any user simply by spelling the pattern.
     */
    public static function userIdFromIdentity(string $identity): ?int
    {
        // Case-insensitive on the scheme: SIP URIs are commonly written
        // lowercase and Plivo's own samples are, but the scheme is not
        // case-sensitive and a "SIP:" prefix would otherwise leave the whole
        // identity unparsed.
        $local = Str::before(preg_replace('/^sips?:/i', '', $identity), '@');

        if (preg_match('/^'.self::USERNAME_PREFIX.'(\\d+)'.self::USERNAME_SEPARATOR.'/', $local, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    private function newPassword(): string
    {
        return Str::password(20, letters: true, numbers: true, symbols: false);
    }

    /**
     * A neutral label for Plivo's own console. White-label product: the
     * buyer's brand comes from config('app.name'), never a vendor name.
     *
     * Plivo documents the alias as letters, numbers, hyphens and underscores
     * only, and a buyer's brand is arbitrary text — "Bob's Dialer & Co" would
     * otherwise have every Endpoint creation rejected, surfacing to them only
     * as a softphone that cannot be set up.
     */
    private function alias(User $user): string
    {
        $brand = trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) config('app.name')), '-');

        return Str::limit(($brand === '' ? 'softphone' : $brand).'-user-'.$user->id, 60, '');
    }

    private function success(array $record): array
    {
        return [
            'success' => true,
            'endpoint_id' => $record['endpoint_id'],
            'username' => $record['username'],
            'password' => $record['password'],
            'sip_uri' => $record['sip_uri'],
            'error' => null,
        ];
    }

    private function failure(string $error): array
    {
        return [
            'success' => false,
            'endpoint_id' => null,
            'username' => null,
            'password' => null,
            'sip_uri' => null,
            'error' => $error,
        ];
    }
}
