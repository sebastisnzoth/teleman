<?php

namespace App\Http\Middleware;

use App\Support\EnvWriter;
use App\Support\Installation;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;

class CheckInstalled
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $installed = Installation::isInstalled();

        // If not installed, use file-based sessions to avoid database dependency
        if (! $installed) {
            // Nothing may touch the database before it exists. .env is fixed
            // below for the next request; this covers the current one.
            config([
                'session.driver' => 'file',
                'cache.default' => 'file',
                'queue.default' => 'sync',
            ]);

            // Also update .env file to persist this setting
            $this->ensureFileSessionsInEnv();

            // Redirect to installer (except if already on install routes)
            if (! $request->is('install*')) {
                return redirect('/install');
            }
        }

        // Once installed the wizard is closed — except its final page, which
        // only shows the post-install checklist (cron, queue worker). Sending
        // that to /dashboard meant nobody ever read it.
        if ($installed && $request->is('install*') && ! $request->is('install/complete')) {
            return redirect('/dashboard');
        }

        return $next($request);
    }

    /**
     * Ensure .env file uses file sessions AND file cache during installation.
     *
     * Only runs while the app is not yet installed, and only rewrites the
     * drivers that still point at the (not-yet-migrated) database.
     */
    private function ensureFileSessionsInEnv(): void
    {
        $envFile = app()->environmentFilePath();

        if (! File::exists($envFile)) {
            return;
        }

        $envContent = File::get($envFile);

        if (! is_string($envContent) || trim($envContent) === '') {
            return;
        }

        $changes = [];

        if (preg_match('/^SESSION_DRIVER=database/m', $envContent) === 1) {
            $changes['SESSION_DRIVER'] = 'file';
        }

        if (preg_match('/^CACHE_STORE=database/m', $envContent) === 1) {
            $changes['CACHE_STORE'] = 'file';
        }

        if (preg_match('/^QUEUE_CONNECTION=database/m', $envContent) === 1) {
            $changes['QUEUE_CONNECTION'] = 'sync';
        }

        if ($changes === []) {
            return;
        }

        if (EnvWriter::make($envFile)->set($changes)) {
            // Clear config cache to pick up the changes
            $configCache = base_path('bootstrap/cache/config.php');
            if (File::exists($configCache)) {
                @File::delete($configCache);
            }
        }
    }
}
