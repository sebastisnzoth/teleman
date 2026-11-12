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
        Schema::create('ai_agents', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type')->default('inbound'); // inbound, outbound
            $table->text('system_prompt');
            $table->string('model')->default('google/gemini-flash-1.5');
            $table->string('voice')->default('alloy');
            $table->integer('max_tokens')->default(150);
            $table->decimal('temperature', 3, 2)->default(0.70);
            $table->boolean('active')->default(true);
            $table->json('settings')->nullable(); // Additional settings
            $table->boolean('enable_transfer')->default(true);
            $table->string('transfer_number')->nullable();
            $table->boolean('enable_recording')->default(true);
            $table->integer('max_duration')->default(600); // seconds
            $table->integer('silence_timeout')->default(10); // seconds
            $table->json('trigger_keywords')->nullable(); // For transfer triggers
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_agents');
    }
};
