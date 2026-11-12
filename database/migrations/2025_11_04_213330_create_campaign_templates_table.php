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
        Schema::create('campaign_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('category', ['promotional', 'lead_qualification', 'survey', 'notification', 'personalized'])->default('promotional');
            $table->json('template_data'); // Full campaign configuration
            $table->boolean('is_system_template')->default(false);
            $table->integer('usage_count')->default(0);
            $table->timestamps();
            
            $table->index(['user_id', 'category']);
            $table->index('is_system_template');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_templates');
    }
};
