<?php

declare(strict_types=1);

namespace App\Exceptions\Telephony;

use RuntimeException;

/**
 * Thrown by ResetTelephonyProviderAction when one of the reset's
 * preconditions fails, before anything has been written.
 *
 * The reset is the most destructive operation in this product, so its
 * refusals carry a message written for the admin who triggered it — the
 * controller and the console command both surface getMessage() verbatim
 * rather than substituting a generic "something went wrong". Nothing in
 * these messages may include a credential or a provider secret; they
 * describe state (what is running, what was typed), never configuration.
 */
final class TelephonyResetRefusedException extends RuntimeException {}
