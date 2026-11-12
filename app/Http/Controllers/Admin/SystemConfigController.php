<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\AppHelper;
use App\Http\Controllers\Controller;
use App\Models\ThemeSetting;
use App\Services\ExchangeRateService;
use App\Support\EnvWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class SystemConfigController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/SystemConfig/Index', [
            'config' => $this->getConfig(),
        ]);
    }

    public function update(Request $request)
    {
        // Never log the payload itself — it carries API secrets and mail
        // passwords in cleartext. Key names only.
        \Log::info('System Config Update Request', [
            'section' => $request->section,
            'config_keys' => array_keys((array) $request->config),
        ]);

        $request->validate([
            'section' => 'required|in:stripe,razorpay,paystack,paypal,flutterwave,cryptomus,mail,application,currency,turnstile',
            'config' => 'required|array',
            'logo' => 'nullable|image|mimes:png,jpg,jpeg,svg|max:2048',
            'logo_dark' => 'nullable|image|mimes:png,jpg,jpeg,svg|max:2048',
            'favicon' => 'nullable|image|mimes:png,jpg,jpeg,ico|max:1024',
        ]);

        $section = $request->section;

        try {
            switch ($section) {
                case 'stripe':
                    $this->updateStripeConfig($request->config);
                    break;
                case 'razorpay':
                    $this->updateRazorpayConfig($request->config);
                    break;
                case 'paystack':
                    $this->updatePaystackConfig($request->config);
                    break;
                case 'paypal':
                    $this->updatePayPalConfig($request->config);
                    break;
                case 'flutterwave':
                    $this->updateFlutterwaveConfig($request->config);
                    break;
                case 'cryptomus':
                    $this->updateCryptomusConfig($request->config);
                    break;
                case 'mail':
                    $this->updateMailConfig($request->config);
                    break;
                case 'currency':
                    $this->updateCurrencyConfig($request->config);
                    break;
                case 'turnstile':
                    $this->updateTurnstileConfig($request->config);
                    break;
                case 'application':
                    $this->updateApplicationConfig($request->config, $request->file('logo'), $request->file('favicon'), $request->file('logo_dark'));
                    // Clear all caches when app name or logo changes
                    Cache::flush();
                    Artisan::call('view:clear');
                    Artisan::call('config:clear');
                    break;
            }

            // Clear config cache
            Artisan::call('config:clear');

            \Log::info('System Config Updated Successfully', ['section' => $section]);

            return back()->with('success', ucfirst($section).' configuration updated successfully.');
        } catch (\Exception $e) {
            \Log::error('System Config Update Failed', [
                'section' => $section,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', 'Failed to update configuration: '.$e->getMessage());
        }
    }

    private function getConfig()
    {
        return [
            'stripe' => [
                'key' => config('services.stripe.public') ?? '',
                'secret' => config('services.stripe.secret') ? '••••••••' : '',
                'webhook_secret' => config('services.stripe.webhook_secret') ? '••••••••' : '',
            ],
            'razorpay' => [
                'key' => config('services.razorpay.api_key') ?? '',
                'secret' => config('services.razorpay.secret_key') ? '••••••••' : '',
                'webhook_secret' => config('services.razorpay.webhook_secret') ? '••••••••' : '',
                'test_mode' => config('services.razorpay.test_mode', true),
            ],
            'paystack' => [
                'public_key' => config('services.paystack.public_key') ?? '',
                'secret_key' => config('services.paystack.secret_key') ? '••••••••' : '',
                'test_mode' => config('services.paystack.test_mode', true),
            ],
            'paypal' => [
                'client_id' => config('services.paypal.client_id') ?? '',
                'client_secret' => config('services.paypal.client_secret') ? '••••••••' : '',
                'webhook_id' => config('services.paypal.webhook_id') ? '••••••••' : '',
                'test_mode' => config('services.paypal.test_mode', true),
            ],
            'flutterwave' => [
                'public_key' => config('services.flutterwave.public_key') ?? '',
                'secret_key' => config('services.flutterwave.secret_key') ? '••••••••' : '',
                'encryption_key' => config('services.flutterwave.encryption_key') ? '••••••••' : '',
                'secret_hash' => config('services.flutterwave.secret_hash') ? '••••••••' : '',
                'test_mode' => config('services.flutterwave.test_mode', true),
            ],
            'cryptomus' => [
                'merchant_uuid' => config('services.cryptomus.merchant_uuid') ?? '',
                'payment_key' => config('services.cryptomus.payment_key') ? '••••••••' : '',
                'test_mode' => config('services.cryptomus.test_mode', true),
            ],
            'mail' => [
                'mailer' => config('mail.default') ?? 'log',
                'host' => config('mail.mailers.smtp.host') ?? '127.0.0.1',
                'port' => config('mail.mailers.smtp.port') ?? 2525,
                'username' => config('mail.mailers.smtp.username') ?? 'null',
                'password' => config('mail.mailers.smtp.password') ? '••••••••' : '',
                'encryption' => config('mail.mailers.smtp.encryption') ?? 'tls',
                'from_address' => config('mail.from.address') ?? 'hello@example.com',
                'from_name' => config('mail.from.name') ?? config('app.name'),
            ],
            'currency' => [
                'base_currency' => config('services.base_currency', 'USD'),
                'exchange_rate_mode' => config('services.exchange_rate_mode', 'auto'),
                'manual_rates' => app(ExchangeRateService::class)->getManualRates(),
            ],
            'turnstile' => [
                'site_key' => config('services.turnstile.site_key') ?? '',
                'secret_key' => config('services.turnstile.secret_key') ? '••••••••' : '',
                'enabled' => (bool) config('services.turnstile.enabled', false),
            ],
            'application' => [
                'name' => config('app.name') ?? 'Teleman AI',
                'url' => config('app.url') ?? '',
                'timezone' => config('app.timezone') ?? 'UTC',
                'locale' => config('app.locale') ?? 'en',
                'debug' => config('app.debug') ?? false,
                'environment' => config('app.env') ?? 'production',
                'logo' => AppHelper::getLogoUrl(),
                'logo_dark' => AppHelper::getLogoDarkUrl(),
                'favicon' => AppHelper::getFaviconUrl(),
                'branding' => AppHelper::getBrandConfig(),
            ],
        ];
    }

    private function updateStripeConfig(array $config)
    {
        $this->updateEnvFile([
            'STRIPE_PUBLIC_KEY' => $config['key'] ?? '',
            'STRIPE_SECRET_KEY' => $config['secret'] !== '••••••••' ? $config['secret'] : null,
            'STRIPE_WEBHOOK_SECRET' => isset($config['webhook_secret']) && $config['webhook_secret'] !== '••••••••' ? $config['webhook_secret'] : null,
        ]);
    }

    private function updateRazorpayConfig(array $config)
    {
        $this->updateEnvFile([
            'RAZORPAY_API_KEY' => $config['key'] ?? '',
            'RAZORPAY_SECRET_KEY' => $config['secret'] !== '••••••••' ? $config['secret'] : null,
            'RAZORPAY_WEBHOOK_SECRET' => $config['webhook_secret'] !== '••••••••' ? ($config['webhook_secret'] ?? '') : null,
            'RAZORPAY_TEST_MODE' => isset($config['test_mode']) ? ($config['test_mode'] ? 'true' : 'false') : 'true',
        ]);
    }

    private function updatePayPalConfig(array $config)
    {
        $this->updateEnvFile([
            'PAYPAL_CLIENT_ID' => $config['client_id'] ?? '',
            'PAYPAL_CLIENT_SECRET' => $config['client_secret'] !== '••••••••' ? $config['client_secret'] : null,
            'PAYPAL_WEBHOOK_ID' => isset($config['webhook_id']) && $config['webhook_id'] !== '••••••••' ? $config['webhook_id'] : null,
            'PAYPAL_TEST_MODE' => isset($config['test_mode']) ? ($config['test_mode'] ? 'true' : 'false') : 'true',
        ]);
    }

    private function updateFlutterwaveConfig(array $config)
    {
        $this->updateEnvFile([
            'FLUTTERWAVE_PUBLIC_KEY' => $config['public_key'] ?? '',
            'FLUTTERWAVE_SECRET_KEY' => $config['secret_key'] !== '••••••••' ? $config['secret_key'] : null,
            'FLUTTERWAVE_ENCRYPTION_KEY' => isset($config['encryption_key']) && $config['encryption_key'] !== '••••••••' ? $config['encryption_key'] : null,
            'FLUTTERWAVE_SECRET_HASH' => isset($config['secret_hash']) && $config['secret_hash'] !== '••••••••' ? $config['secret_hash'] : null,
            'FLUTTERWAVE_TEST_MODE' => isset($config['test_mode']) ? ($config['test_mode'] ? 'true' : 'false') : 'true',
        ]);
    }

    private function updateCryptomusConfig(array $config)
    {
        $this->updateEnvFile([
            'CRYPTOMUS_MERCHANT_UUID' => $config['merchant_uuid'] ?? '',
            'CRYPTOMUS_PAYMENT_KEY' => $config['payment_key'] !== '••••••••' ? $config['payment_key'] : null,
            'CRYPTOMUS_TEST_MODE' => isset($config['test_mode']) ? ($config['test_mode'] ? 'true' : 'false') : 'true',
        ]);
    }

    private function updatePaystackConfig(array $config)
    {
        $this->updateEnvFile([
            'PAYSTACK_PUBLIC_KEY' => $config['public_key'] ?? '',
            'PAYSTACK_SECRET_KEY' => $config['secret_key'] !== '••••••••' ? $config['secret_key'] : null,
            'PAYSTACK_TEST_MODE' => isset($config['test_mode']) ? ($config['test_mode'] ? 'true' : 'false') : 'true',
        ]);
    }

    private function updateTurnstileConfig(array $config)
    {
        $this->updateEnvFile([
            'TURNSTILE_SITE_KEY' => $config['site_key'] ?? '',
            'TURNSTILE_SECRET_KEY' => isset($config['secret_key']) && $config['secret_key'] !== '••••••••' ? $config['secret_key'] : null,
            'TURNSTILE_ENABLED' => isset($config['enabled']) ? ($config['enabled'] ? 'true' : 'false') : 'false',
        ]);
    }

    private function updateCurrencyConfig(array $config)
    {
        $this->updateEnvFile([
            'BASE_CURRENCY' => strtoupper($config['base_currency'] ?? 'USD'),
            'EXCHANGE_RATE_MODE' => $config['exchange_rate_mode'] ?? 'auto',
        ]);

        // Save manual rates if provided
        if (isset($config['manual_rates']) && is_array($config['manual_rates'])) {
            $exchangeRateService = app(ExchangeRateService::class);
            $exchangeRateService->saveManualRates($config['manual_rates']);
        }
    }

    private function updateMailConfig(array $config)
    {
        $envData = [
            'MAIL_MAILER' => $config['mailer'] ?? 'log',
            'MAIL_HOST' => $config['host'] ?? '127.0.0.1',
            'MAIL_PORT' => $config['port'] ?? 2525,
            'MAIL_USERNAME' => $config['username'] ?? 'null',
            'MAIL_PASSWORD' => $config['password'] !== '••••••••' ? ($config['password'] ?? 'null') : null,
            'MAIL_ENCRYPTION' => $config['encryption'] ?? 'tls',
            'MAIL_FROM_ADDRESS' => $config['from_address'] ?? 'hello@example.com',
            'MAIL_FROM_NAME' => $config['from_name'] ?? '"${APP_NAME}"',
        ];

        // Add MAIL_SCHEME if encryption is set
        if (! empty($config['encryption']) && $config['encryption'] !== 'none') {
            $envData['MAIL_ENCRYPTION'] = $config['encryption'];
        }

        $this->updateEnvFile($envData);
    }

    private function updateApplicationConfig(array $config, $logoFile = null, $faviconFile = null, $logoDarkFile = null)
    {
        $envData = [
            'APP_NAME' => $config['name'] ?? 'Teleman AI',
            'APP_URL' => $config['url'] ?? '',
            'APP_TIMEZONE' => $config['timezone'] ?? 'UTC',
            'APP_LOCALE' => $config['locale'] ?? 'en',
            'APP_DEBUG' => isset($config['debug']) ? ($config['debug'] ? 'true' : 'false') : 'false',
            'APP_ENV' => $config['environment'] ?? 'production',
        ];

        $themeSetting = ThemeSetting::first() ?? new ThemeSetting;

        // Keep the brand name in sync — getAppName() reads ThemeSetting.site_name first.
        if (! empty($config['name'])) {
            $themeSetting->site_name = $config['name'];
        }

        // Light logo
        if ($logoFile) {
            if ($themeSetting->logo_path && Storage::disk('public')->exists($themeSetting->logo_path)) {
                Storage::disk('public')->delete($themeSetting->logo_path);
            }
            $themeSetting->logo_path = $logoFile->store('theme', 'public');
            \Log::info('Logo uploaded', ['path' => $themeSetting->logo_path]);
        }

        // Dark-mode logo
        if ($logoDarkFile) {
            if ($themeSetting->logo_dark_path && Storage::disk('public')->exists($themeSetting->logo_dark_path)) {
                Storage::disk('public')->delete($themeSetting->logo_dark_path);
            }
            $themeSetting->logo_dark_path = $logoDarkFile->store('theme', 'public');
            \Log::info('Dark logo uploaded', ['path' => $themeSetting->logo_dark_path]);
        }

        // Remove logos (revert to the text/icon mark) when requested.
        if (! empty($config['remove_logo'])) {
            if ($themeSetting->logo_path && Storage::disk('public')->exists($themeSetting->logo_path)) {
                Storage::disk('public')->delete($themeSetting->logo_path);
            }
            $themeSetting->logo_path = null;
        }
        if (! empty($config['remove_logo_dark'])) {
            if ($themeSetting->logo_dark_path && Storage::disk('public')->exists($themeSetting->logo_dark_path)) {
                Storage::disk('public')->delete($themeSetting->logo_dark_path);
            }
            $themeSetting->logo_dark_path = null;
        }

        // Favicon
        if ($faviconFile) {
            if ($themeSetting->favicon_path && Storage::disk('public')->exists($themeSetting->favicon_path)) {
                Storage::disk('public')->delete($themeSetting->favicon_path);
            }
            $themeSetting->favicon_path = $faviconFile->store('theme', 'public');
        }

        // Per-surface display mode + exact pixel size
        $modes = ['logo_text', 'text_only', 'logo_only'];
        foreach (['landing', 'dashboard', 'auth'] as $surface) {
            $modeKey = "logo_display_{$surface}";
            $sizeKey = "logo_size_{$surface}";
            if (! empty($config[$modeKey]) && in_array($config[$modeKey], $modes, true)) {
                $themeSetting->{$modeKey} = $config[$modeKey];
            }
            if (isset($config[$sizeKey]) && is_numeric($config[$sizeKey])) {
                $themeSetting->{$sizeKey} = max(16, min(120, (int) $config[$sizeKey]));
            }
        }

        $themeSetting->save();

        $this->updateEnvFile($envData);
    }

    private function updateEnvFile(array $data)
    {
        $envPath = app()->environmentFilePath();

        \Log::info('Updating .env file', [
            'path' => $envPath,
            'data_keys' => array_keys($data),
        ]);

        if (! File::exists($envPath)) {
            throw new \Exception('.env file not found');
        }

        if (! is_writable($envPath)) {
            throw new \Exception('.env file is not writable. Please check file permissions.');
        }

        // A null value means "leave this key alone" (e.g. an unchanged password field).
        $changes = array_filter($data, static fn ($value) => $value !== null);

        // EnvWriter locks, validates and swaps the file atomically, and escapes
        // values so secrets containing $ or \ survive intact.
        EnvWriter::make($envPath)->set($changes);

        \Log::info('.env file updated successfully');
    }

    public function fetchLiveRates(Request $request)
    {
        try {
            $baseCurrency = $request->input('base_currency', config('services.base_currency', 'USD'));
            $exchangeRateService = app(ExchangeRateService::class);
            $rates = $exchangeRateService->getRates($baseCurrency);

            return response()->json([
                'success' => true,
                'base' => $baseCurrency,
                'rates' => $rates,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch live rates: '.$e->getMessage(),
            ], 500);
        }
    }

    public function testMail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        try {
            Mail::raw('This is a test email from Teleman AI.', function ($message) use ($request) {
                $message->to($request->email)
                    ->subject('Test Email - Teleman AI');
            });

            return response()->json([
                'success' => true,
                'message' => 'Test email sent successfully to '.$request->email,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send test email: '.$e->getMessage(),
            ], 500);
        }
    }
}
