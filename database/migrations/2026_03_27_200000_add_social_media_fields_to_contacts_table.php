<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('facebook_url', 500)->nullable()->after('website');
            $table->string('instagram_url', 500)->nullable()->after('facebook_url');
            $table->string('linkedin_url', 500)->nullable()->after('instagram_url');
            $table->string('twitter_url', 500)->nullable()->after('linkedin_url');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['facebook_url', 'instagram_url', 'linkedin_url', 'twitter_url']);
        });
    }
};
