<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('theme_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('theme_settings', 'logo_dark_path')) {
                $table->string('logo_dark_path')->nullable()->after('logo_path');
            }
            // Per-surface display mode: logo_text | text_only | logo_only
            foreach (['landing', 'dashboard', 'auth'] as $surface) {
                if (!Schema::hasColumn('theme_settings', "logo_display_{$surface}")) {
                    $table->string("logo_display_{$surface}", 20)->default('logo_text');
                }
            }
            // Per-surface logo height in px
            if (!Schema::hasColumn('theme_settings', 'logo_size_landing')) {
                $table->unsignedSmallInteger('logo_size_landing')->default(34);
            }
            if (!Schema::hasColumn('theme_settings', 'logo_size_dashboard')) {
                $table->unsignedSmallInteger('logo_size_dashboard')->default(32);
            }
            if (!Schema::hasColumn('theme_settings', 'logo_size_auth')) {
                $table->unsignedSmallInteger('logo_size_auth')->default(40);
            }
        });
    }

    public function down(): void
    {
        Schema::table('theme_settings', function (Blueprint $table) {
            foreach ([
                'logo_dark_path',
                'logo_display_landing', 'logo_display_dashboard', 'logo_display_auth',
                'logo_size_landing', 'logo_size_dashboard', 'logo_size_auth',
            ] as $col) {
                if (Schema::hasColumn('theme_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
