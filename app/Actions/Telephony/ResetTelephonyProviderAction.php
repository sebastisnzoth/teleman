<?php

declare(strict_types=1);

namespace App\Actions\Telephony;

use App\Actions\Calls\EndCallAction;
use App\Enums\TelephonyProvider;
use App\Exceptions\Telephony\TelephonyResetRefusedException;
use App\Models\Call;
use App\Models\TelephonyProviderReset;
use App\Models\User;
use App\Services\Telephony\TelephonyLock;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The destructive telephony provider reset — the only way to change the
 * provider an installation is locked to.
 *
 * This is the most destructive operation in the product, so the two rules
 * it follows are worth stating before the code:
 *
 * 1. **It deletes provider configuration and credentials. It never
 *    deletes a row that customer history points at.** Where a row is both
 *    — a phone number is provider inventory *and* the anchor a customer's
 *    SMS threads hang from — the row is neutralised in place: stripped of
 *    every provider identifier and marked released, but kept.
 *
 * 2. **Nothing is released at the provider.** The customer's Twilio or
 *    Plivo account keeps its numbers, trunks and applications. This clears
 *    local state only, and the interface says so, because an admin who
 *    assumed otherwise would stop paying attention to a bill that is still
 *    running.
 *
 * Why rule 1 is not merely tidy: `sms_conversations.phone_number_id` is
 * NOT NULL with `ON DELETE CASCADE`, and `sms_messages.conversation_id`
 * cascades from there. Deleting phone number rows — the obvious reading of
 * "wipe the provider's data" — therefore destroys a customer's entire SMS
 * history as a side effect, silently, on both MySQL and SQLite. Detaching
 * instead is impossible: the column cannot hold null, and
 * `sms_conversations` has no `user_id`, so a detached conversation would
 * also lose the only path by which its owner can be determined.
 *
 * The order of operations below is load-bearing for the same reason; see
 * the comment on execute().
 */
final class ResetTelephonyProviderAction
{
    /**
     * Key prefix for the per-user Plivo softphone endpoint credentials
     * kept in `settings` (`plivo.endpoint.{user_id}`). Those rows are
     * provider state — SIP credentials minted against one Plivo account —
     * so the reset clears them.
     *
     * PlivoEndpointProvisioner is what writes them, on the softphone token
     * endpoint's behalf (IssueSoftphoneTokenAction). Anything else that
     * comes to write one must reference this constant rather than
     * re-spelling the string, or a reset will silently start leaving stale
     * endpoint credentials behind.
     */
    public const PLIVO_ENDPOINT_SETTING_PREFIX = 'plivo.endpoint.';

    /**
     * Campaign statuses that mean "this campaign is about to place, or is
     * placing, calls".
     */
    private const BLOCKING_CAMPAIGN_STATUSES = ['running', 'scheduled'];

    /**
     * Call statuses that mean "this call has not finished".
     *
     * `initiated` is in this list because it is what this application
     * actually stores for a call that is still connecting: Twilio's own
     * `queued` is normalised to `initiated` before it ever reaches the
     * database (WebhookHandlerService, UpdateCallStatusAction,
     * MakeCampaignCallJob, CampaignController, Api\AiAgentCallController
     * and TwilioSyncService all map `'queued', 'initiated' => 'initiated'`).
     * A precondition that only looked for `queued` would wave a reset
     * through in the exact moment a campaign had just dialled. `queued` is
     * kept as well so that a row written by anything that skips that
     * normalisation still blocks.
     */
    private const BLOCKING_CALL_STATUSES = ['initiated', 'queued', 'ringing', 'in-progress'];

    public function __construct(
        private readonly TelephonyLock $lock,
        private readonly EndCallAction $endCall,
    ) {}

    /**
     * @param  User|null  $actor  The admin running the reset, taken from the
     *                            authenticated session by the controller and
     *                            null when run from the console. Never from
     *                            request input.
     * @param  string  $confirmation  What the admin typed to confirm.
     *
     * @throws TelephonyResetRefusedException if any precondition fails. Nothing
     *                                        has been written when it is thrown.
     */
    public function execute(?User $actor, string $confirmation, string $source = TelephonyProviderReset::SOURCE_WEB, bool $force = false): TelephonyProviderReset
    {
        $provider = $this->guardPreconditions($confirmation, $force);

        // Forcing does not ignore the work in flight; it ends it. This runs
        // before the transaction opens because hanging a call up is a
        // request to the provider — slow, and able to fail — and neither
        // belongs inside a transaction that is about to rewrite a dozen
        // tables. It also has to happen while the credentials still exist:
        // a few lines further down they are deleted, and after that there
        // is nothing left to hang a call up with.
        $forced = $force ? $this->endWorkInFlight($provider) : null;

        $reset = DB::transaction(function () use ($provider, $actor, $source, $force, $forced): TelephonyProviderReset {
            // The checks above ran before this transaction opened and took
            // no locks, so they are only a fast, readable fail. They are
            // not the authority: `campaigns:process-scheduled` runs every
            // minute and LaunchCampaignAction can flip a campaign to
            // running at any moment, so between the check and here a
            // campaign could have started dialling. Re-checking inside the
            // transaction, holding locks on the rows the counts read, is
            // what actually makes the precondition unbypassable — and
            // throwing here rolls the whole wipe back.
            $this->assertStillSafe($provider, $force);

            // The phone numbers are recorded *before* they are neutralised:
            // afterwards there is no way to tell which rows this reset
            // retired from ones that were already released.
            $retiredNumbers = $this->phoneNumbersAboutToBeRetired();

            $neutralised = [
                // MUST run first. Both trunk tables cascade into
                // phone_numbers (`phone_numbers.trunk_id` and
                // `.trunk_phone_number_id`, both ON DELETE CASCADE), and
                // phone_numbers cascades into sms_conversations. Clearing
                // the trunk links here is what makes deleting the trunks
                // below safe; reorder these and a SIP-trunk-sourced
                // number, plus every SMS thread on it, disappears.
                'phone_numbers' => $this->neutralisePhoneNumbers(),
                'campaigns' => $this->detachCampaigns(),
                'ai_agents' => $this->detachAiAgents(),
                'users' => $this->clearUserProviderFlags(),
                'number_requests' => $this->cancelPendingNumberRequests(),
                'telephony_providers' => $this->clearProviderCredentials(),
            ];

            $deleted = [
                // Twilio account configuration.
                'twilio_global_config' => DB::table('twilio_global_config')->delete(),
                'twilio_credentials' => DB::table('twilio_credentials')->delete(),
                'twilio_account_migrations' => DB::table('twilio_account_migrations')->delete(),

                // BYOC trunks, children first. connection_policies and
                // connection_policy_targets would be taken by the cascade
                // from byoc_trunks anyway; they are deleted explicitly so
                // their counts appear in the audit rather than vanishing
                // into a cascade nobody recorded.
                'byoc_health_logs' => DB::table('byoc_health_logs')->delete(),
                'connection_policy_targets' => DB::table('connection_policy_targets')->delete(),
                'connection_policies' => DB::table('connection_policies')->delete(),
                'byoc_trunks' => DB::table('byoc_trunks')->delete(),

                // Twilio Elastic SIP trunks, children first.
                'trunk_health_logs' => DB::table('trunk_health_logs')->delete(),
                'trunk_phone_numbers' => DB::table('trunk_phone_numbers')->delete(),
                'user_sip_trunks' => DB::table('user_sip_trunks')->delete(),

                // The provider's SMS number inventory. Nothing references
                // this table — SMS conversations moved off it onto
                // phone_numbers — so it carries no history.
                'sms_phone_numbers' => DB::table('sms_phone_numbers')->delete(),

                // In-flight queue state. The preconditions already
                // guarantee no call is live, so these are leftovers.
                'call_queue_entries' => DB::table('call_queue_entries')->delete(),

                // Per-user Plivo softphone endpoint credentials only —
                // every other setting is the customer's own.
                'settings' => DB::table('settings')
                    ->where('key', 'like', self::PLIVO_ENDPOINT_SETTING_PREFIX.'%')
                    ->delete(),
            ];

            // Releasing inside the transaction keeps the unlock atomic
            // with the wipe: a crash halfway cannot leave an installation
            // that is still locked to a provider whose credentials are
            // gone.
            $this->lock->release();

            return TelephonyProviderReset::query()->create([
                'from_provider' => $provider->value,
                'source' => $source,
                // Snapshotted, not only referenced: by_user_id is nulled if
                // this admin's account is later deleted, and this row is
                // the only evidence the reset happened.
                'by_user_id' => $actor?->id,
                'by_user_name' => $actor?->name,
                'by_user_email' => $actor?->email,
                'counts' => array_filter([
                    'deleted' => $deleted,
                    'neutralised' => $neutralised,
                    // Only present on a forced reset, so an audit row says
                    // plainly whether work was ended to make room for it.
                    'forced' => $forced,
                ], fn ($v) => $v !== null),
                'neutralised_phone_numbers' => $retiredNumbers,
            ]);
        });

        // release() forgot the lock cache while the transaction was still
        // open, so a concurrent request reading the lock in that window
        // could have re-cached the old, still-committed value for the full
        // cache TTL. Running it again now that the commit is visible
        // re-forgets the cache; it is a no-op on the database because
        // nothing is locked any more.
        //
        // rescue() because this runs *after* the wipe is committed: if the
        // mutex is momentarily unavailable, the reset has still succeeded,
        // and turning that into a 500 would tell the admin their most
        // destructive operation failed when it did not. The worst case
        // without it is a stale "locked" answer for the 60-second cache
        // TTL, on an installation whose credentials are already gone.
        rescue(fn () => $this->lock->release(), report: false);

        Log::warning('Telephony provider reset completed.', [
            'from_provider' => $provider->value,
            'source' => $source,
            'by_user_id' => $actor?->id,
            'counts' => $reset->counts,
            'retired_phone_numbers' => $reset->retiredPhoneNumbers(),
        ]);

        return $reset;
    }

    /**
     * End everything a normal reset would have refused to run over, so that
     * a forced reset has nothing left to trip on.
     *
     * The order matters. Campaigns are stopped first: a campaign left
     * `running` has `campaigns:process-scheduled` behind it, dispatching a
     * fresh call every minute, and hanging calls up while that is still
     * dialling is a race nobody wins.
     *
     * Each hangup is attempted at the provider and is allowed to fail. A
     * call whose row says `in-progress` is very often a call that ended
     * long ago and whose final webhook never arrived — the provider will
     * answer "no such call", and that is a success for our purposes, not an
     * error. Either way the row is then closed locally, because the point
     * of the force is that the reset proceeds; an unreachable provider must
     * not leave the installation stuck in the state the admin asked to
     * leave.
     *
     * @return array{campaigns_stopped: int, calls_hung_up: int, calls_closed_locally: int}
     */
    private function endWorkInFlight(TelephonyProvider $provider): array
    {
        $stopped = DB::table('campaigns')
            ->where('status', 'running')
            ->whereNull('deleted_at')
            ->update(['status' => 'paused', 'updated_at' => now()]);

        // `scheduled` goes back to `draft` rather than `paused`: those are
        // the transitions Campaign::STATUS_TRANSITIONS allows from each, and
        // a forced reset should not be the one thing in the product that
        // leaves a campaign in a state its own state machine cannot reach.
        $stopped += DB::table('campaigns')
            ->where('status', 'scheduled')
            ->whereNull('deleted_at')
            ->update(['status' => 'draft', 'updated_at' => now()]);

        $hungUp = 0;

        foreach (Call::whereIn('status', self::BLOCKING_CALL_STATUSES)->cursor() as $call) {
            if (rescue(fn () => $this->endCall->execute($call), false, report: false)) {
                $hungUp++;
            }
        }

        // Whatever the provider said, nothing may still read as in flight.
        $closedLocally = DB::table('calls')
            ->whereIn('status', self::BLOCKING_CALL_STATUSES)
            ->update([
                'status' => 'canceled',
                'ended_at' => now(),
                'updated_at' => now(),
            ]);

        Log::warning('Telephony reset forced: work in flight was ended.', [
            'provider' => $provider->value,
            'campaigns_stopped' => $stopped,
            'calls_hung_up' => $hungUp,
            'calls_closed_locally' => $closedLocally,
        ]);

        return [
            'campaigns_stopped' => $stopped,
            'calls_hung_up' => $hungUp,
            'calls_closed_locally' => $closedLocally,
        ];
    }

    /**
     * Re-assert, inside the transaction and holding locks, everything that
     * could have changed since guardPreconditions() ran.
     *
     * `lockForUpdate()` is what makes this more than a second look. On
     * MySQL/InnoDB a locking read takes gap locks over the index range it
     * scans, so a concurrent transaction cannot insert a campaign into
     * `running`/`scheduled`, or a call into an in-flight status, until this
     * one finishes. On SQLite a write transaction holds the database
     * exclusively, which gets there by a blunter route. Laravel compiles
     * lockForUpdate() to nothing on SQLite, so it is harmless there.
     *
     * @throws TelephonyResetRefusedException
     */
    private function assertStillSafe(TelephonyProvider $provider, bool $force = false): void
    {
        $stillLocked = DB::table('telephony_providers')
            ->where('name', $provider->value)
            ->whereNotNull('locked_at')
            ->lockForUpdate()
            ->exists();

        if (! $stillLocked) {
            throw new TelephonyResetRefusedException(sprintf(
                'This installation is no longer locked to %s — another reset may have just run. Nothing was changed.',
                $provider->value,
            ));
        }

        // A forced reset has already ended the work in flight, and anything
        // that started in the moment since is being ended by this wipe
        // regardless. The lock check above still applies: two resets racing
        // must not both proceed.
        if ($force) {
            return;
        }

        $campaigns = DB::table('campaigns')
            ->whereIn('status', self::BLOCKING_CAMPAIGN_STATUSES)
            ->whereNull('deleted_at')
            ->lockForUpdate()
            ->count();

        if ($campaigns > 0) {
            throw new TelephonyResetRefusedException($this->campaignsRunningMessage($campaigns));
        }

        $calls = DB::table('calls')
            ->whereIn('status', self::BLOCKING_CALL_STATUSES)
            ->lockForUpdate()
            ->count();

        if ($calls > 0) {
            throw new TelephonyResetRefusedException($this->callsInFlightMessage($calls));
        }
    }

    /**
     * The phone numbers this reset is about to retire, captured before the
     * update so they can be named in the audit row.
     *
     * Every row is listed, including numbers already `released`: the point
     * of the list is "these are the numbers this installation stopped
     * tracking on this date", and a partial list would be worse than none
     * when an admin is working through their provider console deciding what
     * to cancel.
     *
     * @return list<array{id: int, number: string}>
     */
    private function phoneNumbersAboutToBeRetired(): array
    {
        return DB::table('phone_numbers')
            ->orderBy('id')
            ->get(['id', 'number'])
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'number' => (string) $row->number,
            ])
            ->all();
    }

    /**
     * @throws TelephonyResetRefusedException
     */
    private function guardPreconditions(string $confirmation, bool $force = false): TelephonyProvider
    {
        $provider = $this->lock->current();

        if ($provider === null) {
            throw new TelephonyResetRefusedException(
                'This installation is not locked to a telephony provider, so there is nothing to reset.'
            );
        }

        // Case- and whitespace-insensitive: the admin is shown "Twilio"
        // but the provider's name is "twilio", and refusing over a capital
        // letter or a trailing space teaches an admin to retype a
        // destructive confirmation until it works, which is the opposite
        // of what a typed confirmation is for.
        if (strcasecmp(trim($confirmation), $provider->value) !== 0) {
            throw new TelephonyResetRefusedException(sprintf(
                'The confirmation did not match. Type "%s" exactly to reset this installation\'s telephony provider.',
                $provider->value,
            ));
        }

        // A forced reset ends the work in flight instead of waiting for
        // it, so these two are not refusals any more. The two checks above
        // are never skipped: without a lock there is nothing to reset, and
        // the typed confirmation is the only thing standing between an
        // admin and a wipe, forced or not.
        if ($force) {
            return $provider;
        }

        $campaigns = DB::table('campaigns')
            ->whereIn('status', self::BLOCKING_CAMPAIGN_STATUSES)
            ->whereNull('deleted_at')
            ->count();

        if ($campaigns > 0) {
            throw new TelephonyResetRefusedException($this->campaignsRunningMessage($campaigns));
        }

        $calls = DB::table('calls')
            ->whereIn('status', self::BLOCKING_CALL_STATUSES)
            ->count();

        if ($calls > 0) {
            throw new TelephonyResetRefusedException($this->callsInFlightMessage($calls));
        }

        return $provider;
    }

    private function campaignsRunningMessage(int $campaigns): string
    {
        return sprintf(
            'Cannot reset telephony while %d campaign(s) are running or scheduled. '
            .'Pause or complete them first, then try again.',
            $campaigns,
        );
    }

    private function callsInFlightMessage(int $calls): string
    {
        return sprintf(
            'Cannot reset telephony while %d call(s) are still in progress. '
            .'Wait for them to finish, then try again.',
            $calls,
        );
    }

    /**
     * Strip every provider identifier from the phone numbers and mark them
     * released, keeping the rows.
     *
     * Mirrors what PhoneNumberService::releaseNumber() already writes when
     * a single number is given up (status, twilio_sid, user_id,
     * released_at), extended to the identifiers that service predates.
     *
     * `ai_agent_id` is cleared for the same reason `user_id` is, and it is
     * not cosmetic: the inbound SMS webhook routes a message to
     * `phone_numbers.ai_agent_id` while billing and provider credentials
     * come from `user_id`. Leaving the agent pointer on an unowned row
     * means that once a number sync revives the row and an admin assigns it
     * to a *different* customer, that customer's inbound messages are
     * answered by the previous tenant's AI agent. The number must belong to
     * nobody, and an agent is part of belonging.
     *
     * `provider` is deliberately left alone: the column is NOT NULL, and
     * its value is a historical fact ("this tombstone was a Twilio
     * number") rather than a pointer into an account. Nothing dials or
     * configures a released, unowned number, so keeping it costs nothing
     * and tells a later admin something true. `source` is left for the
     * same reason.
     */
    private function neutralisePhoneNumbers(): int
    {
        return DB::table('phone_numbers')->update([
            'status' => 'released',
            'user_id' => null,
            'released_at' => now(),
            'twilio_sid' => null,
            'provider_sid' => null,
            'provider_number_sid' => null,
            'account_sid' => null,
            'trunk_id' => null,
            'trunk_phone_number_id' => null,
            'ai_agent_id' => null,
            'updated_at' => now(),
        ]);
    }

    /**
     * Clear the denormalised "this user has Twilio set up" flag.
     *
     * `users.twilio_configured` is a cached answer to a question whose
     * source of truth — twilio_credentials — this reset has just emptied.
     * Leaving it true means the next provider inherits a flag that asserts
     * a configuration nobody has. NOT NULL, so cleared means false.
     */
    private function clearUserProviderFlags(): int
    {
        return DB::table('users')
            ->where('twilio_configured', true)
            ->update([
                'twilio_configured' => false,
                'updated_at' => now(),
            ]);
    }

    /**
     * Detach campaigns from the numbers they dialled from, without
     * touching the campaigns themselves.
     *
     * `from_number` is a copy of the chosen number's digits
     * (CampaignController writes `from_number = $phoneNumber->number`) and
     * is what MakeCampaignCallJob actually dials from, so it has to go
     * with the link. `caller_id` is left alone: it is admin-entered,
     * never derived from a phone number, and a verified caller identity
     * can reasonably outlive a provider switch.
     */
    private function detachCampaigns(): int
    {
        return DB::table('campaigns')
            ->where(function (Builder $query): void {
                $query->whereNotNull('phone_number_id')->orWhereNotNull('from_number');
            })
            ->update([
                'phone_number_id' => null,
                'from_number' => null,
                'updated_at' => now(),
            ]);
    }

    /**
     * Detach AI agents from their inbound number and its provider webhook.
     *
     * `twilio_auto_configured` is a NOT NULL boolean, so "cleared" here
     * means false, not null — the webhook it recorded no longer exists.
     */
    private function detachAiAgents(): int
    {
        return DB::table('ai_agents')
            ->where(function (Builder $query): void {
                $query->whereNotNull('phone_number')
                    ->orWhereNotNull('twilio_webhook_sid')
                    ->orWhere('twilio_auto_configured', true);
            })
            ->update([
                'phone_number' => null,
                'twilio_webhook_sid' => null,
                'twilio_auto_configured' => false,
                'updated_at' => now(),
            ]);
    }

    /**
     * Cancel number requests still waiting on a decision.
     *
     * A number request is a record of something a customer did and an
     * admin answered, so the rows stay. But a pending one is waiting for a
     * number to be bought from a provider this installation is leaving:
     * nobody can approve it any more, so it is cancelled rather than left
     * dangling. Requests already approved or rejected are finished history
     * and are not touched.
     */
    private function cancelPendingNumberRequests(): int
    {
        return DB::table('number_requests')
            ->where('status', 'pending')
            ->update([
                'status' => 'cancelled',
                'processed_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Wipe the credentials and provider-side identifiers from every
     * telephony_providers row, keeping the rows themselves.
     *
     * Every row, not just the locked one: an installation can hold saved
     * credentials for the provider it is not locked to (the admin
     * credentials page writes them before locking), and a reset that left
     * those behind would hand the next provider a set of secrets the
     * customer believed they had wiped.
     *
     * `settings` is cleared for the same reason `app_id` is: Plivo's
     * provisioner (PlivoApplicationProvisioner::ensureFor()) snapshots the
     * URLs it last applied there, purely so the admin settings page can
     * notice a later domain change without calling Plivo — that snapshot
     * is provider state exactly like the id it sits next to, and leaving
     * it behind would hand the next provider a stale comparison baseline
     * instead of the clean slate a reset promises.
     *
     * `locked_at` is not cleared here — TelephonyLock::release() owns that
     * column and is the only race-safe writer for it.
     */
    private function clearProviderCredentials(): int
    {
        return DB::table('telephony_providers')->update([
            'credentials' => null,
            'app_id' => null,
            'webhook_url' => null,
            'settings' => null,
            'verified_at' => null,
            'updated_at' => now(),
        ]);
    }
}
