<?php

declare(strict_types=1);

namespace App\Exceptions\Telephony;

use RuntimeException;

/**
 * The browser softphone cannot be given login material right now — the
 * installation's provider is not configured, or setting up this user's
 * identity at the provider failed.
 *
 * Its message is safe to show the user. The underlying cause, which may
 * carry provider detail, is logged where it is raised rather than carried
 * here.
 */
class SoftphoneUnavailableException extends RuntimeException {}
