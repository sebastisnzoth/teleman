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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->comment('User who owns this setting');
            $table->string('key', 100)->comment('Setting key');
            $table->text('value')->nullable()->comment('Setting value');
            $table->boolean('encrypted')->default(false)->comment('Whether value is encrypted');
            $table->boolean('is_global')->default(false)->comment('Admin-only global settings');
            $table->timestamps();
            
            // Unique constraint: one key per user (or global)
            $table->unique(['user_id', 'key'], 'unique_user_key');
            
            // Indexes
            $table->index('key');
            $table->index('is_global');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
