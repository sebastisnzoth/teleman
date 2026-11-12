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
        Schema::create('theme_use_cases', function (Blueprint $table) {
            $table->id();
            $table->string('icon'); // Lucide icon name
            $table->string('title');
            $table->text('description');
            $table->json('items'); // Array of feature items
            $table->string('color')->default('from-gray-900 to-gray-700');
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
        Schema::dropIfExists('theme_use_cases');
    }
};
