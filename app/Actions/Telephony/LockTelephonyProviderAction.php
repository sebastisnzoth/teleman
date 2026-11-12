<?php

declare(strict_types=1);

namespace App\Actions\Telephony;

use App\Enums\TelephonyProvider;
use App\Exceptions\Telephony\AlreadyLockedException;
use App\Models\TelephonyProviderConfig;
use App\Models\User;
use App\Services\Telephony\TelephonyLock;

/**
 * The "choose a provider" write use-case: the installer's provider step
 * and the admin destructive-reset flow both lock the installation to a
 * provider, and this is the one Action both call rather than reaching
 * past it into TelephonyLock directly. It is also the single place a
 * future audit-log entry for that decision belongs.
 *
 * All of the actual locking behaviour — the idempotent no-op on
 * re-locking the same provider, the AlreadyLockedException on a
 * different one, the mutex and transaction that make it race-safe — is
 * TelephonyLock's job. This Action only translates the caller's User
 * into the plain user id TelephonyLock::lock() takes, so callers can
 * hold a model rather than an id.
 */
final class LockTelephonyProviderAction
{
    public function __construct(
        private readonly TelephonyLock $lock,
    ) {}

    /**
     * @throws AlreadyLockedException if a different provider is already locked.
     */
    public function execute(TelephonyProvider $provider, ?User $by = null): TelephonyProviderConfig
    {
        return $this->lock->lock($provider, $by?->id);
    }
}
