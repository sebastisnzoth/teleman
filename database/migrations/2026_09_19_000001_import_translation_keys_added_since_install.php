<?php

declare(strict_types=1);

use App\Services\TranslationService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Give an upgrading installation the interface wording added since it was
 * installed.
 *
 * The interface reads its text from the database, and the database is filled
 * by a seeder — which only ever runs when the database is first created. So
 * an installation that upgrades keeps the catalogue it was installed with,
 * and every string added since then reaches its administrators as the raw key
 * instead of the words: "admin.telephony.locked_to" where "Locked to" belongs.
 * The shipped language files have always had the words; nothing carried them
 * across an upgrade.
 *
 * Migrations are what an upgrade runs, so this is where that gap closes.
 * It adds missing keys only, in every language that ships a file, and leaves
 * every value already in the database alone — an administrator who rewrote a
 * string in the translation editor keeps their wording.
 *
 * On a fresh install this finds no languages yet (the seeders run after the
 * migrations) and does nothing; the seeder then imports the full catalogue.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('languages') || ! Schema::hasTable('translations')) {
            return;
        }

        app(TranslationService::class)->importMissingFromFiles();
    }

    public function down(): void
    {
        // Nothing to undo. These are the words the installed version needs to
        // render its own screens; removing them would leave raw keys behind,
        // and the rows are indistinguishable from any other translation.
    }
};
