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
        Schema::create('theme_stats', function (Blueprint $table) {
            $table->id();
            $table->string('number'); // e.g., '10M+', '99.9%'
            $table->string('label'); // e.g., 'Calls Delivered'
            $table->string('icon')->default('PhoneCall'); // Lucide icon name
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
        Schema::dropIfExists('theme_stats');
    }
};
