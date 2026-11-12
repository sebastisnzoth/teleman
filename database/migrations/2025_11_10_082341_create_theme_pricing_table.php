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
        Schema::create('theme_pricing', function (Blueprint $table) {
            $table->id();
            $table->string('credits'); // e.g., '100', '500', '5,000+'
            $table->string('price'); // e.g., '$10', 'Custom'
            $table->string('per_credit'); // e.g., '$0.10', 'Best Rate'
            $table->boolean('popular')->default(false);
            $table->string('icon')->default('Coins');
            $table->string('savings')->nullable(); // e.g., '10% off'
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('theme_pricing');
    }
};
