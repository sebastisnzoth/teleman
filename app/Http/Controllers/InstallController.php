<?php

namespace App\Http\Controllers;

use App\Actions\Telephony\LockTelephonyProviderAction;
use App\Enums\TelephonyProvider;
use App\Exceptions\Telephony\AlreadyLockedException;
use App\Http\Middleware\EnsureNotInstalled;
use App\Http\Requests\LockTelephonyProviderRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\Install\DatabaseInstaller;
use App\Services\Telephony\TelephonyLock;
use App\Support\EnvWriter;
use App\Support\Installation;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class InstallController extends Controller implements HasMiddleware
{
    /**
     * The installer rewrites database credentials and creates an admin
     * account without authentication, so it re-checks the install state
     * itself rather than trusting the global middleware alone — a missing
     * marker file must not be enough to re-open it.
     *
     * 'complete' is exempt: it is the page shown immediately after a
     * successful install, and it has its own marker check.
     */
    public static function middleware(): array
    {
        return [
            new Middleware(EnsureNotInstalled::class, except: ['complete']),
        ];
    }

    /**
     * Show installer welcome page
     */
    public function index()
    {
        return redirect()->route('install.requirements');
    }

    /**
     * Show requirements check page
     */
    public function requirements()
    {
        $requirements = $this->getRequirements();

        return Inertia::render('Install/Requirements', [
            'requirements' => $requirements,
        ]);
    }

    /**
     * Check all requirements
     */
    public function checkRequirements()
    {
        $requirements = $this->getRequirements();

        $allPassed = collect($requirements)->every(function ($group) {
            return collect($group['items'])->every(fn ($item) => $item['passed']);
        });

        if ($allPassed) {
            return redirect()->route('install.database');
        }

        return back()->with('error', 'Please fix all requirements before continuing.');
    }

    /**
     * Show database configuration page
     */
    public function database()
    {
        return Inertia::render('Install/Database', [
            'env' => [
                'DB_HOST' => env('DB_HOST', '127.0.0.1'),
                'DB_PORT' => env('DB_PORT', '3306'),
                'DB_DATABASE' => env('DB_DATABASE', ''),
                'DB_USERNAME' => env('DB_USERNAME', ''),
            ],
        ]);
    }

    /**
     * Test database connection
     */
    public function testDatabase(Request $request, DatabaseInstaller $installer)
    {
        $credentials = $this->validateCredentials($request);

        try {
            $installer->probe($credentials);
        } catch (\RuntimeException $e) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['message' => $e->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => 'Database connection successful!']);
        }

        return back()->with('flash', ['message' => 'Database connection successful!']);
    }

    /**
     * Save the database connection and prepare for the stepped migration.
     *
     * This used to migrate and seed everything inside one request, which
     * timed out on shared hosting — and, because it never wrote
     * DB_CONNECTION and rebuilt the container with config:cache mid-request,
     * it migrated the wrong database and redirected to localhost. The heavy
     * work now happens in databaseStep(), one slice per request.
     */
    public function runMigrations(Request $request, DatabaseInstaller $installer)
    {
        $credentials = $this->validateCredentials($request);
        $wipe = $request->boolean('wipe');

        try {
            $installer->configure($credentials);
        } catch (\RuntimeException $e) {
            Log::warning('Installer could not configure the database', ['error' => $e->getMessage()]);

            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['message' => $e->getMessage()]);
        }

        $inspection = $installer->inspect();

        if (! $inspection['has_migrations_table'] && $inspection['table_count'] > 0) {
            if (! $wipe) {
                $payload = [
                    'ok' => false,
                    'reason' => 'not_empty',
                    'table_count' => $inspection['table_count'],
                    'message' => 'This database already contains tables. Use an empty database, or confirm erasing it.',
                ];

                return $request->expectsJson()
                    ? response()->json($payload, 409)
                    : back()->withErrors(['message' => $payload['message']]);
            }

            $installer->wipe();
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'status' => $installer->status()]);
        }

        return redirect()->route('install.database')->with('flash', ['message' => 'Database connection saved.']);
    }

    /**
     * Run one time-budgeted slice of migrations / seeders and report progress.
     * The browser keeps calling this until "done" is true; each call resumes
     * exactly where the previous one stopped, however it stopped.
     */
    public function databaseStep(DatabaseInstaller $installer)
    {
        if (! $installer->isConfigured()) {
            return response()->json([
                'ok' => false,
                'message' => 'Configure the database connection first.',
            ], 409);
        }

        try {
            $result = $installer->step((float) config('install.step_budget', 15));
        } catch (\Throwable $e) {
            Log::error('Installer step failed', ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);

            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 500);
        }

        if (($result['stage'] ?? null) === 'blocked') {
            return response()->json(['ok' => false] + $result + [
                'message' => 'This database already contains tables. Use an empty database, or confirm erasing it.',
            ], 409);
        }

        return response()->json(['ok' => true] + $result);
    }

    /**
     * @return array{driver: string, host?: string, port?: int, database: string, username?: string, password?: string}
     */
    private function validateCredentials(Request $request): array
    {
        $validated = $request->validate([
            'driver' => ['nullable', Rule::in(DatabaseInstaller::DRIVERS)],
            'host' => ['required_unless:driver,sqlite', 'nullable', 'string'],
            'port' => ['required_unless:driver,sqlite', 'nullable', 'integer'],
            'database' => ['required', 'string'],
            'username' => ['required_unless:driver,sqlite', 'nullable', 'string'],
            'password' => ['nullable', 'string'],
        ]);

        $validated['driver'] = $validated['driver'] ?? 'mysql';
        $validated['password'] = $validated['password'] ?? '';

        return $validated;
    }

    /**
     * Show the telephony provider chooser.
     *
     * The one point in the wizard — and in the product's life — where the
     * buyer picks Twilio or Plivo and is warned the choice is permanent.
     * Already locked (a resumed install, or a refreshed browser after a
     * successful post): nothing left to choose here, so move forward
     * instead of re-asking or erroring.
     */
    public function telephony(TelephonyLock $lock)
    {
        // Guard: mirrors admin()/createAdmin() below. Reading the lock is a
        // database query, and this step is only meant to run after the
        // database step has configured a working connection.
        if (! $this->isDatabaseMigrated()) {
            return redirect()->route('install.database')
                ->with('error', 'Please configure the database first.');
        }

        if ($lock->isLocked()) {
            return redirect()->route('install.admin');
        }

        return Inertia::render('Install/Telephony', [
            'provider' => TelephonyProvider::TWILIO->value,
            'providers' => array_map(
                static fn (TelephonyProvider $provider) => [
                    'value' => $provider->value,
                    'label' => $provider->label(),
                ],
                TelephonyProvider::cases(),
            ),
            'submitUrl' => route('install.telephony.lock'),
            'backUrl' => route('install.database'),
        ]);
    }

    /**
     * Lock the installation to the chosen provider and move on to admin
     * creation.
     *
     * locked_by_user_id is left null (LockTelephonyProviderAction's default):
     * no admin account exists yet at this point in the wizard, and the
     * locking user must never be derived from request input.
     */
    public function lockTelephonyProvider(LockTelephonyProviderRequest $request, LockTelephonyProviderAction $action)
    {
        if (! $this->isDatabaseMigrated()) {
            return redirect()->route('install.database')
                ->with('error', 'Please configure the database first.');
        }

        $provider = TelephonyProvider::from($request->validated('provider'));

        try {
            $action->execute($provider);
        } catch (AlreadyLockedException) {
            // Someone already locked a different provider — a duplicate
            // submit, or a second browser tab racing this one. The
            // decision is already made and irreversible from this screen,
            // so there is nothing useful to ask the buyer to fix: just
            // continue the wizard with whichever provider actually won.
            // What matters is that this never surfaces as a raw 500.
        } catch (LockTimeoutException) {
            // The same race, caught one step earlier: TelephonyLock
            // serialises every lock() behind a cache mutex and gives up
            // after a few seconds of waiting. Unlike the case above,
            // nothing was written and no provider is necessarily locked
            // yet, so continuing to /install/admin would only bounce
            // straight back here (that step refuses to render without a
            // lock). Send the buyer back to this step with a readable
            // reason instead — the chooser renders a 'provider' error,
            // which a flash message on this page would not.
            return redirect()->route('install.telephony')->withErrors([
                'provider' => 'Another request is still finishing this step. Please try again.',
            ]);
        }

        return redirect()->route('install.admin');
    }

    /**
     * Show admin creation page
     */
    public function admin(TelephonyLock $lock)
    {
        // Guard: ensure database has been migrated before allowing admin creation
        if (! $this->isDatabaseMigrated()) {
            return redirect()->route('install.database')
                ->with('error', 'Please configure the database first.');
        }

        // Guard: a telephony provider must be locked before an admin
        // account can be created — this is what makes the choice
        // unskippable rather than merely the default path through the UI.
        if (! $lock->isLocked()) {
            return redirect()->route('install.telephony');
        }

        return Inertia::render('Install/Admin');
    }

    /**
     * Create admin user and complete installation
     */
    public function createAdmin(Request $request, TelephonyLock $lock)
    {
        // Guard: ensure database has been migrated
        if (! $this->isDatabaseMigrated()) {
            return redirect()->route('install.database')
                ->with('error', 'Please configure the database first.');
        }

        // Guard: mirrors admin()'s own guard. The GET page stops a browser
        // from reaching this step without a locked provider, but this
        // endpoint must refuse on its own terms too — a scripted install
        // (this product supports non-browser automation) could otherwise
        // post straight here and finish with no provider ever locked.
        if (! $lock->isLocked()) {
            return redirect()->route('install.telephony');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        try {
            // Create admin user
            $admin = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'status' => 'active',
                'credit_balance' => 0,
            ]);

            // Mark email as verified (not in $fillable, must set directly)
            $admin->email_verified_at = now();
            $admin->save();

            // Assign admin role via the roles system
            $adminRole = Role::where('slug', Role::ADMIN)->first();
            if ($adminRole) {
                $admin->assignRole($adminRole);
            }

            $this->finalizeEnvironment($request);

            // Mark the app installed (marker lives outside the web root)
            Installation::markInstalled();

            return redirect()->route('install.complete');
        } catch (\Exception $e) {
            return back()->withErrors([
                'error' => 'Failed to create admin user: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Show installation complete page
     */
    public function complete()
    {
        // Guard: only reachable once the install actually finished
        if (! Installation::isInstalled()) {
            return redirect()->route('install.requirements');
        }

        $root = base_path();

        return Inertia::render('Install/Complete', [
            'cronCommand' => "* * * * * cd {$root} && php artisan schedule:run >> /dev/null 2>&1",
            'queueCommand' => "cd {$root} && php artisan queue:work database --queue=high,default,campaigns --tries=3 --timeout=300",
            // Shared hosting cannot keep a process alive; a cron-driven worker
            // that drains the queue and exits every minute is the workable
            // alternative there.
            'queueCronCommand' => "* * * * * cd {$root} && php artisan queue:work database --queue=high,default,campaigns --stop-when-empty --max-time=55 >> /dev/null 2>&1",
            'appUrl' => rtrim((string) config('app.url'), '/'),
        ]);
    }

    /**
     * Turn the install-time .env into a production one: database-backed
     * session/cache/queue now that the tables exist, the real URL instead of
     * .env.example's localhost, and debug off.
     */
    private function finalizeEnvironment(Request $request): void
    {
        rescue(function () use ($request): void {
            EnvWriter::make()->set([
                'APP_ENV' => 'production',
                'APP_DEBUG' => false,
                'APP_URL' => $request->getSchemeAndHttpHost(),
                'SESSION_DRIVER' => 'database',
                'CACHE_STORE' => 'database',
                'QUEUE_CONNECTION' => 'database',
            ]);

            // A config cache from the wizard would pin the old drivers.
            File::delete(base_path('bootstrap/cache/config.php'));
        }, null, true);
    }

    /**
     * Get system requirements
     */
    private function getRequirements(): array
    {
        $envPath = app()->environmentFilePath();
        $memoryLimit = (string) ini_get('memory_limit');
        $maxExecution = (int) ini_get('max_execution_time');

        // Every item carries a plain-language fix so a buyer without a
        // developer can act on a red mark.
        $extHint = 'Enable this PHP extension in your hosting panel (cPanel: "Select PHP Extensions"; Plesk: PHP Settings → Extensions) or ask your host to enable it.';
        $permHint = "In your file manager, set this folder's permissions to 775 (or 755 if your host runs PHP as your user), including subfolders.";

        return [
            'php' => [
                'title' => 'PHP Requirements',
                'items' => [
                    [
                        // Keep in step with composer.json ("php": "^8.2").
                        'hint' => 'Switch the PHP version in your hosting panel (cPanel: MultiPHP Manager; Plesk: PHP Settings; aaPanel: Website → PHP version).',
                        'name' => 'PHP Version >= 8.2',
                        'passed' => version_compare(PHP_VERSION, '8.2.0', '>='),
                        'current' => PHP_VERSION,
                    ],
                ],
            ],
            'extensions' => [
                'title' => 'PHP Extensions',
                'items' => [
                    ['name' => 'PDO', 'passed' => extension_loaded('pdo'), 'hint' => $extHint],
                    ['name' => 'PDO MySQL', 'passed' => extension_loaded('pdo_mysql'), 'hint' => $extHint],
                    ['name' => 'mbstring', 'passed' => extension_loaded('mbstring'), 'hint' => $extHint],
                    ['name' => 'OpenSSL', 'passed' => extension_loaded('openssl'), 'hint' => $extHint],
                    ['name' => 'Tokenizer', 'passed' => extension_loaded('tokenizer'), 'hint' => $extHint],
                    ['name' => 'XML', 'passed' => extension_loaded('xml'), 'hint' => $extHint],
                    ['name' => 'Ctype', 'passed' => extension_loaded('ctype'), 'hint' => $extHint],
                    ['name' => 'JSON', 'passed' => extension_loaded('json'), 'hint' => $extHint],
                    ['name' => 'BCMath', 'passed' => extension_loaded('bcmath'), 'hint' => $extHint],
                    ['name' => 'Fileinfo', 'passed' => extension_loaded('fileinfo'), 'hint' => $extHint],
                    ['name' => 'cURL', 'passed' => extension_loaded('curl'), 'hint' => $extHint],
                ],
            ],
            'environment' => [
                'title' => 'PHP Configuration',
                'items' => [
                    [
                        'name' => 'memory_limit (128M or more)',
                        'passed' => $this->memoryLimitAtLeast($memoryLimit, 128 * 1024 * 1024),
                        'current' => $memoryLimit,
                        'hint' => 'Raise memory_limit to 256M in your hosting panel (cPanel: MultiPHP INI Editor) or php.ini.',
                    ],
                    [
                        // Informational: the installer works in short slices,
                        // so a low limit slows it down but cannot block it.
                        'name' => 'max_execution_time',
                        'passed' => true,
                        'current' => $maxExecution === 0 ? 'unlimited' : $maxExecution.'s',
                        'hint' => 'Informational only: the installer works in short slices, so a low limit slows it down but cannot block it.',
                    ],
                ],
            ],
            'permissions' => [
                'title' => 'Directory Permissions',
                'items' => [
                    ['name' => 'storage/', 'passed' => is_writable(storage_path()), 'hint' => $permHint],
                    ['name' => 'storage/app/', 'passed' => is_writable(storage_path('app')), 'hint' => $permHint],
                    ['name' => 'storage/framework/', 'passed' => is_writable(storage_path('framework')), 'hint' => $permHint],
                    ['name' => 'storage/logs/', 'passed' => is_writable(storage_path('logs')), 'hint' => $permHint],
                    ['name' => 'bootstrap/cache/', 'passed' => is_writable(base_path('bootstrap/cache')), 'hint' => $permHint],
                ],
            ],
            'files' => [
                'title' => 'Required Files',
                'items' => [
                    [
                        // Created automatically on first request; the wizard
                        // has to be able to write it.
                        'name' => '.env file is writable',
                        'passed' => is_file($envPath) ? is_writable($envPath) : is_writable(dirname($envPath)),
                        'hint' => 'The application folder (the one containing artisan) must be writable by the web server: set its permissions to 775 in your file manager.',
                    ],
                    [
                        'name' => 'Composer dependencies (vendor/)',
                        'passed' => is_file(base_path('vendor/autoload.php')),
                        'hint' => 'The vendor/ folder is missing: re-upload the complete package, or extract the zip again and make sure all folders were uploaded.',
                    ],
                    [
                        'name' => 'Front-end build (public/build)',
                        'passed' => is_file(public_path('build/manifest.json')),
                        'hint' => 'public/build/ is missing: re-upload the complete package.',
                    ],
                    $this->documentRootRequirement(),
                ],
            ],
        ];
    }

    /**
     * The app must be served from public/. With the root .htaccess shim that
     * is transparent; without rewriting, the root index.php still answers
     * but assets cannot load, so say so here instead of shipping a broken
     * dashboard.
     *
     * @return array{name: string, passed: bool, current?: string}
     */
    private function documentRootRequirement(): array
    {
        $script = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');

        $hint = 'Point the domain at the public/ folder (cPanel: Domains → Document Root). If you cannot, upload the files directly into public_html so the included .htaccess can route into public/.';

        if ($script === '' || app()->runningInConsole()) {
            return ['name' => 'Web server document root is public/', 'passed' => true, 'hint' => $hint];
        }

        $servedFromPublic = basename(dirname($script)) === 'public';

        return [
            'name' => 'Web server document root is public/',
            'passed' => $servedFromPublic,
            'current' => $servedFromPublic ? 'OK' : 'not public/',
            'hint' => $hint,
        ];
    }

    /**
     * Compare a php.ini size such as "128M" or "-1" against a byte count.
     */
    private function memoryLimitAtLeast(string $limit, int $bytes): bool
    {
        $limit = trim($limit);

        if ($limit === '' || $limit === '-1') {
            return true;
        }

        $unit = strtolower(substr($limit, -1));
        $value = (float) $limit;

        $value *= match ($unit) {
            'g' => 1024 ** 3,
            'm' => 1024 ** 2,
            'k' => 1024,
            default => 1,
        };

        return $value >= $bytes;
    }

    /**
     * Check if the database has been migrated by testing if the users table exists
     */
    private function isDatabaseMigrated(): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable('users');
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Clear config cache files manually
     */
    private function clearConfigCache(): void
    {
        $cacheFiles = [
            base_path('bootstrap/cache/config.php'),
            base_path('bootstrap/cache/routes-v7.php'),
            base_path('bootstrap/cache/events.php'),
            base_path('bootstrap/cache/packages.php'),
            base_path('bootstrap/cache/services.php'),
        ];

        foreach ($cacheFiles as $file) {
            if (File::exists($file)) {
                @File::delete($file);
            }
        }
    }
}
