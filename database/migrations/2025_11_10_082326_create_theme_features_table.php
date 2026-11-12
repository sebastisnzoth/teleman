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
        Schema::create('theme_features', function (Blueprint $table) {
            $table->id();
            $table->string('icon'); // Lucide icon name (e.g., 'Bot')
            $table->string('title');
            $table->text('description');
            $table->string('gradient')->default('from-gray-900 via-gray-800 to-gray-700');
            $table->string('icon_bg')->default('from-gray-900 to-gray-700');
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
        Schema::dropIfExists('theme_features');
    }
};
