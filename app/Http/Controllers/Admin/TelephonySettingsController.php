<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Telephony\LockTelephonyProviderAction;
use App\Actions\Telephony\ResetTelephonyProviderAction;
use App\Enums\TelephonyProvider;
use App\Exceptions\Telephony\AlreadyLockedException;
use App\Exceptions\Telephony\TelephonyResetRefusedException;
use App\Http\Controllers\Controller;
use App\Models\TelephonyProviderConfig;
use App\Models\TelephonyProviderReset;
use App\Services\Telephony\Plivo\PlivoApplicationProvisioner;
use App\Services\Telephony\Plivo\PlivoVoiceProvider;
use App\Services\Telephony\TelephonyLock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The one admin page for the installation's locked telephony provider:
 * which provider it is, and — for Plivo — the credentials for it.
 *
 * Deliberately NOT feature-gated behind the lock: TelephonyLock::current()
 * can be Twilio, Plivo, or null (no lock at all — what a destructive
 * provider reset leaves behind, or a fresh install whose installer never
 * finished). index() renders something sensible for all three; see its
 * docblock.
 *
 * Only Plivo has credentials to manage here. Twilio's already live at
 * /settings/twilio (TwilioController) — this page links to it rather
 * than rebuilding it.
 *
 * This controller also owns the two halves of changing that choice:
 * reset() wipes the current provider and opens the lock, and
 * choose()/lockProvider() let the admin pick the next one. The chooser is
 * an admin route rendering the same component the installer's provider
 * step uses — never the installer route itself, which an installed
 * application must not be able to re-enter.
 */
final class TelephonySettingsController extends Controller
{
    public function __construct(
        private readonly TelephonyLock $lock,
        private readonly PlivoVoiceProvider $plivoVoiceProvider,
        private readonly PlivoApplicationProvisioner $plivoApplicationProvisioner,
    ) {}

    /**
     * Renders one of three branches, chosen purely by reading the lock:
     *
     *  - Twilio: a pointer to the existing Twilio settings page.
     *  - Plivo: the credential form + current (non-secret) state.
     *  - No lock: a neutral explanation, no form — this page cannot know
     *    which provider's fields to show, and must not guess or default
     *    to one, since that would be this page silently making the
     *    choice the installer (or a future reset flow) owns.
     */
    public function index(): Response
    {
        $provider = $this->lock->current();

        return Inertia::render('Admin/Telephony/Index', [
            'provider' => $provider?->value,
            'providerLabel' => $provider?->label(),
            'locked' => $provider !== null,
            'twilio' => $provider === TelephonyProvider::TWILIO ? [
                'settingsUrl' => route('twilio.settings'),
            ] : null,
            'plivo' => $provider === TelephonyProvider::PLIVO ? $this->plivoProps() : null,
            // Where an admin goes to pick a provider when none is locked.
            // Sent as a prop rather than hardcoded in the page so this
            // controller stays the single owner of the chooser's location.
            'chooseUrl' => $provider === null ? route('admin.telephony.choose') : null,
            // The word the admin must type to arm the danger zone. Sent
            // from the server so the button and the check agree about what
            // this installation is actually locked to, rather than the
            // browser inferring it from a label.
            'resetConfirmationWord' => $provider?->value,
        ]);
    }

    /**
     * Wipe this installation's telephony configuration and open the lock.
     *
     * The acting user comes from the authenticated session, never from
     * request input: "who ran the most destructive operation in the
     * product" must not be something the request can assert. Every
     * precondition failure comes back as a readable flash error on the
     * page the admin was already on, with nothing written.
     *
     * The route also sits behind `password.confirm`. Reading the system
     * configuration page already required re-entering the password; the one
     * operation that destroys a customer's telephony setup asking for less
     * than that was backwards, and it is the cheapest real protection
     * against both an accidental click and an unattended session.
     */
    public function reset(Request $request, ResetTelephonyProviderAction $reset): RedirectResponse
    {
        $validated = $request->validate([
            'confirmation' => ['required', 'string', 'max:191'],
            // Ends the calls and campaigns that would otherwise refuse the
            // reset, instead of waiting for them. The typed confirmation is
            // still required — this changes what stands in the way, not how
            // deliberate the admin has to be.
            'force' => ['sometimes', 'boolean'],
        ]);

        try {
            $reset->execute(
                $request->user(),
                $validated['confirmation'],
                TelephonyProviderReset::SOURCE_WEB,
                (bool) ($validated['force'] ?? false),
            );
        } catch (TelephonyResetRefusedException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.telephony.choose')
            ->with('success', 'Telephony has been reset. Choose the provider this installation should use from now on.');
    }

    /**
     * The post-reset provider chooser.
     *
     * Renders the same component as the installer's provider step, at an
     * admin route: an installed application can never re-enter the install
     * wizard (EnsureNotInstalled forbids exactly that), so reusing the
     * installer's route was not an option.
     *
     * Only reachable while the lock is open. With a provider still locked
     * there is no choice to make, and showing a chooser that cannot
     * actually change anything would imply otherwise.
     */
    public function choose(): Response|RedirectResponse
    {
        if ($this->lock->isLocked()) {
            return redirect()->route('admin.telephony.index');
        }

        return Inertia::render('Admin/Telephony/Choose', [
            'provider' => TelephonyProvider::TWILIO->value,
            'providers' => array_map(
                fn (TelephonyProvider $p): array => ['value' => $p->value, 'label' => $p->label()],
                TelephonyProvider::cases(),
            ),
            // Where the chosen provider is submitted. A prop for the same
            // reason index()'s chooseUrl is one, and the installer's
            // telephony step passes its own submitUrl: this controller is
            // the single owner of these locations, so the page must not
            // re-spell one and quietly disagree with the route table.
            'submitUrl' => route('admin.telephony.choose.lock'),
        ]);
    }

    /**
     * Lock the installation to the provider chosen after a reset.
     *
     * Refuses if something is already locked rather than silently
     * succeeding: reaching here with a lock in place means the admin
     * submitted a stale form, and the honest answer is that the choice has
     * already been made.
     */
    public function lockProvider(Request $request, LockTelephonyProviderAction $lockProvider): RedirectResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', Rule::in(array_column(TelephonyProvider::cases(), 'value'))],
        ]);

        if ($this->lock->isLocked()) {
            return redirect()->route('admin.telephony.index')
                ->with('error', 'This installation is already locked to a telephony provider.');
        }

        $provider = TelephonyProvider::from($validated['provider']);

        try {
            $lockProvider->execute($provider, $request->user());
        } catch (AlreadyLockedException $e) {
            return redirect()->route('admin.telephony.index')->with('error', $e->getMessage());
        } catch (LockTimeoutException) {
            // TelephonyLock serialises every lock() behind a cache mutex
            // and gives up after a few seconds of waiting — reachable when
            // two tabs submit this chooser at the same moment. Nothing was
            // written, so the honest answer is to send the admin back to
            // the chooser and ask them to submit again rather than let a
            // contended mutex surface as a raw 500. Returned as a
            // 'provider' error because that is what the chooser renders;
            // this page shows no flash message.
            return redirect()->route('admin.telephony.choose')->withErrors([
                'provider' => 'Another request is still finishing this step. Please try again.',
            ]);
        }

        return redirect()->route('admin.telephony.index')
            ->with('success', "This installation is now locked to {$provider->label()}. Add its credentials to start calling.");
    }

    /**
     * Persists Plivo's auth_id/auth_token into the encrypted `credentials`
     * column. Both fields are required on every submit — like the Twilio
     * form this mirrors, the token is never echoed back to the browser to
     * pre-fill, so there is nothing to "leave blank to keep the old one".
     *
     * Always clears a previous verified_at. The alternative — diffing the
     * incoming values against the stored ones and only clearing when they
     * differ — saves a re-verify click on an identical re-save, but adds
     * a comparison that must itself never leak the token (e.g. via timing
     * or a stray log line) for a marginal gain. Unconditional clearing is
     * simple, obviously correct, and matches the actual risk: once a save
     * happens, "verified" must mean "verified since this exact save", not
     * "verified at some point for whatever used to be stored".
     */
    public function storeCredentials(Request $request): RedirectResponse
    {
        if (! $this->isPlivoLocked()) {
            return back()->with('error', 'This installation is not locked to Plivo.');
        }

        // Trim before validating, not after. In THIS app that duplicates a
        // protection that already exists one layer up: Laravel's default
        // global middleware stack runs TrimStrings then
        // ConvertEmptyStringsToNull on every request (see
        // Illuminate\Foundation\Configuration\Middleware, which this app's
        // bootstrap/app.php does not remove or reconfigure for either
        // class), so a whitespace-only submission is already '' by the
        // time it reaches this method, and already null by the time
        // `required` sees it — confirmed empirically: reverting this block
        // still leaves both trim-order tests below green, because the
        // upstream middleware already did the job. This block is kept
        // anyway as a deliberate, local belt-and-suspenders: this method's
        // correctness should not depend on global middleware configuration
        // it does not own and that a later, unrelated change (e.g. adding
        // 'auth_token' to TrimStrings::except() for some other form) could
        // silently disable for this one. Trimming first also means a
        // whitespace-only value collapses to '' before `required` ever
        // sees it, so it is rejected as a normal validation error rather
        // than reaching storage as a blank credential.
        $request->merge([
            'auth_id' => $this->trimmedInput($request, 'auth_id'),
            'auth_token' => $this->trimmedInput($request, 'auth_token'),
        ]);

        $validated = $request->validate([
            'auth_id' => ['required', 'string', 'max:191'],
            'auth_token' => ['required', 'string', 'max:191'],
        ]);

        TelephonyProviderConfig::forProvider(TelephonyProvider::PLIVO)->update([
            'credentials' => [
                'auth_id' => $validated['auth_id'],
                'auth_token' => $validated['auth_token'],
            ],
            'verified_at' => null,
        ]);

        return back()->with('success', 'Plivo credentials saved. Verify them to confirm they work.');
    }

    /**
     * Fetches the Plivo account over the API using the credentials already
     * saved — this endpoint takes no input of its own, so there is no
     * request-supplied credential that could ever diverge from what was
     * stored. On success, provisions this installation's Plivo
     * Application (PlivoApplicationProvisioner) and only then stamps
     * verified_at via markVerified(); on failure of either step, surfaces
     * a readable reason without ever repeating the token back — see
     * redactToken().
     *
     * Provisioning deliberately lives here and not in storeCredentials():
     * this is the one place that already knows the credentials work, so
     * it is the only place that can attempt to provision without risking
     * a confusing double failure (bad credentials AND an unreachable
     * API). It also means "verified" can keep meaning exactly one thing —
     * this installation has a live, correctly-pointed Application behind
     * it — rather than two things that can drift apart. An admin who
     * saves credentials and never presses verify simply has nothing
     * provisioned yet, which is an honest, single state, not a
     * half-configured one; the alternative (provisioning on every save)
     * would risk exactly that half state the moment provisioning failed
     * on a save nobody was watching for a provider-side error.
     *
     * Reuses PlivoVoiceProvider::verifyCredentials() rather than calling
     * the Plivo SDK directly: that method already wraps the SDK call in a
     * try/catch that never throws outward, so this action does not need
     * its own. PlivoApplicationProvisioner::ensureFor() offers the same
     * guarantee for provisioning — and, unlike a bare ensure() call here,
     * does the read-provision-persist sequence atomically, so two
     * verify() requests arriving close together cannot both create an
     * Application. See that method's own docblock.
     */
    public function verify(Request $request): RedirectResponse
    {
        if (! $this->isPlivoLocked()) {
            return back()->with('error', 'This installation is not locked to Plivo.');
        }

        $config = TelephonyProviderConfig::forProvider(TelephonyProvider::PLIVO);
        $authId = $config->credential('auth_id');
        $authToken = $config->credential('auth_token');

        if ($authId === null || $authToken === null) {
            return back()->with('error', 'Save your Plivo Auth ID and Auth Token before verifying.');
        }

        $result = $this->plivoVoiceProvider->verifyCredentials([
            'auth_id' => $authId,
            'auth_token' => $authToken,
        ]);

        if ($result['valid'] !== true) {
            $reason = $this->redactToken($result['error'] ?? 'Unknown error.', $authToken);

            return back()->with('error', "Plivo verification failed: {$reason}");
        }

        $provisioned = $this->plivoApplicationProvisioner
            ->initialize(['auth_id' => $authId, 'auth_token' => $authToken])
            ->ensureFor($config);

        if ($provisioned['success'] !== true) {
            $reason = $this->redactToken($provisioned['error'] ?? 'Unknown error.', $authToken);

            return back()->with('error', "Plivo credentials are valid, but the Plivo Application that receives inbound calls and messages could not be set up: {$reason} Your credentials are still saved — try verifying again.");
        }

        $config->markVerified();

        $message = 'Plivo credentials verified successfully.';
        if (! empty($result['account_name'])) {
            $message .= " Connected account: {$result['account_name']}.";
        }

        return back()->with('success', $message);
    }

    private function isPlivoLocked(): bool
    {
        return $this->lock->current() === TelephonyProvider::PLIVO;
    }

    /**
     * The request's raw value for $key, trimmed if it is a string. Anything
     * else (missing, array, ...) passes through unchanged so the normal
     * `string` validation rule rejects it with a readable error instead of
     * this helper fatal-erroring on a non-string trim().
     */
    private function trimmedInput(Request $request, string $key): mixed
    {
        $value = $request->input($key);

        return is_string($value) ? trim($value) : $value;
    }

    /**
     * urlsOutOfDate compares a snapshot taken at the last successful
     * provisioning (PlivoApplicationProvisioner::ensureFor() writes it
     * into $config->settings) against what this installation would
     * provision right now — both purely local reads, no call to Plivo.
     * It is false whenever nothing has been provisioned yet (never true
     * just because the admin hasn't verified), and becomes true the
     * moment this installation's own address diverges from the one
     * Plivo was last told about — e.g. moving off a temporary domain
     * onto the real one, which otherwise leaves a green "Verified" badge
     * sitting on top of an Application that can no longer reach this
     * installation. Self-heals the next time verify() succeeds; this is
     * only what makes that gap visible before a real call or message
     * quietly fails to route.
     *
     * @return array{authId: ?string, hasAuthToken: bool, verifiedAt: ?string, applicationId: ?string, urlsOutOfDate: bool}
     */
    private function plivoProps(): array
    {
        $config = TelephonyProviderConfig::forProvider(TelephonyProvider::PLIVO);
        $provisionedUrls = $config->settings['provisioned_urls'] ?? null;

        return [
            'authId' => $config->credential('auth_id'),
            'hasAuthToken' => $config->credential('auth_token') !== null,
            'verifiedAt' => $config->verified_at?->toDateTimeString(),
            'applicationId' => $config->app_id,
            'urlsOutOfDate' => $provisionedUrls !== null
                && $provisionedUrls !== $this->plivoApplicationProvisioner->currentUrls(),
        ];
    }

    /**
     * Belt-and-suspenders against the token ever reaching the browser via
     * an error message. Plivo's own error responses are built from the
     * API's JSON body (see PlivoResponseException), not an echo of the
     * request that was sent, so this should never actually trigger — but
     * "must never leak" is not a property this page may take on trust
     * from a dependency it does not control.
     */
    private function redactToken(string $message, string $authToken): string
    {
        return str_replace($authToken, '[redacted]', $message);
    }
}
