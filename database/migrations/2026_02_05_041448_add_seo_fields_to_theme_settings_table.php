<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('theme_settings', function (Blueprint $table) {
            $table->string('meta_title')->nullable()->after('site_tagline');
            $table->text('meta_description')->nullable()->after('meta_title');
            $table->text('meta_keywords')->nullable()->after('meta_description');
            $table->string('og_image_path')->nullable()->after('favicon_path');
            $table->string('twitter_card')->default('summary_large_image')->after('og_image_path');
            $table->string('twitter_site')->nullable()->after('twitter_card');
            $table->text('google_analytics_id')->nullable()->after('twitter_site');
            $table->text('google_tag_manager_id')->nullable()->after('google_analytics_id');
            $table->text('facebook_pixel_id')->nullable()->after('google_tag_manager_id');
            $table->text('custom_head_scripts')->nullable()->after('facebook_pixel_id');
            $table->text('custom_body_scripts')->nullable()->after('custom_head_scripts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('theme_settings', function (Blueprint $table) {
            $table->dropColumn([
                'meta_title',
                'meta_description',
                'meta_keywords',
                'og_image_path',
                'twitter_card',
                'twitter_site',
                'google_analytics_id',
                'google_tag_manager_id',
                'facebook_pixel_id',
                'custom_head_scripts',
                'custom_body_scripts',
            ]);
        });
    }
};
