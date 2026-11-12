<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Telephony\TelephonyLock;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hides a Twilio-only route when the installation is locked to a provider
 * that doesn't support it.
 *
 * Used as `telephony.feature:<key>`, where `<key>` is one of the values in
 * config('telephony.hidden_features.<provider>') — see
 * App\Enums\TelephonyProvider::hides(). This middleware never reads that
 * config directly and never re-derives the hidden list itself; hides() is
 * the single source of truth.
 *
 * Aborts 404, not 403: a hidden feature should look absent to a buyer
 * locked to a provider that can't do it, not forbidden — there is nothing
 * for them to request access to.
 *
 * An unlocked installation (TelephonyLock::current() === null — a fresh
 * install whose wizard never finished, or what a destructive provider
 * reset leaves behind) hides nothing, on purpose: every feature stays
 * reachable so an admin is never stranded mid-recovery by a gate that
 * guessed at a provider nobody chose yet.
 *
 * This is one of two layers. The other is the `hidden` array the
 * `telephony` Inertia shared prop carries (HandleInertiaRequests::share())
 * and resources/js/lib/telephony.ts's isHidden() reads, which keeps the
 * matching sidebar entry from rendering at all
 * (resources/js/components/app-sidebar.tsx). Both trace back to the same
 * TelephonyLock::current() + TelephonyProvider::hides() computation, so
 * they cannot drift apart — but only this middleware is real enforcement:
 * the sidebar merely stops an entry from appearing, while a bookmarked or
 * emailed URL reaches this middleware directly regardless of what the
 * sidebar ever rendered.
 */
class EnsureTelephonyFeature
{
    public function __construct(private readonly TelephonyLock $lock) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $locked = $this->lock->current();

        if ($locked !== null && $locked->hides($feature)) {
            abort(404);
        }

        return $next($request);
    }
}
