<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Single source of truth for "has this app been installed yet?".
 *
 * The installer rewrites database credentials in .env and creates an admin
 * account, all without authentication — so the check that gates it is a
 * security boundary. It used to be a bare file_exists() on
 * public/.installed, which was weak in two ways:
 *
 *  1. The marker lived in the web root, so it was fetchable at /.installed
 *     and was routinely lost to deploys that rebuild public/ (rsync,
 *     container images, CI artifacts).
 *  2. Losing that one file re-opened the installer to anonymous visitors,
 *     who could then point the app at their own database and create an
 *     admin user — a full takeover.
 *
 * The marker now lives outside the web root, and — crucially — a populated
 * users table counts as installed on its own. An attacker cannot delete
 * rows they cannot reach, so the installer stays closed even if every
 * marker file disappears.
 */
class Installation
{
    /** Marker written by the installer, outside the web root. */
    public static function markerPath(): string
    {
        return storage_path('installed');
    }

    /** Pre-existing installs kept their marker in the web root. */
    public static function legacyMarkerPath(): string
    {
        return public_path('.installed');
    }

    /**
     * Where the step-by-step database installer records which seeders have
     * completed, so a run interrupted by a timeout resumes instead of
     * starting over.
     */
    public static function progressPath(): string
    {
        return storage_path('framework/install-progress.json');
    }

    public static function isInstalled(): bool
    {
        if (File::exists(self::markerPath()) || File::exists(self::legacyMarkerPath())) {
            return true;
        }

        // No marker: fall back to the database. Creating the admin account is
        // the installer's final step, so an existing admin means the install
        // finished — whatever happened to the marker file.
        return self::hasAdminUser();
    }

    /**
     * Record the installation. Writes the marker outside the web root and
     * removes the legacy one so it can no longer be served or scraped.
     */
    public static function markInstalled(): void
    {
        File::put(self::markerPath(), now()->toDateTimeString());
        @chmod(self::markerPath(), 0600);

        if (File::exists(self::legacyMarkerPath())) {
            @File::delete(self::legacyMarkerPath());
        }
    }

    /**
     * True when the database already holds an administrator account.
     *
     * Any failure (no database, tables not migrated) means "not installed", so
     * a genuine first-time install can still proceed.
     */
    public static function hasAdminUser(): bool
    {
        try {
            $schema = DB::getSchemaBuilder();

            if (! $schema->hasTable('users')) {
                return false;
            }

            // Roles may not be migrated yet on a half-finished install; then
            // the presence of any account is the best signal we have.
            if (! $schema->hasTable('roles') || ! $schema->hasTable('role_user')) {
                return DB::table('users')->exists();
            }

            return DB::table('role_user')
                ->join('roles', 'roles.id', '=', 'role_user.role_id')
                ->where('roles.slug', 'admin')
                ->exists();
        } catch (\Throwable) {
            return false;
        }
    }
}
