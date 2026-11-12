<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Safe writer for the .env file.
 *
 * Every historical .env-corruption incident traced back to hand-rolled
 * read/preg_replace/file_put_contents blocks scattered across the app:
 *
 *  - file_put_contents() truncates before writing, so a concurrent reader
 *    could observe a 0-byte file, "update" that empty string and write it
 *    back — permanently emptying .env.
 *  - preg_replace() returns null on PCRE failure, and writing null produced
 *    an empty file.
 *  - Values are used as a regex *replacement*, so "$1"/"$0"/"\1" inside a
 *    secret were expanded as backreferences and silently dropped.
 *
 * This class is the only supported way to mutate .env: it serialises writers
 * through a sidecar lock file, validates, and swaps the file atomically so a
 * crash or a racing request can never leave a truncated or empty file behind.
 *
 * The lock deliberately lives in ".env.lock" rather than on .env itself. On
 * Windows a file that is held open cannot be renamed over, so locking .env
 * directly made the atomic swap fail on every Windows install.
 */
class EnvWriter
{
    public function __construct(private string $path) {}

    public static function make(?string $path = null): self
    {
        return new self($path ?? app()->environmentFilePath());
    }

    /**
     * Set (or insert) one or more keys.
     *
     * @param  array<string, string|int|bool|null>  $values
     * @return bool True when the file was updated, false when there was
     *              nothing to change or the file is unavailable.
     */
    public function set(array $values): bool
    {
        if ($values === [] || ! is_file($this->path)) {
            return false;
        }

        $lock = self::acquireLock($this->path);

        if ($lock === null) {
            return false;
        }

        try {
            $contents = @file_get_contents($this->path);

            // Refuse to operate on an unreadable or already-empty file rather
            // than "repairing" it into a one-line .env.
            if (! is_string($contents) || trim($contents) === '') {
                return false;
            }

            $updated = self::apply($contents, $values);

            if ($updated === $contents) {
                return false;
            }

            self::replaceFileAtomically($this->path, $updated);

            return true;
        } finally {
            self::releaseLock($lock);
        }
    }

    /**
     * Pure transformation: return $contents with each key replaced or
     * appended. No I/O, so it can be reused by code that manages its own
     * locking (see EnvBootstrap).
     *
     * @param  array<string, string|int|bool|null>  $values
     */
    public static function apply(string $contents, array $values): string
    {
        foreach ($values as $key => $value) {
            $contents = self::replaceKey($contents, (string) $key, self::stringify($value));
        }

        return $contents;
    }

    /**
     * Read a single value from raw .env contents (unquoted, trimmed).
     */
    public static function read(string $contents, string $key): ?string
    {
        if (preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $contents, $m) !== 1) {
            return null;
        }

        $value = trim($m[1]);

        if (preg_match('/^(["\'])(.*)\1$/s', $value, $q) === 1) {
            $value = $q[2];
        }

        return $value;
    }

    /**
     * Block until this process holds the exclusive .env lock.
     *
     * @return resource|null
     */
    public static function acquireLock(string $envPath)
    {
        $handle = @fopen(self::lockPath($envPath), 'c');

        if ($handle === false) {
            return null;
        }

        if (! flock($handle, LOCK_EX)) {
            fclose($handle);

            return null;
        }

        return $handle;
    }

    /**
     * @param  resource  $handle
     */
    public static function releaseLock($handle): void
    {
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    public static function lockPath(string $envPath): string
    {
        return $envPath.'.lock';
    }

    /**
     * Write via a temp file + rename so readers only ever see the complete
     * old file or the complete new one — never a truncated one. Works for a
     * target that does not exist yet.
     *
     * Callers must hold the lock (or be certain nobody else writes).
     */
    public static function replaceFileAtomically(string $path, string $contents): void
    {
        if (trim($contents) === '') {
            throw new RuntimeException('Refusing to write an empty .env file.');
        }

        $directory = dirname($path);
        $temp = @tempnam($directory, '.env-tmp-');

        if ($temp === false) {
            throw new RuntimeException('Unable to create a temporary file for .env.');
        }

        try {
            if (@file_put_contents($temp, $contents, LOCK_EX) !== strlen($contents)) {
                throw new RuntimeException('Failed to fully write the temporary .env file.');
            }

            // Preserve the original permissions; tempnam() creates 0600.
            $perms = is_file($path) ? @fileperms($path) : false;
            if ($perms !== false) {
                @chmod($temp, $perms & 0777);
            } else {
                @chmod($temp, 0644);
            }

            // On Windows a rename fails while any other process has the target
            // open — a concurrent request merely *reading* .env is enough. Such
            // reads last microseconds, so retry briefly before giving up.
            $attempts = 0;
            while (! @rename($temp, $path)) {
                if (++$attempts >= 40) {
                    throw new RuntimeException('Failed to atomically replace the .env file.');
                }

                usleep(25_000);
            }
        } catch (\Throwable $e) {
            @unlink($temp);
            throw $e;
        }
    }

    /**
     * Replace a single KEY=... line, or append it when absent.
     */
    private static function replaceKey(string $contents, string $key, string $value): string
    {
        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
        $line = $key.'='.self::quote($value);

        if (preg_match($pattern, $contents) === 1) {
            // preg_replace_callback avoids backreference expansion ($1, \1,
            // $0) inside secrets such as "sk_live_a$1b".
            $result = preg_replace_callback($pattern, fn (): string => $line, $contents, 1);

            // preg_replace_callback returns null on PCRE failure (backtrack
            // or JIT stack limits). Never propagate that into a write.
            return is_string($result) ? $result : $contents;
        }

        return rtrim($contents, "\r\n")."\n".$line."\n";
    }

    private static function stringify(string|int|bool|null $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            $value === null => '',
            default => (string) $value,
        };
    }

    /**
     * Quote values that would otherwise break dotenv parsing. Note "0" and
     * "false" are meaningful values, so only genuinely empty strings and
     * values with whitespace/specials get quoted.
     */
    private static function quote(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (preg_match('/^".*"$/s', $value) === 1) {
            return $value;
        }

        if (preg_match('/[\s#"\'\\\\$]/', $value) === 1) {
            return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
        }

        return $value;
    }
}
