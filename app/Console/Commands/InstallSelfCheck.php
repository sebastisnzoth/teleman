<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\EnvWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Exercise, on this machine, the platform-sensitive operations the web
 * installer depends on. Meant for support: "run php artisan install:selfcheck
 * and paste the output" replaces guessing about a buyer's host.
 *
 * Nothing here touches the real .env — every write goes to scratch files
 * under storage/framework/.
 */
class InstallSelfCheck extends Command
{
    protected $signature = 'install:selfcheck';

    protected $description = 'Verify this server can run the web installer (file locking, atomic .env writes, permissions, symlinks)';

    /** @var list<array{name: string, ok: bool, detail: string, fatal: bool}> */
    private array $results = [];

    public function handle(): int
    {
        $scratch = storage_path('framework/selfcheck-'.getmypid());
        File::ensureDirectoryExists($scratch);

        try {
            $this->check('PHP version', version_compare(PHP_VERSION, '8.2.0', '>='), PHP_VERSION.' on '.PHP_OS_FAMILY);
            $this->check('pdo_mysql extension', extension_loaded('pdo_mysql'), extension_loaded('pdo_mysql') ? 'loaded' : 'missing — MySQL cannot be used');
            $this->check('storage/ writable', is_writable(storage_path()), storage_path());
            $this->check('bootstrap/cache/ writable', is_writable(base_path('bootstrap/cache')), base_path('bootstrap/cache'));

            $envPath = $this->laravel->environmentFilePath();
            $envWritable = is_file($envPath) ? is_writable($envPath) : is_writable(dirname($envPath));
            $this->check('.env writable', $envWritable, is_file($envPath) ? $envPath : dirname($envPath).' (will be created)');

            $this->checkEnvLock($scratch);
            $this->checkAtomicReplace($scratch);
            $this->checkSymlink($scratch);
        } finally {
            File::deleteDirectory($scratch);
        }

        $this->newLine();
        foreach ($this->results as $r) {
            $tag = $r['ok'] ? '<info>PASS</info>' : ($r['fatal'] ? '<error>FAIL</error>' : '<comment>WARN</comment>');
            $this->line(sprintf(' [%s] %-28s %s', $tag, $r['name'], $r['detail']));
        }
        $this->newLine();

        $failed = array_filter($this->results, fn ($r) => ! $r['ok'] && $r['fatal']);

        if ($failed !== []) {
            $this->error(count($failed).' check(s) failed. The web installer will not work until they are fixed.');

            return self::FAILURE;
        }

        $this->info('All checks passed. The web installer can run on this server.');

        return self::SUCCESS;
    }

    private function checkEnvLock(string $scratch): void
    {
        $env = $scratch.DIRECTORY_SEPARATOR.'.env';

        try {
            $handle = EnvWriter::acquireLock($env);
            $ok = $handle !== null;
            if ($handle !== null) {
                EnvWriter::releaseLock($handle);
            }
            $this->check('.env lock', $ok, $ok ? 'flock() on '.basename(EnvWriter::lockPath($env)).' works' : 'could not lock '.EnvWriter::lockPath($env));
        } catch (Throwable $e) {
            $this->check('.env lock', false, $e->getMessage());
        }
    }

    /**
     * The swap that used to fail on Windows: replace an existing file via
     * temp + rename with no handle held open on the target.
     */
    private function checkAtomicReplace(string $scratch): void
    {
        $env = $scratch.DIRECTORY_SEPARATOR.'.env';

        try {
            file_put_contents($env, "APP_KEY=\nAPP_NAME=before\n");

            $ok = EnvWriter::make($env)->set(['APP_NAME' => 'after', 'SECRET' => 'sk_live_a$1b']);
            $after = (string) file_get_contents($env);
            $ok = $ok && str_contains($after, 'APP_NAME=after') && str_contains($after, 'SECRET="sk_live_a$1b"');
            $leftovers = glob($scratch.DIRECTORY_SEPARATOR.'.env-tmp-*') ?: [];

            $this->check('.env atomic replace', $ok && $leftovers === [], $ok ? 'temp file + rename OK, no leftovers' : 'replace failed or wrote wrong content');
        } catch (Throwable $e) {
            $this->check('.env atomic replace', false, $e->getMessage());
        }
    }

    /**
     * Informational only: without symlink rights (Windows without Developer
     * Mode, some shared hosts) uploads fall back to the private disk.
     */
    private function checkSymlink(string $scratch): void
    {
        $target = $scratch.DIRECTORY_SEPARATOR.'target';
        $link = $scratch.DIRECTORY_SEPARATOR.'link';

        try {
            File::ensureDirectoryExists($target);
            $ok = @symlink($target, $link) && is_link($link);
            $this->check('symlink support', $ok, $ok ? 'public/storage link can be created' : 'symlink() unavailable — storage:link will be skipped (not fatal)', fatal: false);
        } catch (Throwable $e) {
            $this->check('symlink support', false, $e->getMessage().' (not fatal)', fatal: false);
        }
    }

    private function check(string $name, bool $ok, string $detail, bool $fatal = true): void
    {
        $this->results[] = compact('name', 'ok', 'detail', 'fatal');
    }
}
