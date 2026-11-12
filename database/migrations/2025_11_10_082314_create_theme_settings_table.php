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
        Schema::create('theme_settings', function (Blueprint $table) {
            $table->id();
            $table->string('site_name')->default('Teleman AI');
            $table->string('site_tagline')->default('Voice Calling Reimagined');
            $table->string('logo_path')->nullable();
            $table->string('primary_color')->default('#000000');
            $table->string('secondary_color')->default('#ffffff');
            $table->boolean('can_register')->default(true);
            $table->json('trust_badges')->nullable(); // For hero section trust indicators
            $table->json('social_links')->nullable(); // Twitter, LinkedIn, GitHub, Mail
            $table->json('legal_links')->nullable(); // Privacy, Terms, Cookies
            $table->string('copyright_text')->default('© 2025 Teleman AI. All rights reserved.');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('theme_settings');
    }
};
