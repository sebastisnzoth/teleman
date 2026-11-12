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
        Schema::create('theme_footer', function (Blueprint $table) {
            $table->id();
            $table->string('cta_badge_text')->default('Join 10,000+ businesses');
            $table->string('cta_badge_icon')->default('Rocket');
            $table->string('cta_headline')->default('Ready to Transform Your Voice Campaigns?');
            $table->text('cta_description')->nullable();
            $table->string('cta_primary_text')->default('Buy Credits');
            $table->string('cta_primary_icon')->default('Coins');
            $table->string('cta_secondary_text')->default('Sign In');
            $table->json('trust_indicators')->nullable(); // Array of trust badges
            $table->string('background_gradient')->default('from-gray-900 via-gray-800 to-black');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('theme_footer');
    }
};
