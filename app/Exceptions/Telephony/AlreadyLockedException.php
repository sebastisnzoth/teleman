<?php

declare(strict_types=1);

namespace App\Exceptions\Telephony;

use RuntimeException;

/**
 * Thrown by TelephonyLock::lock() when locking to a provider is attempted
 * while a different provider is already locked.
 *
 * The product rule is one telephony provider per installation,
 * permanently. Switching to another provider is the destructive admin
 * reset flow's job (release the lock, then lock the new one) — never a
 * direct re-lock to something else.
 */
final class AlreadyLockedException extends RuntimeException {}
