<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $settings = DB::table('theme_settings')->first();

        if (!$settings) {
            return;
        }

        $legalLinks = json_decode($settings->legal_links ?? '[]', true) ?: [];

        // Check if privacy policy link already exists
        $hasPrivacy = collect($legalLinks)->contains(fn ($link) =>
            str_contains(strtolower($link['url'] ?? ''), 'privacy')
        );

        // Check if terms link already exists
        $hasTerms = collect($legalLinks)->contains(fn ($link) =>
            str_contains(strtolower($link['url'] ?? ''), 'terms')
        );

        $updated = false;

        if (!$hasPrivacy) {
            $legalLinks[] = ['name' => 'Privacy Policy', 'url' => '/page/privacy-policy'];
            $updated = true;
        }

        if (!$hasTerms) {
            $legalLinks[] = ['name' => 'Terms & Conditions', 'url' => '/page/terms-and-conditions'];
            $updated = true;
        }

        if ($updated) {
            DB::table('theme_settings')
                ->where('id', $settings->id)
                ->update(['legal_links' => json_encode($legalLinks)]);
        }
    }

    public function down(): void
    {
        // No-op: we don't remove links on rollback as they may have been customized
    }
};
