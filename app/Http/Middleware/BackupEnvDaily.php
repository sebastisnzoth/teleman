<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Keep one copy of .env — the newest — so EnvBootstrap can restore an
 * emptied .env, APP_KEY and all, instead of starting again from .env.example
 * and losing everything encrypted with the old key.
 *
 * Every copy holds every secret .env does, so only one is kept per APP_KEY,
 * it is readable by its owner alone, ENV_BACKUPS=false turns it off, and a
 * test run never copies the checkout's own .env (a developer's holds live
 * keys). An .env that has lost its APP_KEY is not copied — it would replace
 * the copy that can still restore it — and a copy holding a different APP_KEY
 * is never removed, being all that can decrypt what that key encrypted, but
 * is cut down to that key: nothing else in it is worth keeping.
 */
class BackupEnvDaily
{
    public function handle(Request $request, Closure $next)
    {
        if (config('app.env_backups', true) && ! app()->runningUnitTests()) {
            $this->snapshot();
        }

        return $next($request);
    }

    private function snapshot(): void
    {
        try {
            $backupDir = storage_path('backups/env');
            $todayFile = $backupDir.'/.env-'.date('Y-m-d');

            if (file_exists($todayFile)) {
                return;
            }

            if (! is_dir($backupDir)) {
                @mkdir($backupDir, 0755, true);
            }

            // Only snapshot a healthy .env — copying an empty or truncated
            // file would overwrite the very thing we might need to restore.
            $envPath = app()->environmentFilePath();
            $contents = is_file($envPath) ? @file_get_contents($envPath) : false;
            $appKey = is_string($contents) ? $this->appKey($contents) : null;

            if ($appKey === null) {
                return;
            }

            // Owner-readable from the first byte, and whole or not at all.
            $partial = $backupDir.'/.partial-'.date('Y-m-d');

            if (@file_put_contents($partial, $contents, LOCK_EX) === false) {
                return;
            }

            @chmod($partial, 0600);

            if (! @rename($partial, $todayFile)) {
                @unlink($partial);

                return;
            }

            // Only now that today's copy exists, deal with the older ones: one
            // holding the same APP_KEY is superseded and removed; one holding
            // another key keeps that key alone — the secrets stored beside it
            // have most likely been rotated since.
            foreach (glob($backupDir.'/.env-*') ?: [] as $file) {
                $older = $file === $todayFile ? false : @file_get_contents($file);

                if (! is_string($older)) {
                    continue;
                }

                if ($this->appKey($older) === $appKey) {
                    @unlink($file);
                } elseif ($this->appKey($older) !== null) {
                    @file_put_contents($file, $this->keysOnly($older), LOCK_EX);
                }
            }
        } catch (\Throwable $e) {
            // Never block the request — backup is best-effort
        }
    }

    /** Just the APP_KEY and APP_PREVIOUS_KEYS lines of an .env. */
    private function keysOnly(string $contents): string
    {
        preg_match_all('/^(?:APP_KEY|APP_PREVIOUS_KEYS)\s*=.*$/m', $contents, $lines);

        return implode("\n", array_map('trim', $lines[0]))."\n";
    }

    /** The APP_KEY an .env sets, or null when it sets none. */
    private function appKey(string $contents): ?string
    {
        if (preg_match('/^APP_KEY\s*=\s*["\']?([^"\'\r\n]*)["\']?\s*$/m', $contents, $match) !== 1) {
            return null;
        }

        return trim($match[1]) === '' ? null : trim($match[1]);
    }
}
