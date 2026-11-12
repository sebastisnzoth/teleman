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
        Schema::create('theme_hero', function (Blueprint $table) {
            $table->id();
            $table->string('badge_text')->default('Pay As You Go • Credit Based System');
            $table->string('badge_icon_left')->default('Sparkles');
            $table->string('badge_icon_right')->default('Coins');
            $table->string('typewriter_text')->default('Voice Calling Reimagined');
            $table->text('subtitle')->nullable();
            $table->string('subtitle_emoji')->nullable()->default('💳');
            $table->string('primary_cta_text')->default('Get Started');
            $table->string('primary_cta_icon')->default('Coins');
            $table->string('secondary_cta_text')->default('How It Works');
            $table->string('secondary_cta_icon')->default('Zap');
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
        Schema::dropIfExists('theme_hero');
    }
};
