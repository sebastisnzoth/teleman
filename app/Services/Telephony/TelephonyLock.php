<?php

declare(strict_types=1);

namespace App\Services\Telephony;

use App\Enums\TelephonyProvider;
use App\Exceptions\Telephony\AlreadyLockedException;
use App\Models\TelephonyProviderConfig;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Owns the telephony provider lock/unlock transition.
 *
 * A buyer picks one telephony provider and the app locks to it
 * permanently — switching later is a destructive admin reset, never a
 * toggle. This service is the single authoritative, race-safe place that
 * enforces "at most one provider locked at a time". TelephonyProviderConfig
 * has its own save-time guard for the same invariant, but that is only a
 * backstop (it does not fire on mass updates and is not transaction-safe
 * on its own) — this class is the real thing, following the same
 * transaction + lockForUpdate() + instance save() pattern CreditService
 * uses to guard concurrent credit deduction.
 */
final class TelephonyLock
{
    /**
     * Cache key holding the currently locked provider (or the absence of
     * one). Shared across the whole installation, not per-request.
     */
    private const CACHE_KEY = 'telephony.lock';

    /**
     * The cached stand-in for "this installation is not locked to any
     * provider". Cache::remember() treats a cached null as a miss
     * (Illuminate\Cache\Repository::get() returns the default when the
     * stored value is null, so remember() re-runs its callback every
     * time), so caching null would mean a cache read, a database read and
     * a cache write on every single request that touches the shared
     * Inertia prop. An empty string is never a valid TelephonyProvider
     * backing value, so it is unambiguous, and it caches.
     *
     * Unlocked is a real and reachable state — a fresh installation before
     * the installer's telephony step, and any window before an admin
     * chooses — but it is NOT what an upgrading buyer sees: the migration
     * that locks existing installations to Twilio settles them on install
     * of this version. Do not reason from "nothing is locked yet"; that was
     * true only before that migration existed, and reading it as still true
     * has already produced one wrong decision elsewhere in the codebase.
     */
    private const NOT_LOCKED = '';

    /**
     * How long current() trusts the cache before re-reading the database.
     */
    private const CACHE_TTL_SECONDS = 60;

    /**
     * Cache key for the atomic mutex guarding lock()/release() below. Not
     * used anywhere else in this codebase, so here is why it exists:
     * `lockForUpdate()` can only serialize access to a row that already
     * exists. On a fresh install, before any telephony_providers row
     * exists for either provider, two concurrent first-time calls to
     * lock() for two *different* providers would both find nothing to
     * acquire a row-lock on and could both proceed. A cache key always
     * exists, so `Cache::lock()` closes exactly that window — it
     * serializes every lock()/release() call regardless of what state the
     * database is in. `lockForUpdate()` stays: it remains the authority
     * once a row exists, which is everywhere except that first window.
     */
    private const MUTEX_KEY = 'telephony.lock.mutex';

    /**
     * How long the mutex may be held before it auto-expires — a safety
     * net if the holder crashes mid-transaction. The happy path releases
     * it immediately after the transaction completes, well before this.
     */
    private const MUTEX_TTL_SECONDS = 10;

    /**
     * How long a caller waits to acquire the mutex before giving up.
     */
    private const MUTEX_WAIT_SECONDS = 3;

    /**
     * The provider this installation is locked to, or null if none.
     *
     * Both outcomes are cached for CACHE_TTL_SECONDS — the locked
     * provider by its backing value, "not locked" by the NOT_LOCKED
     * sentinel, because a cached null would not cache at all (see that
     * constant). Once set, this almost never changes, so there is no
     * reason for every request to hit the database for it. lock() and
     * release() both forget the cache, so a change made through this
     * service is visible immediately rather than waiting out the TTL; a
     * change made behind its back (a mass update, a raw insert) is not.
     *
     * Safe to call before installation: the telephony_providers table may
     * not exist yet (the install wizard renders before any migration has
     * run), so a failed lookup degrades to "not locked" rather than
     * throwing — the same guard SetLocale and app.blade.php already apply
     * to their own pre-install database reads (there, the whole
     * cache-plus-query operation is wrapped in one rescue(), which is
     * mirrored here rather than only rescuing the inner query). Nothing
     * is cached in that case: the closure throws before remember() can
     * store anything, so the very next call retries the database rather
     * than serving a "not locked" answer that was really a failure.
     */
    public function current(): ?TelephonyProvider
    {
        $value = rescue(fn () => Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): string {
            return TelephonyProviderConfig::locked()?->provider()->value ?? self::NOT_LOCKED;
        }), self::NOT_LOCKED, false);

        return $value === self::NOT_LOCKED ? null : TelephonyProvider::tryFrom((string) $value);
    }

    public function isLocked(): bool
    {
        return $this->current() !== null;
    }

    /**
     * Lock the installation to the given provider.
     *
     * - A different provider already locked: refused, AlreadyLockedException.
     * - This same provider already locked: a no-op, returning the existing
     *   row unchanged (same locked_at, same locked_by_user_id) — so the
     *   install wizard and the admin reset flow can safely retry this step
     *   without it becoming an error.
     * - Not locked yet: locks it now, reusing any row already saved for
     *   this provider (e.g. credentials entered before locking) rather
     *   than creating a duplicate.
     *
     * The whole operation runs inside the mutex described on MUTEX_KEY,
     * then the database transaction + row lock — see those for why both
     * exist.
     *
     * @throws AlreadyLockedException if a different provider is locked.
     * @throws LockTimeoutException if the mutex cannot be acquired in time.
     */
    public function lock(TelephonyProvider $provider, ?int $byUserId = null): TelephonyProviderConfig
    {
        $config = Cache::lock(self::MUTEX_KEY, self::MUTEX_TTL_SECONDS)->block(
            self::MUTEX_WAIT_SECONDS,
            function () use ($provider, $byUserId) {
                return DB::transaction(function () use ($provider, $byUserId) {
                    $lockedElsewhere = TelephonyProviderConfig::query()
                        ->whereNotNull('locked_at')
                        ->where('name', '!=', $provider->value)
                        ->lockForUpdate()
                        ->first();

                    if ($lockedElsewhere !== null) {
                        throw new AlreadyLockedException(sprintf(
                            'Cannot lock the telephony provider to [%s]: the installation is already locked to [%s].',
                            $provider->value,
                            $lockedElsewhere->name,
                        ));
                    }

                    $config = TelephonyProviderConfig::query()
                        ->where('name', $provider->value)
                        ->lockForUpdate()
                        ->first() ?? new TelephonyProviderConfig(['name' => $provider->value]);

                    if ($config->isLocked()) {
                        return $config;
                    }

                    $config->locked_at = now();
                    $config->locked_by_user_id = $byUserId;
                    $config->save();

                    return $config;
                });
            }
        );

        Cache::forget(self::CACHE_KEY);

        return $config;
    }

    /**
     * Unlock the installation. Only locked_at is cleared — credentials,
     * settings, and who last locked it are left alone for the admin reset
     * flow (or a future lock() call) to read or overwrite as it goes on.
     * A no-op if nothing is currently locked.
     *
     * Clears *every* row with locked_at set, not just one. The invariant
     * is supposed to hold at most one, but release() is precisely the
     * recovery path an admin reaches for if it is ever violated — it must
     * not leave a second row wedged locked.
     *
     * @throws LockTimeoutException if the mutex cannot be acquired in time.
     */
    public function release(): void
    {
        Cache::lock(self::MUTEX_KEY, self::MUTEX_TTL_SECONDS)->block(self::MUTEX_WAIT_SECONDS, function () {
            DB::transaction(function () {
                TelephonyProviderConfig::query()
                    ->whereNotNull('locked_at')
                    ->lockForUpdate()
                    ->get()
                    ->each(function (TelephonyProviderConfig $config): void {
                        $config->locked_at = null;
                        $config->save();
                    });
            });
        });

        Cache::forget(self::CACHE_KEY);
    }
}
