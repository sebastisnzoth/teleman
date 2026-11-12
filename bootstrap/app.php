<?php

use App\Actions\Campaigns\LaunchCampaignAction;
use App\Http\Middleware\BackupEnvDaily;
use App\Http\Middleware\CheckInstalled;
use App\Http\Middleware\CheckKycTier;
use App\Http\Middleware\CheckOwnership;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EnsureTelephonyFeature;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\ThrottleApiRequests;
use App\Http\Middleware\TrustHosts;
use App\Http\Middleware\UpdateAppUrl;
use App\Http\Middleware\ValidatePlivoSignature;
use App\Http\Middleware\ValidateTwilioSignature;
use App\Jobs\ProcessCampaignJob;
use App\Models\Campaign;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        apiPrefix: 'api',
        then: function () {
            Route::middleware('web')
                ->group(base_path('routes/install.php'));

            Route::middleware('web')
                ->group(base_path('routes/kyc.php'));
        }
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Honour X-Forwarded-* from the load balancer / CDN. Without this the
        // app sees "http" behind an HTTPS proxy, which breaks generated URLs
        // (and used to trigger a permanent APP_URL/.env rewrite loop).
        $middleware->trustProxies(at: '*');

        // ...which is exactly why the host a request names must be checked:
        // without this, X-Forwarded-Host decided where reset links pointed.
        $middleware->prepend(TrustHosts::class);

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->validateCsrfTokens(except: [
            'webhooks/twilio/*',
            'webhooks/plivo/*',
            'webhooks/payment/*',
            'twiml/*',
            'plivo-xml/*',
            'sip/trunk/*', // SIP trunk webhooks from Twilio
            'byoc/trunk/*', // BYOC trunk webhooks from Twilio
            'api/sms/webhook/*', // SMS provider webhooks
            'sanctum/csrf-cookie', // Allow this endpoint to set CSRF cookie
        ]);

        // Ensure stateful API
        $middleware->statefulApi();

        // BackupEnvDaily runs first so a snapshot is always taken before any
        // other middleware can touch .env.
        $middleware->web(prepend: [
            BackupEnvDaily::class,
            CheckInstalled::class,
            UpdateAppUrl::class,
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            SetLocale::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::using(50),
        ]);

        // Register role and permission middleware aliases
        $middleware->alias([
            'role' => CheckRole::class,
            'permission' => CheckPermission::class,
            'ownership' => CheckOwnership::class,
            'throttle.api' => ThrottleApiRequests::class,
            'kyc' => CheckKycTier::class,
            'twilio.signature' => ValidateTwilioSignature::class,
            'plivo.signature' => ValidatePlivoSignature::class,
            'telephony.feature' => EnsureTelephonyFeature::class,
        ]);

        // Putting 'plivo.signature' first in a route group's own
        // middleware array does NOT make it run first: Laravel resolves
        // the actual order via $middlewarePriority (Kernel's hardcoded
        // default list — StartSession, ThrottleRequests,
        // SubstituteBindings, etc.), which SortedMiddleware applies to
        // the *entire* merged stack regardless of each middleware's
        // position in its own group's array. A class absent from that
        // list keeps its natural (late) position while everything
        // present gets hoisted ahead of it — confirmed by dumping the
        // resolved middleware for webhooks/plivo/calls/{id}/status: 14
        // middleware ran before ValidatePlivoSignature, including
        // ThrottleRequests, StartSession and SubstituteBindings, even
        // though the route's own array lists plivo.signature first.
        // Prepending it here, ahead of the very first entry in the
        // default priority list, makes it actually run before all of
        // them. Twilio's ValidateTwilioSignature has the identical
        // pre-existing ordering (not fixed here — it is unrelated to
        // this task and changing it would not be a no-op for a live
        // Twilio installation).
        $middleware->prependToPriorityList(
            before: HandlePrecognitiveRequests::class,
            prepend: ValidatePlivoSignature::class,
        );
    })
    ->withSchedule(function (Schedule $schedule) {
        // Auto-launch scheduled campaigns when their time arrives
        $schedule->call(function () {
            $campaigns = Campaign::where('status', 'scheduled')
                ->where('scheduled_at', '<=', now())
                ->whereNull('started_at')
                ->get();

            foreach ($campaigns as $campaign) {
                try {
                    app(LaunchCampaignAction::class)->execute($campaign);
                    Log::info("Auto-launched scheduled campaign: {$campaign->id}");
                } catch (Exception $e) {
                    Log::error("Failed to auto-launch campaign {$campaign->id}: {$e->getMessage()}");
                }
            }
        })->everyMinute()->name('launch-scheduled-campaigns');

        // Process running campaigns every minute
        $schedule->call(function () {
            $campaigns = Campaign::where('status', 'running')->get();
            foreach ($campaigns as $campaign) {
                ProcessCampaignJob::dispatch($campaign);
            }
        })->everyMinute()->name('process-campaigns');

        // Sync available Twilio numbers daily at 3 AM
        $schedule->command('twilio:sync-numbers')->dailyAt('03:00');

        // phone-numbers:charge-monthly is scheduled once, in routes/console.php.
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Merges into the framework's own default (current_password,
        // password, password_confirmation) — see
        // Illuminate\Foundation\Exceptions\Handler::dontFlash(), which
        // array_merge()s rather than replaces. Without this, a validation
        // failure on either field (e.g. an admin pasting an oversized
        // token, tripping `max:191` after `required` already passed)
        // flashes the raw value into the session's "old input" via the
        // framework's default withInput() handling. Session storage is not
        // the same protection as the `credentials` column's
        // `encrypted:array` cast: this app ships SESSION_DRIVER=database
        // with SESSION_ENCRYPT=false by default, so an un-added credential
        // field would sit in plaintext in the sessions table until the
        // flash aged out. TelephonySettingsController and
        // Settings\TwilioController both validate a field literally named
        // auth_token; neither controller's Inertia page pre-fills a form
        // from old() input (confirmed: this codebase has no old() usage
        // anywhere — it is an Inertia SPA, not server-rendered Blade forms),
        // so excluding these two names from flashing changes no visible
        // behavior for either page.
        $exceptions->dontFlash([
            'auth_id',
            'auth_token',
        ]);

        // Only integrate Sentry if the package is installed
        if (class_exists(Integration::class)) {
            Integration::handles($exceptions);
        }
    })->create();
