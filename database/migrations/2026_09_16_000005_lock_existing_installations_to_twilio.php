<?php

declare(strict_types=1);

use App\Enums\TelephonyProvider;
use App\Exceptions\Telephony\AlreadyLockedException;
use App\Services\Telephony\TelephonyLock;
use App\Support\Installation;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * A buyer now picks one telephony provider and the app locks to it
     * permanently (see TelephonyProviderConfig, TelephonyLock) — a new
     * install chooses during the install wizard's telephony step. This
     * migration is what happens to every installation that already
     * existed before that step did: they were always on Twilio, the only
     * provider that ever existed for them, so they are locked to it here,
     * silently — no prompt, no banner, no behaviour change. The only
     * difference is a row in telephony_providers.
     *
     * "Already installed" is detected the same way Installation decides
     * it everywhere else: an admin account exists. A brand-new install has
     * no users at all at the point migrations run — the admin account
     * isn't created until the end of the install wizard, after its
     * telephony step — so that case is left a deliberate no-op here for
     * that step to handle instead. Getting this backwards would lock a
     * brand-new buyer to Twilio before they ever saw the choice.
     *
     * Written through TelephonyLock — never a builder upsert() or mass
     * update — because a mass update fires no model events and would
     * silently skip TelephonyProviderConfig's single-lock guard. Resolving
     * it here is not awkward: migrations run inside a fully booted
     * application (this class itself is bound as a singleton with no
     * constructor arguments), so `app(TelephonyLock::class)` is exactly as
     * safe as anywhere else in the codebase.
     *
     * Idempotent by construction: locking the same provider twice is a
     * documented no-op in TelephonyLock::lock(), and if some other
     * provider is already locked (e.g. a fresh install on a version that
     * already had the telephony step, where a buyer chose Plivo before an
     * admin account — and therefore this migration's condition — existed)
     * that is a real, deliberate choice that must be left exactly as it
     * is, not overridden just because an admin now also exists. lock()
     * reports that case with AlreadyLockedException rather than silently
     * doing nothing, so it is caught here.
     */
    public function up(): void
    {
        if (! Installation::hasAdminUser()) {
            return;
        }

        try {
            app(TelephonyLock::class)->lock(TelephonyProvider::TWILIO);
        } catch (AlreadyLockedException) {
            // Another provider already holds the lock — leave it alone.
        }
    }

    /**
     * Deliberately a no-op.
     *
     * The provider lock is a one-way product decision everywhere else in
     * this codebase — switching providers is a distinct, explicit admin
     * reset, never something a migration rollback should do as a side
     * effect. There is also no reliable way to tell "the lock this
     * migration created" apart from a same-shaped lock made afterwards by
     * the ordinary install wizard (both lock Twilio with no actor), so an
     * attempt to unlock only "what this migration did" would really just
     * be guessing. Releasing the lock unconditionally would be worse: it
     * could undo a legitimate change made by something else entirely
     * after up() ran. Rolling back this migration therefore leaves
     * whatever is currently locked untouched; reversing the lock itself,
     * if that is ever genuinely wanted, is what the admin reset flow
     * (TelephonyLock::release()) is for.
     */
    public function down(): void
    {
        // Intentionally empty — see the docblock above.
    }
};
