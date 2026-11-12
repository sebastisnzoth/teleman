<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Makes a raw upload bootable before Laravel starts.
 *
 * A buyer unzips the product, uploads it and opens the domain. At that point
 * there is no .env at all, .env.example ships with an empty APP_KEY, and its
 * session/cache/queue drivers point at a database that does not exist yet.
 * Laravel cannot even render an error page in that state ("No application
 * encryption key has been specified"), so the install wizard was unreachable
 * for exactly the people it exists for.
 *
 * This runs from public/index.php, ahead of the framework, and only ever
 * does three things:
 *
 *  1. No usable .env  → create one (from .env.example, a dated backup, or a
 *     built-in template) with install-safe drivers.
 *  2. No APP_KEY      → generate one.
 *  3. Either happened → drop a stale config cache that would keep serving
 *     the broken state.
 *
 * An existing, populated .env is otherwise never modified: it may belong to
 * a live install.
 */
final class EnvBootstrap
{
    /**
     * Settings a fresh install must start with. Nothing here may depend on a
     * database — the wizard creates it later and flips these to their
     * production values at the end.
     */
    public const INSTALL_DEFAULTS = [
        'APP_ENV' => 'production',
        'APP_DEBUG' => 'false',
        'SESSION_DRIVER' => 'file',
        'CACHE_STORE' => 'file',
        'QUEUE_CONNECTION' => 'sync',
        'BROADCAST_CONNECTION' => 'log',
        // Off until the owner adds Cloudflare keys; on, it blocks every login.
        'TURNSTILE_ENABLED' => 'false',
    ];

    /**
     * @return list<string> Actions taken: created, restored_from_backup, key_generated.
     */
    public static function ensure(string $basePath): array
    {
        $envPath = $basePath.DIRECTORY_SEPARATOR.'.env';
        $lock = EnvWriter::acquireLock($envPath);

        if ($lock === null) {
            // Nothing we can do without write access; let the app report it.
            return [];
        }

        try {
            $actions = [];
            $contents = is_file($envPath) ? @file_get_contents($envPath) : false;
            $changed = false;

            if (! is_string($contents) || trim($contents) === '') {
                $backup = self::newestBackup($basePath);

                if ($backup !== null) {
                    $contents = $backup;
                    $actions[] = 'restored_from_backup';
                } else {
                    $contents = EnvWriter::apply(self::template($basePath), self::INSTALL_DEFAULTS);
                    $actions[] = 'created';
                }

                $changed = true;
            }

            if ((string) EnvWriter::read($contents, 'APP_KEY') === '') {
                $contents = EnvWriter::apply($contents, ['APP_KEY' => self::generateKey()]);
                $actions[] = 'key_generated';
                $changed = true;
            }

            if ($changed) {
                EnvWriter::replaceFileAtomically($envPath, $contents);

                $cache = $basePath.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'config.php';
                if (is_file($cache)) {
                    @unlink($cache);
                }
            }

            return $actions;
        } finally {
            EnvWriter::releaseLock($lock);
        }
    }

    public static function generateKey(): string
    {
        return 'base64:'.base64_encode(random_bytes(32));
    }

    private static function template(string $basePath): string
    {
        $example = $basePath.DIRECTORY_SEPARATOR.'.env.example';
        $contents = is_file($example) ? @file_get_contents($example) : false;

        if (is_string($contents) && trim($contents) !== '') {
            return $contents;
        }

        // The example is missing from the upload; this is the smallest .env
        // that lets the app boot and the wizard run.
        return implode("\n", [
            'APP_NAME=Teleman',
            'APP_ENV=production',
            'APP_KEY=',
            'APP_DEBUG=false',
            'APP_URL=http://localhost',
            'LOG_CHANNEL=stack',
            'LOG_LEVEL=error',
            'DB_CONNECTION=sqlite',
            'SESSION_DRIVER=file',
            'CACHE_STORE=file',
            'QUEUE_CONNECTION=sync',
            'BROADCAST_CONNECTION=log',
            'FILESYSTEM_DISK=local',
            'MAIL_MAILER=log',
        ])."\n";
    }

    /**
     * Newest non-empty snapshot written by BackupEnvDaily, if any.
     */
    private static function newestBackup(string $basePath): ?string
    {
        $dir = $basePath.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'backups'.DIRECTORY_SEPARATOR.'env';
        $files = glob($dir.DIRECTORY_SEPARATOR.'.env-*') ?: [];
        rsort($files, SORT_STRING);

        foreach ($files as $file) {
            $contents = @file_get_contents($file);
            if (is_string($contents) && trim($contents) !== '') {
                return $contents;
            }
        }

        return null;
    }
}
