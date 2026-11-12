<?php

namespace App\Http\Middleware;

use App\Actions\Telephony\IssueSoftphoneTokenAction;
use App\Helpers\AppHelper;
use App\Models\KycSetting;
use App\Models\Language;
use App\Models\PhoneNumber;
use App\Models\TrunkPhoneNumber;
use App\Services\ExchangeRateService;
use App\Services\Telephony\TelephonyLock;
use App\Services\Telephony\TelephonyManager;
use App\Services\TranslationService;
use App\Support\Installation;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        // During installation, the database may not be available yet.
        // Return minimal shared data to avoid crashing on install pages.
        $isInstalling = ! Installation::isInstalled();
        if ($isInstalling && $request->is('install*')) {
            return [
                ...parent::share($request),
                'name' => AppHelper::getAppName(),
                'appName' => AppHelper::getAppName(),
                'appLogo' => config('app.logo'),
                'quote' => ['message' => '', 'author' => ''],
                'auth' => [
                    'user' => null,
                    'kyc' => null,
                    'isImpersonating' => false,
                ],
                'kycEnabled' => false,
                'sidebarOpen' => true,
                'csrf_token' => csrf_token(),
                'flash' => [
                    'success' => $request->session()->get('success'),
                    'error' => $request->session()->get('error'),
                    'warning' => $request->session()->get('warning'),
                    'info' => $request->session()->get('info'),
                    'message' => $request->session()->get('flash.message') ?? $request->session()->get('message'),
                ],
                'locale' => config('app.locale', 'en'),
                // No translations table yet: serve the shipped JSON so the
                // wizard shows real text instead of raw "install.*" keys.
                'translations' => fn () => $this->fileTranslations(config('app.locale', 'en')),
                'activeLanguages' => fn () => [],
                'telephony' => fn () => rescue(fn () => $this->telephonyProps(), ['provider' => 'twilio', 'locked' => false, 'hidden' => []], false),
            ];
        }

        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        $user = $request->user();

        // Prepare user data with permissions if authenticated
        $userData = null;
        $kycData = null;

        if ($user) {
            $user->load('roles', 'kycVerification');
            $userData = array_merge(
                $user->toArray(),
                ['permissions' => $user->permissions()->values()->all()]
            );

            // Add KYC information for all non-admin users
            if (! $user->hasRole('admin') && $user->kycVerification) {
                $kyc = $user->kycVerification;
                $diffInDays = $user->created_at->diffInDays(now());
                $daysRemaining = max(0, ceil(7 - $diffInDays));

                $kycData = [
                    'tier' => $kyc->kyc_tier,
                    'status' => $kyc->status,
                    'is_unverified' => $user->isKycUnverified(),
                    'grace_period_ends_at' => $user->created_at->addDays(7)->toDateTimeString(),
                    'days_remaining' => (int) $daysRemaining,
                    'is_grace_period_expired' => $diffInDays >= 7,
                    'needs_renewal' => $kyc->needsRenewal(),
                    'expires_at' => $kyc->kyc_verified_at?->addYears(2)->toDateTimeString(),
                    'limits' => $user->getKycLimits(),
                ];
            } elseif (! $user->hasRole('admin') && ! $user->kycVerification) {
                // User has no KYC record yet (new user)
                $diffInDays = $user->created_at->diffInDays(now());
                $daysRemaining = max(0, ceil(7 - $diffInDays));

                $kycData = [
                    'tier' => 'unverified',
                    'status' => null,
                    'is_unverified' => true,
                    'grace_period_ends_at' => $user->created_at->addDays(7)->toDateTimeString(),
                    'days_remaining' => (int) $daysRemaining,
                    'is_grace_period_expired' => $diffInDays >= 7,
                    'needs_renewal' => false,
                    'expires_at' => null,
                    'limits' => $user->getKycLimits(),
                ];
            }
        }

        return [
            ...parent::share($request),
            'name' => AppHelper::getAppName(),
            'appName' => AppHelper::getAppName(),
            'appLogo' => AppHelper::getLogoUrl() ?? config('app.logo'),
            'brand' => AppHelper::getBrandConfig(),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $userData,
                'kyc' => $kycData,
                'isImpersonating' => $request->session()->has('impersonator'),
            ],
            'kycEnabled' => KycSetting::get('kyc_enabled', true),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'csrf_token' => csrf_token(), // Always provide fresh CSRF token
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'info' => $request->session()->get('info'),
            ],
            // Softphone configuration (lazy-loaded for the global softphone widget)
            'softphone' => fn () => $user ? [
                // Whether the provider this installation is locked to has
                // the account configuration a softphone needs. This gate
                // disables the dialler in the UI, so asking
                // TwilioGlobalConfig here left every Plivo installation's
                // softphone permanently switched off. IssueSoftphoneTokenAction
                // is the one answer both providers share, and the same one
                // /api/telephony/status reports.
                'isConfigured' => app(IssueSoftphoneTokenAction::class)->isConfigured(),
                'hasAssignedNumbers' => PhoneNumber::where('user_id', $user->id)
                    ->where('status', 'assigned')
                    ->exists()
                    || TrunkPhoneNumber::where('user_id', $user->id)
                        ->where('status', 'active')
                        ->where('assigned_to', 'softphone')
                        ->exists(),
            ] : null,
            // Telephony provider lock: which provider (twilio/plivo) this
            // installation is locked to, and which Twilio-only features to
            // hide for it. rescue()'d the same way SetLocale guards its own
            // pre-install database read — see telephonyProps() below for why
            // this is a second, cheap safety net rather than the only one.
            'telephony' => fn () => rescue(fn () => $this->telephonyProps(), ['provider' => 'twilio', 'locked' => false, 'hidden' => []], false),
            // Turnstile
            'turnstile' => [
                'siteKey' => config('services.turnstile.site_key') ?: '',
                'enabled' => (bool) config('services.turnstile.enabled', false),
            ],
            // Currency
            'baseCurrency' => config('services.base_currency', 'USD'),
            'baseCurrencySymbol' => ExchangeRateService::CURRENCY_SYMBOLS[config('services.base_currency', 'USD')] ?? config('services.base_currency', 'USD'),
            // i18n
            'locale' => app()->getLocale(),
            'translations' => fn () => app(TranslationService::class)
                ->getFlatTranslationsForLocale(app()->getLocale()),
            'activeLanguages' => fn () => Cache::remember('active_languages', 3600, fn () => Language::active()
                ->orderBy('sort_order')
                ->get(['id', 'code', 'name', 'native_name', 'flag', 'is_rtl', 'is_default'])
                ->toArray()
            ),
        ];
    }

    /**
     * Which telephony provider this installation is locked to (or the
     * unlocked default), and which Twilio-only features to hide for it.
     *
     * TelephonyLock::current() already rescues its own cache/database read
     * down to null (a missing telephony_providers table — pre-install, or
     * a fresh migration mid-run — degrades to "not locked", not a crash),
     * the same way SetLocale guards its own pre-install read, and
     * TelephonyManager::provider() resolves the display value the same
     * safe way — tryFrom(), never the throwing from() — rather than this
     * method re-deriving it from config() itself. So the rescue() wrapped
     * around the call to this method by both callers above is not
     * covering for a fragile dependency; it is the same cheap, blanket
     * defense-in-depth every other shared prop in this method would want
     * if it started touching the database.
     *
     * `provider` and `hidden` deliberately do NOT share one fallback.
     * `provider` delegates to TelephonyManager::provider(), which falls
     * back to config('telephony.default') (then to Twilio) so an unlocked
     * install still has something sensible to display (e.g. the installer
     * chooser's starting point) — but `hidden` must reflect only an
     * *actual* lock. An unlocked installation — a fresh install whose
     * wizard never finished, or what a destructive provider reset leaves
     * behind — hides nothing, full stop, matching
     * EnsureTelephonyFeature's identical rule for the route-level gate: if
     * `hidden` inherited the config-default fallback instead, a stale or
     * merely-unrewritten TELEPHONY_PROVIDER env value could hide sidebar
     * entries that middleware still serves at 200 — exactly the drift
     * between the two layers this prop exists to prevent. So `hidden`
     * reads $locked directly rather than through TelephonyManager, which
     * always resolves to *some* provider and has no notion of "unlocked".
     *
     * @return array{provider: string, locked: bool, hidden: array<int, string>}
     */
    private function telephonyProps(): array
    {
        $locked = app(TelephonyLock::class)->current();
        $provider = app(TelephonyManager::class)->provider();

        return [
            'provider' => $provider->value,
            'locked' => $locked !== null,
            'hidden' => $locked?->hiddenFeatures() ?? [],
        ];
    }

    /**
     * Flat translations from lang/{locale}.json (falling back to English).
     * Used only while installing, when the database-backed catalogue does
     * not exist.
     *
     * @return array<string, string>
     */
    private function fileTranslations(string $locale): array
    {
        foreach (array_unique([$locale, 'en']) as $candidate) {
            $path = lang_path($candidate.'.json');

            if (is_file($path)) {
                $decoded = json_decode((string) file_get_contents($path), true);

                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        return [];
    }
}
